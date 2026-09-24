<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\CustomPackageInquiry;
use App\Models\Inquiry;
use App\Models\TicketBooking;
use App\Models\VisaApplication;
use Illuminate\Support\Facades\DB;

class CrmSyncService
{
    /**
     * Scan existing inquiries, package requests, ticketing quotations, and visa applications
     * and ensure they exist as CRM leads in the unified pipeline.
     */
    public function syncAll(): int
    {
        $newCount = 0;

        DB::transaction(function () use (&$newCount) {
            // 1. Sync Custom Package Inquiries
            $customInquiries = CustomPackageInquiry::all();
            foreach ($customInquiries as $inquiry) {
                $exists = CrmLead::where('source_type', CustomPackageInquiry::class)
                    ->where('source_id', $inquiry->id)
                    ->exists();

                if (! $exists) {
                    $stage = match ($inquiry->status) {
                        'quoted' => CrmLead::STAGE_QUOTED,
                        'booked' => CrmLead::STAGE_WON,
                        'cancelled' => CrmLead::STAGE_LOST,
                        default => CrmLead::STAGE_NEW,
                    };

                    $budget = (float) ($inquiry->estimated_budget ?? 0);
                    $priority = $budget >= 100000 ? 'urgent' : ($budget >= 40000 ? 'high' : 'medium');

                    CrmLead::create([
                        'reference_code' => CrmLead::generateReferenceCode(),
                        'client_name' => $inquiry->client_name,
                        'client_email' => $inquiry->client_email,
                        'client_phone' => $inquiry->client_phone,
                        'service_type' => 'custom_tour',
                        'source' => 'website',
                        'title' => 'Custom Tour: '.($inquiry->destination_name ?: 'Custom Destination').' ('.($inquiry->number_of_pax ?? 1).' Pax)',
                        'destination' => $inquiry->destination_name,
                        'travel_date' => $inquiry->check_in_date,
                        'number_of_pax' => $inquiry->number_of_pax ?? 1,
                        'estimated_value' => $budget,
                        'currency' => $inquiry->currency ?: 'PHP',
                        'stage' => $stage,
                        'priority' => $priority,
                        'assigned_to' => null,
                        'source_type' => CustomPackageInquiry::class,
                        'source_id' => $inquiry->id,
                        'notes' => $inquiry->special_requests ?? $inquiry->agent_notes,
                        'created_at' => $inquiry->created_at,
                        'updated_at' => $inquiry->updated_at,
                    ]);
                    $newCount++;
                }
            }

            // 2. Sync General Contact Inquiries
            $inquiries = Inquiry::all();
            foreach ($inquiries as $inquiry) {
                $exists = CrmLead::where('source_type', Inquiry::class)
                    ->where('source_id', $inquiry->id)
                    ->exists();

                if (! $exists) {
                    $stage = match ($inquiry->status) {
                        'contacted' => CrmLead::STAGE_CONTACTED,
                        'closed' => CrmLead::STAGE_WON,
                        default => CrmLead::STAGE_NEW,
                    };

                    CrmLead::create([
                        'reference_code' => CrmLead::generateReferenceCode(),
                        'client_name' => $inquiry->name,
                        'client_email' => $inquiry->email,
                        'client_phone' => $inquiry->phone,
                        'service_type' => 'general',
                        'source' => 'website',
                        'title' => 'Web Inquiry: '.($inquiry->service_requested ?: 'Travel Request'),
                        'destination' => null,
                        'travel_date' => null,
                        'number_of_pax' => 1,
                        'estimated_value' => 0,
                        'currency' => 'PHP',
                        'stage' => $stage,
                        'priority' => 'medium',
                        'assigned_to' => null,
                        'source_type' => Inquiry::class,
                        'source_id' => $inquiry->id,
                        'notes' => $inquiry->message,
                        'created_at' => $inquiry->created_at,
                        'updated_at' => $inquiry->updated_at,
                    ]);
                    $newCount++;
                }
            }

            // 3. Sync Ticket Bookings (especially Quotations or Pending)
            $ticketBookings = TicketBooking::where(function ($q) {
                $q->where('is_quotation', true)
                    ->orWhere('status', TicketBooking::STATUS_PENDING);
            })->get();

            foreach ($ticketBookings as $ticket) {
                $exists = CrmLead::where('source_type', TicketBooking::class)
                    ->where('source_id', $ticket->id)
                    ->exists();

                if (! $exists) {
                    $stage = $ticket->is_quotation
                        ? CrmLead::STAGE_QUOTED
                        : match ($ticket->status) {
                            TicketBooking::STATUS_CONFIRMED, TicketBooking::STATUS_ISSUED => CrmLead::STAGE_WON,
                            TicketBooking::STATUS_CANCELLED => CrmLead::STAGE_LOST,
                            default => CrmLead::STAGE_NEW,
                        };

                    CrmLead::create([
                        'reference_code' => CrmLead::generateReferenceCode(),
                        'client_name' => $ticket->contact_name ?: 'Flight Client',
                        'client_email' => $ticket->contact_email,
                        'client_phone' => $ticket->contact_phone,
                        'service_type' => 'flight_ticket',
                        'source' => 'portal',
                        'title' => ($ticket->is_quotation ? 'Quotation: ' : 'Flight: ').($ticket->origin ? $ticket->origin.' → ' : '').($ticket->destination ?: 'Ticket'),
                        'destination' => $ticket->destination,
                        'travel_date' => $ticket->departure_date,
                        'number_of_pax' => $ticket->total_passengers ?? 1,
                        'estimated_value' => $ticket->total_amount ?? 0,
                        'currency' => 'PHP',
                        'stage' => $stage,
                        'priority' => ($ticket->total_amount > 50000) ? 'high' : 'medium',
                        'assigned_to' => $ticket->created_by,
                        'source_type' => TicketBooking::class,
                        'source_id' => $ticket->id,
                        'notes' => $ticket->special_requests,
                        'created_at' => $ticket->created_at,
                        'updated_at' => $ticket->updated_at,
                    ]);
                    $newCount++;
                }
            }

            // 4. Sync Visa Applications
            $visaApplications = VisaApplication::all();
            foreach ($visaApplications as $app) {
                $exists = CrmLead::where('source_type', VisaApplication::class)
                    ->where('source_id', $app->id)
                    ->exists();

                if (! $exists) {
                    $stage = match ($app->status) {
                        'released', 'acknowledged', 'payment' => CrmLead::STAGE_WON,
                        'cancelled' => CrmLead::STAGE_LOST,
                        'pending' => CrmLead::STAGE_NEW,
                        default => CrmLead::STAGE_CONTACTED,
                    };

                    CrmLead::create([
                        'reference_code' => CrmLead::generateReferenceCode(),
                        'client_name' => $app->client_name,
                        'client_email' => $app->client_email,
                        'client_phone' => $app->client_phone,
                        'service_type' => 'visa_assistance',
                        'source' => 'portal',
                        'title' => 'Visa Application: '.($app->destination_country ?: 'Travel Visa').' ('.ucfirst(str_replace('_', ' ', $app->service_type)).')',
                        'destination' => $app->destination_country,
                        'travel_date' => null,
                        'number_of_pax' => 1,
                        'estimated_value' => $app->total_amount ?? 0,
                        'currency' => $app->currency ?: 'PHP',
                        'stage' => $stage,
                        'priority' => ($app->processing_speed === 'rush') ? 'urgent' : 'medium',
                        'assigned_to' => $app->created_by,
                        'source_type' => VisaApplication::class,
                        'source_id' => $app->id,
                        'notes' => $app->remarks,
                        'created_at' => $app->created_at,
                        'updated_at' => $app->updated_at,
                    ]);
                    $newCount++;
                }
            }
        });

        return $newCount;
    }

    /**
     * Propagate CRM stage updates back to source models when applicable.
     */
    public function syncLeadToSource(CrmLead $lead): void
    {
        if (! $lead->source_type || ! $lead->source_id) {
            return;
        }

        if ($lead->source_type === CustomPackageInquiry::class) {
            $inquiry = CustomPackageInquiry::find($lead->source_id);
            if ($inquiry) {
                $targetStatus = match ($lead->stage) {
                    CrmLead::STAGE_QUOTED => 'quoted',
                    CrmLead::STAGE_WON => 'booked',
                    CrmLead::STAGE_LOST => 'cancelled',
                    default => 'pending',
                };
                if ($inquiry->status !== $targetStatus) {
                    $inquiry->update(['status' => $targetStatus]);
                }
            }
        } elseif ($lead->source_type === TicketBooking::class) {
            $ticket = TicketBooking::find($lead->source_id);
            if ($ticket) {
                $targetStatus = match ($lead->stage) {
                    CrmLead::STAGE_WON => TicketBooking::STATUS_CONFIRMED,
                    CrmLead::STAGE_LOST => TicketBooking::STATUS_CANCELLED,
                    default => TicketBooking::STATUS_PENDING,
                };
                if ($ticket->status !== $targetStatus) {
                    $ticket->update(['status' => $targetStatus]);
                }
            }
        } elseif ($lead->source_type === Inquiry::class) {
            $inquiry = Inquiry::find($lead->source_id);
            if ($inquiry) {
                $targetStatus = match ($lead->stage) {
                    CrmLead::STAGE_CONTACTED, CrmLead::STAGE_QUOTED => 'contacted',
                    CrmLead::STAGE_WON => 'closed',
                    default => 'pending',
                };
                if ($inquiry->status !== $targetStatus) {
                    $inquiry->update(['status' => $targetStatus]);
                }
            }
        }
    }
}
