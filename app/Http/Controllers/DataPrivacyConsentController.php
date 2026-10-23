<?php

namespace App\Http\Controllers;

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\VisaApplication;
use App\Support\DataPrivacyConsent;
use App\Support\ImmigrationServices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function visa(VisaApplication $application): View|RedirectResponse
    {
        if ($held = $application->approvalBlocker('printed')) {
            return redirect()->route('visa.applications.show', $application)->with('error', $held);
        }

        return $this->form(DataPrivacyConsent::forVisa($application));
    }

    public function srrvApplication(SrrvApplication $application): View|RedirectResponse
    {
        if ($held = $application->approvalBlocker('printed')) {
            return redirect()->route('srrv.applications.show', $application)->with('error', $held);
        }

        return $this->form(DataPrivacyConsent::forSrrvApplication($application));
    }

    public function srrvRenewal(SrrvRenewal $renewal): View|RedirectResponse
    {
        if ($held = $renewal->approvalBlocker('printed')) {
            return redirect()->route('srrv.renewals.show', $renewal)->with('error', $held);
        }

        return $this->form(DataPrivacyConsent::forSrrvRenewal($renewal));
    }

    public function immigration(Request $request, ImmigrationClient $clientSheet): View
    {
        // Back to the folder (and service) the consent was printed from.
        $backUrl = ImmigrationServices::folderUrl($clientSheet, $request->input('service'), $request->integer('case') ?: null);

        return $this->form(DataPrivacyConsent::forImmigration($clientSheet, $backUrl));
    }

    private function form(DataPrivacyConsent $consent): View
    {
        return view('consent.data-privacy', ['consent' => $consent]);
    }
}
