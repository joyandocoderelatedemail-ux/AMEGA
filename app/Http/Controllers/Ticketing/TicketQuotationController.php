<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use App\Models\User;
use App\Notifications\TicketApprovalRequestedNotification;
use App\Notifications\TicketBookedNotification;
use App\Services\ActivityLogger;
use App\Services\ClientNotifier;
use App\Services\ClientProfileService;
use App\Support\Approvals;
use App\Support\DocumentStorage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quotations: trips priced and saved without the travel documents (Save as Quotation in the
 * wizard). The list shows the open ones; Convert to booking asks for what a booking needs (the
 * flight booked, the contact, every passenger with a valid passport, the agent's acknowledgement),
 * runs the same checks as the wizard, and sends the booking to the admin for approval.
 */
class TicketQuotationController extends Controller
{
    public function index(Request $request): View
    {
        $quotations = TicketBooking::with('passengers', 'createdBy')
            ->where('is_quotation', true)
            ->where('status', '!=', TicketBooking::STATUS_CANCELLED)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($q) => $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('ticketing.quotations.index', compact('quotations'));
    }

    public function convert(TicketBooking $ticket): View|RedirectResponse
    {
        if ($reason = $this->notConvertible($ticket)) {
            return redirect()->route('ticketing.tickets.show', $ticket)->with('error', $reason);
        }

        $ticket->load('passengers.documents');
        $airlines = Airline::offered()->get();
        if ($ticket->airline && ! $airlines->contains('id', $ticket->airline_id)) {
            $airlines->push($ticket->airline);
        }

        return view('ticketing.quotations.convert', [
            'ticket' => $ticket,
            'airlines' => $airlines,
            'rows' => $this->passengerRows($ticket),
        ]);
    }

    public function storeConversion(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($reason = $this->notConvertible($ticket)) {
            return redirect()->route('ticketing.tickets.show', $ticket)->with('error', $reason);
        }

        $ticket->load('passengers.documents');
        $international = $ticket->travel_type === 'international';
        $roundTrip = $ticket->trip_type === 'round_trip';

        $rules = [
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => [$international ? 'required' : 'nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => [$international ? 'required' : 'nullable', 'string', 'max:100'],
            'emergency_contact_phone' => [$international ? 'required' : 'nullable', 'string', 'max:50'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            // The flight that was booked, as the wizard asks for a booking.
            'airline_id' => ['required', 'integer', Rule::exists('airlines', 'id')],
            'airline_pnr' => ['nullable', 'string', 'alpha_num', 'max:20'],
            'flight_number' => ['required', 'string', 'max:20'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'return_flight_number' => [$roundTrip ? 'required' : 'nullable', 'string', 'max:20'],
            'return_departure_time' => [$roundTrip ? 'required' : 'nullable', 'date_format:H:i'],
            'return_arrival_time' => [$roundTrip ? 'required' : 'nullable', 'date_format:H:i'],
            // Every passenger, named, with their passport.
            'passengers' => ['required', 'array', 'size:'.(int) $ticket->total_passengers],
            'passengers.*.id' => ['nullable', 'integer', Rule::exists('ticket_passengers', 'id')->where('ticket_booking_id', $ticket->id)],
            'passengers.*.passenger_type' => ['required', Rule::in(['adult', 'child', 'infant'])],
            'passengers.*.first_name' => ['required', 'string', 'max:100'],
            'passengers.*.middle_name' => ['nullable', 'string', 'max:100'],
            'passengers.*.last_name' => ['required', 'string', 'max:100'],
            'passengers.*.gender' => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'passengers.*.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'passengers.*.nationality_type' => ['required', Rule::in(['filipino', 'foreign_national'])],
            'passengers.*.passport_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.passport_expiry_date' => ['required', 'date'],
            'passengers.*.passport_file' => 'nullable|'.ClientProfileService::SCAN_RULE,
            'agent_acknowledged' => ['accepted'],
        ];

        $validated = $request->validate($rules, [
            'passengers.size' => 'Enter all :size passengers.',
            'passengers.*.first_name.required' => 'Enter the first name.',
            'passengers.*.last_name.required' => 'Enter the last name.',
            'passengers.*.passport_expiry_date.required' => 'Enter the passport expiry date.',
            'passengers.*.passport_file.mimes' => 'Attach a JPG, PNG, WEBP or PDF file.',
            'passengers.*.passport_file.max' => 'The file is larger than 5 MB.',
            'agent_acknowledged.accepted' => 'Tick the acknowledgement to confirm the information is correct.',
        ]);

        $departure = Carbon::parse($ticket->departure_date);
        $errors = [];

        if ($departure->isPast() && ! $departure->isToday()) {
            $errors['departure_date'] = 'The departure date ('.$departure->format('M d, Y').') has passed: edit the trip first.';
        }

        // The passengers match the party that was quoted.
        $counts = collect($validated['passengers'])->countBy('passenger_type');
        foreach (['adult' => 'adults_count', 'child' => 'children_count', 'infant' => 'infants_count'] as $type => $column) {
            if ((int) ($counts[$type] ?? 0) !== (int) $ticket->{$column}) {
                $errors['passengers'] = 'The passengers must be '.$ticket->adults_count.' adult(s), '.$ticket->children_count.' child(ren) and '.$ticket->infants_count.' infant(s), as quoted.';
            }
        }

        $existing = $ticket->passengers->keyBy('id');
        foreach ($validated['passengers'] as $index => $data) {
            $label = 'Passenger #'.($index + 1).' ('.trim($data['first_name'].' '.$data['last_name']).')';

            if (! empty($data['date_of_birth'])) {
                $ageType = TicketPassenger::typeForAge(Carbon::parse($data['date_of_birth']), $departure);
                if ($ageType !== $data['passenger_type']) {
                    $errors["passengers.{$index}.date_of_birth"] = "{$label} is booked as ".ucfirst($data['passenger_type']).', but that date of birth makes them '.ucfirst($ageType).' on the departure date.';
                }
            }

            if (Carbon::parse($data['passport_expiry_date'])->lt($departure->copy()->addMonths(6))) {
                $errors["passengers.{$index}.passport_expiry_date"] = "{$label}: the passport must be valid for at least six (6) months after departure ({$departure->format('M d, Y')}).";
            }

            // International travel and foreign nationals need the passport number and its scan.
            if ($international || $data['nationality_type'] === 'foreign_national') {
                if (blank($data['passport_number'] ?? null)) {
                    $errors["passengers.{$index}.passport_number"] = "{$label} needs a passport number.";
                }
                $hasScan = $request->hasFile("passengers.{$index}.passport_file")
                    || (! empty($data['id']) && $existing->get((int) $data['id'])?->documents->contains('document_type', 'passport_scan'));
                if (! $hasScan) {
                    $errors["passengers.{$index}.passport_file"] = "{$label}: upload the passport scan.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($request, $ticket, $validated, $existing) {
            $number = (int) $ticket->passengers->max('passenger_number');

            foreach ($validated['passengers'] as $index => $data) {
                $details = [
                    'passenger_type' => $data['passenger_type'],
                    'nationality_type' => $data['nationality_type'],
                    'first_name' => trim($data['first_name']),
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => trim($data['last_name']),
                    'gender' => $data['gender'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_expiry_date' => $data['passport_expiry_date'],
                ];

                $passenger = ! empty($data['id']) ? $existing->get((int) $data['id']) : null;
                if ($passenger) {
                    $passenger->update($details);
                } else {
                    $passenger = $ticket->passengers()->create($details + ['passenger_number' => ++$number]);
                }

                if ($request->hasFile("passengers.{$index}.passport_file")) {
                    $file = $request->file("passengers.{$index}.passport_file");
                    $passenger->documents()->create([
                        'document_type' => 'passport_scan',
                        'file_path' => $file->store("tickets/{$ticket->booking_reference}/p{$passenger->passenger_number}", DocumentStorage::diskName()),
                        'original_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType(),
                        'status' => 'uploaded',
                    ]);
                }
            }

            $code = fn (?string $value): ?string => filled($value) ? strtoupper(str_replace(' ', '', trim($value))) : null;
            $roundTrip = $ticket->trip_type === 'round_trip';

            $ticket->fill([
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? $ticket->emergency_contact_name,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? $ticket->emergency_contact_relationship,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? $ticket->emergency_contact_phone,
                'emergency_contact_email' => $validated['emergency_contact_email'] ?? $ticket->emergency_contact_email,
                'airline_id' => $validated['airline_id'],
                'airline_pnr' => $code($validated['airline_pnr'] ?? null),
                'flight_number' => $code($validated['flight_number']),
                'departure_time' => $validated['departure_time'],
                'arrival_time' => $validated['arrival_time'],
                'return_flight_number' => $roundTrip ? $code($validated['return_flight_number'] ?? null) : null,
                'return_departure_time' => $roundTrip ? ($validated['return_departure_time'] ?? null) : null,
                'return_arrival_time' => $roundTrip ? ($validated['return_arrival_time'] ?? null) : null,
            ]);
            $ticket->forceFill([
                'is_quotation' => false,
                'agent_acknowledged_by' => Auth::id(),
                'agent_acknowledged_at' => now(),
            ]);
            // Checked by an admin before the cashier takes payment, like every booking.
            $ticket->submitForApproval();
            $ticket->save();
        });

        ActivityLogger::log('Ticketing', 'CONVERT', "Converted quotation {$ticket->booking_reference} for {$ticket->contact_name} into a booking");

        ClientNotifier::send($ticket->contact_email, $ticket->contact_name, new TicketBookedNotification($ticket));
        try {
            Notification::send(
                Approvals::recipients('ticketing', $ticket->createdBy),
                new TicketApprovalRequestedNotification($ticket->loadMissing('createdBy')),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $message = "Quotation {$ticket->booking_reference} is now a booking and was sent to the admin for approval. Once approved, the cashier records the payment.";
        if (! $ticket->fresh('passengers.documents')->hasAllRequiredDocuments()) {
            $message .= ' Some required documents are still missing: upload them here before the ticket is issued.';
        }

        return redirect()->route('ticketing.tickets.show', $ticket)->with('success', $message);
    }

    /** Why this file cannot be converted, or null when it can. */
    private function notConvertible(TicketBooking $ticket): ?string
    {
        return match (true) {
            ! $ticket->isQuotation() => 'This is already a booking.',
            $ticket->isCancelled() => 'A cancelled quotation cannot be converted.',
            default => null,
        };
    }

    /**
     * A row for every passenger quoted: the ones already named, then an empty one for each
     * passenger still to be named, typed so the party matches the quote.
     *
     * @return list<array<string, mixed>>
     */
    private function passengerRows(TicketBooking $ticket): array
    {
        $rows = $ticket->passengers->sortBy('passenger_number')->map(fn (TicketPassenger $p) => [
            'id' => $p->id,
            'passenger_type' => $p->passenger_type,
            'first_name' => $p->first_name,
            'middle_name' => $p->middle_name,
            'last_name' => $p->last_name,
            'gender' => $p->gender,
            'date_of_birth' => $p->date_of_birth?->toDateString(),
            'nationality_type' => $p->nationality_type ?: 'filipino',
            'passport_number' => $p->passport_number,
            'passport_expiry_date' => $p->passport_expiry_date?->toDateString(),
            'has_scan' => $p->documents->contains('document_type', 'passport_scan'),
        ])->values()->all();

        $named = collect($rows)->countBy('passenger_type');
        foreach (['adult' => 'adults_count', 'child' => 'children_count', 'infant' => 'infants_count'] as $type => $column) {
            for ($i = (int) ($named[$type] ?? 0); $i < (int) $ticket->{$column}; $i++) {
                $rows[] = ['id' => null, 'passenger_type' => $type, 'first_name' => '', 'middle_name' => '', 'last_name' => '', 'gender' => null,
                    'date_of_birth' => null, 'nationality_type' => 'filipino', 'passport_number' => '', 'passport_expiry_date' => null, 'has_scan' => false];
            }
        }

        return $rows;
    }
}
