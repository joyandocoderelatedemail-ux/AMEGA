<?php

namespace App\Http\Controllers;

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\VisaApplication;
use App\Support\DataPrivacyConsent;
use Illuminate\View\View;

/**
 * The printable Data Privacy Consent Form for a file on any desk.
 *
 * Each route sits inside its desk's access middleware, and desk files carry
 * the own-files scope, so an officer can only print consent for their files.
 */
class DataPrivacyConsentController extends Controller
{
    public function ticket(TicketBooking $ticket): View
    {
        return $this->form(DataPrivacyConsent::forTicket($ticket));
    }

    public function visa(VisaApplication $application): View
    {
        return $this->form(DataPrivacyConsent::forVisa($application));
    }

    public function srrvApplication(SrrvApplication $application): View
    {
        return $this->form(DataPrivacyConsent::forSrrvApplication($application));
    }

    public function srrvRenewal(SrrvRenewal $renewal): View
    {
        return $this->form(DataPrivacyConsent::forSrrvRenewal($renewal));
    }

    public function immigration(ImmigrationClient $clientSheet): View
    {
        return $this->form(DataPrivacyConsent::forImmigration($clientSheet));
    }

    private function form(DataPrivacyConsent $consent): View
    {
        return view('consent.data-privacy', ['consent' => $consent]);
    }
}
