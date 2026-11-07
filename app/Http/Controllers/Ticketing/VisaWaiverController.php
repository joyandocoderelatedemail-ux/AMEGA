<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Services\ActivityLogger;
use App\Services\ClientProfileService;
use App\Services\TicketDocumentPdf;
use App\Support\DocumentStorage;
use App\Support\VisaWaiver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The printable Visa Waiver for a booking whose passengers do not have their visa yet. It sits inside
 * the ticketing middleware, and tickets carry the own-files scope, so an officer prints only their own.
 */
class VisaWaiverController extends Controller
{
    public function show(TicketBooking $ticket): View|RedirectResponse
    {
        if (! $waiver = VisaWaiver::forTicket($ticket)) {
            return $this->nothingToSign($ticket);
        }

        return view('ticketing.waivers.visa-waiver', ['waiver' => $waiver]);
    }

    public function pdf(TicketBooking $ticket): Response|RedirectResponse
    {
        if (! $waiver = VisaWaiver::forTicket($ticket)) {
            return $this->nothingToSign($ticket);
        }

        return response(TicketDocumentPdf::visaWaiver($waiver), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$waiver->filename().'"',
        ]);
    }

    /**
     * The client signed the waiver: staff tick that it was signed and upload the signed copy. Until then
     * the ticket cannot be issued. Uploading again replaces the copy on file.
     */
    public function sign(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($ticket->isCancelled() || $ticket->isIssued()) {
            return back()->with('error', 'A cancelled or issued booking can no longer be changed.');
        }

        if (! $ticket->needsVisaWaiver()) {
            return $this->nothingToSign($ticket);
        }

        $validated = $request->validate([
            'client_signed' => ['accepted'],
            'file' => ['required', ...explode('|', ClientProfileService::SCAN_RULE)],
        ], [
            'client_signed.accepted' => 'Tick the box to confirm the client signed the waiver.',
            'file.required' => 'Upload the signed waiver.',
            'file.mimes' => 'Attach a JPG, PNG, WEBP or PDF file.',
            'file.max' => 'The file is larger than 5 MB.',
            'file.uploaded' => 'The file could not be uploaded. Check that it is under 5 MB and try again.',
        ]);

        $file = $request->file('file');
        $disk = DocumentStorage::disk();
        $previous = $ticket->visa_waiver_path;

        $path = $file->store("tickets/{$ticket->booking_reference}/visa-waiver", DocumentStorage::diskName());

        $ticket->update([
            'visa_waiver_signed_at' => now(),
            'visa_waiver_signed_by' => $request->user()->id,
            'visa_waiver_path' => $path,
            'visa_waiver_name' => $file->getClientOriginalName(),
        ]);

        // The old copy goes only once the new one is on record.
        if ($previous && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }

        ActivityLogger::log('Ticketing', 'VISA_WAIVER_SIGNED', "Signed Visa Waiver uploaded for {$ticket->booking_reference}", ['ticket_id' => $ticket->id]);

        return back()->with('success', 'Visa Waiver recorded as signed. The ticket can now be issued once everything else is in place.');
    }

    /** The signed copy that was uploaded, through the private document disk. */
    public function signed(TicketBooking $ticket): StreamedResponse|RedirectResponse
    {
        abort_unless($ticket->hasSignedVisaWaiver(), 404);

        $disk = DocumentStorage::disk();

        if (! $disk->exists($ticket->visa_waiver_path)) {
            return back()->with('error', 'The signed waiver file could not be found.');
        }

        return $disk->download($ticket->visa_waiver_path, $ticket->visa_waiver_name ?: 'signed-visa-waiver.pdf');
    }

    private function nothingToSign(TicketBooking $ticket): RedirectResponse
    {
        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('error', 'No passenger on this booking needs visa assistance, so there is no waiver to print.');
    }
}
