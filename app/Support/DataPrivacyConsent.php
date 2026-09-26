<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\VisaApplication;

/**
 * The agency's Data Privacy Consent Form, filled from a desk file.
 *
 * The services table is the paper form's own list. Each desk file ticks the
 * services it covers; staff can tick more on screen before printing when the
 * client avails of something else in the same visit.
 */
class DataPrivacyConsent
{
    /**
     * The services on the paper form, in the order the form lays them out.
     *
     * @var array<string, string>
     */
    public const SERVICES = [
        'ticket' => 'Purchase of Domestic or International Ticket',
        'package' => 'Purchase of Domestic or International Package',
        'immigration' => 'Visa Extension/Immigration Services',
        'visa' => 'Visa Assistance Services',
        'documentation' => 'Other Documentation Services',
        'day_tour' => 'Purchase of local/day tour',
        'passport' => 'Renewal and New Filipino, UK, US, Australia Passport Application',
    ];

    /**
     * @param  list<string>  $services  Keys of self::SERVICES that are ticked.
     */
    public function __construct(
        public string $clientName,
        public array $services,
        public string $reference,
        public string $backUrl,
    ) {}

    public static function forTicket(TicketBooking $ticket): self
    {
        $services = ['ticket'];

        if (in_array($ticket->package_type, ['with_package', 'custom_package'], true) || filled($ticket->package_name)) {
            $services[] = 'package';
        }

        if ((float) $ticket->visa_assistance_fee > 0) {
            $services[] = 'visa';
        }

        if (in_array('tour_package', $ticket->selected_services ?? [], true)) {
            $services[] = 'day_tour';
        }

        return new self(
            (string) $ticket->contact_name,
            $services,
            (string) $ticket->booking_reference,
            route('ticketing.tickets.show', $ticket),
        );
    }

    public static function forVisa(VisaApplication $application): self
    {
        return new self(
            (string) $application->client_name,
            [$application->service_type === 'passporting' ? 'passport' : 'visa'],
            (string) $application->reference,
            route('visa.applications.show', $application),
        );
    }

    public static function forSrrvApplication(SrrvApplication $application): self
    {
        return new self(
            (string) $application->retiree_name,
            ['documentation'],
            (string) $application->reference,
            route('srrv.applications.show', $application),
        );
    }

    public static function forSrrvRenewal(SrrvRenewal $renewal): self
    {
        return new self(
            (string) $renewal->retiree_name,
            ['documentation'],
            (string) $renewal->reference,
            route('srrv.renewals.show', $renewal),
        );
    }

    public static function forImmigration(ImmigrationClient $client): self
    {
        return new self(
            $client->full_name,
            ['immigration'],
            'Client sheet #'.$client->id,
            route('admin.client-sheets.edit', $client),
        );
    }

    public function isTicked(string $service): bool
    {
        return in_array($service, $this->services, true);
    }
}
