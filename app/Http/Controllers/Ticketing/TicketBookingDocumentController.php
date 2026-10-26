<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Models\TicketBookingDocument;
use App\Services\ActivityLogger;
use App\Support\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supporting documents kept with a ticket booking, so the office has a copy: the real e-ticket,
 * boarding passes, hotel and tour vouchers, insurance policies, receipts. Allowed at any point,
 * issued tickets included, since the airline's ticket arrives after issuing. Files stay on the
 * private documents disk; the {ticket} binding keeps each officer to their own bookings.
 */
class TicketBookingDocumentController extends Controller
{
    private const MAX_FILES = 10;

    public function store(Request $request, TicketBooking $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(TicketBookingDocument::TYPES))],
            'note' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'files.required' => 'Choose at least one file to upload.',
            'files.max' => 'Upload at most '.self::MAX_FILES.' files at a time.',
            'files.*.mimes' => 'Attach JPG, PNG, WEBP or PDF files.',
            'files.*.max' => 'Each file must be 10 MB or smaller.',
            'files.*.uploaded' => 'A file could not be uploaded. Check that it is under 10 MB and try again.',
        ]);

        foreach ($request->file('files') as $file) {
            $ticket->supportingDocuments()->create([
                'document_type' => $validated['document_type'],
                'note' => $validated['note'] ?? null,
                'file_path' => $file->store("tickets/{$ticket->booking_reference}/supporting", DocumentStorage::diskName()),
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $count = count($request->file('files'));
        $label = TicketBookingDocument::TYPES[$validated['document_type']];
        ActivityLogger::log('Ticketing', 'UPLOAD_DOCUMENT', "Kept {$count} supporting document(s) ({$label}) on {$ticket->booking_reference}");

        return redirect()->to(route('ticketing.tickets.show', $ticket).'#supporting-documents')
            ->with('success', $count === 1 ? 'Supporting document saved.' : "{$count} supporting documents saved.");
    }

    public function download(TicketBookingDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a booking this staff member can see (their own, or any for admins).
        abort_unless($document->booking, 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document file could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    public function destroy(TicketBooking $ticket, TicketBookingDocument $supportingDocument): RedirectResponse
    {
        if (DocumentStorage::disk()->exists($supportingDocument->file_path)) {
            DocumentStorage::disk()->delete($supportingDocument->file_path);
        }
        $supportingDocument->delete();

        ActivityLogger::log('Ticketing', 'DELETE_DOCUMENT', "Removed supporting document '{$supportingDocument->original_name}' from {$ticket->booking_reference}");

        return redirect()->to(route('ticketing.tickets.show', $ticket).'#supporting-documents')
            ->with('success', 'Supporting document removed.');
    }
}
