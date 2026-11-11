<?php

namespace App\Http\Controllers\LocalTours;

use App\Http\Controllers\Controller;
use App\Models\LocalTourBooking;
use App\Models\LocalTourDocument;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientProfileService;
use App\Services\DeskApproval;
use App\Support\DocumentStorage;
use App\Support\LocalTours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Local Tours desk: a booking is opened for a client and a tour, then worked through the booking
 * procedure's steps (see LocalTourBooking). Each step's tasks are ticked and its details saved
 * (recordStage) before the booking moves on (advance). Leaving Guest Details sends it to an
 * approver; the cashier takes the payment; the voucher is released once it is paid in full.
 */
class LocalTourBookingController extends Controller
{
    /** The lists on the desk's front page. */
    private const VIEWS = ['open' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public function index(Request $request): View
    {
        $view = array_key_exists((string) $request->input('view'), self::VIEWS) ? (string) $request->input('view') : 'open';
        $tour = array_key_exists((string) $request->input('tour'), LocalTours::TOURS) ? (string) $request->input('tour') : null;
        $search = trim((string) $request->input('search'));

        $bookings = LocalTourBooking::query()
            ->when($view === 'open', fn ($q) => $q->whereNotIn('status', LocalTourBooking::FINISHED_STATUSES), fn ($q) => $q->where('status', $view))
            ->when($tour, fn ($q) => $q->where('tour', $tour))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('lead_guest_name', 'like', $like)->orWhere('contact_phone', 'like', $like));
            })
            ->with('creator:id,name')
            // Held by the approver first, then the soonest travel date.
            ->orderHeldFirst("status IN ('completed', 'cancelled')")
            ->orderByRaw('travel_date IS NULL')
            ->orderBy('travel_date')
            ->paginate(20)
            ->withQueryString();

        // One card per tour: what is in progress on it, and how many of those wait on an approver.
        $tours = collect(LocalTours::TOURS)->map(fn (array $info, string $key): array => [
            'key' => $key,
            'label' => $info['label'],
            'icon' => $info['icon'],
            'supplier' => $info['supplier'],
            'open' => LocalTourBooking::where('tour', $key)->whereNotIn('status', LocalTourBooking::FINISHED_STATUSES)->count(),
            'waiting' => LocalTourBooking::where('tour', $key)->whereNotIn('status', LocalTourBooking::FINISHED_STATUSES)->held()->count(),
        ])->values();

        return view('local-tours.index', [
            'bookings' => $bookings,
            'tours' => $tours,
            'views' => self::VIEWS,
            'view' => $view,
            'tour' => $tour,
            'search' => $search,
        ]);
    }

    /** The procedure itself: every tour's supplier and steps, the drivers and the reminders. */
    public function procedures(): View
    {
        return view('local-tours.procedures');
    }

    public function create(Request $request): View
    {
        $tour = array_key_exists((string) $request->query('tour'), LocalTours::TOURS) ? (string) $request->query('tour') : null;

        return view('local-tours.create', ['booking' => new LocalTourBooking(['tour' => $tour, 'adults' => 1, 'children' => 0, 'transport' => 'amega'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $booking = new LocalTourBooking($this->validated($request) + [
            'reference' => LocalTourBooking::generateReference(),
            'status' => 'availability',
        ]);
        $booking->created_by = $request->user()->id;
        $booking->save();

        $this->rememberClient($booking);
        ActivityLogger::log('Local Tours', 'CREATE', "Opened {$booking->tourLabel()} booking {$booking->reference} for {$booking->lead_guest_name}");

        return redirect()->route('local-tours.bookings.show', $booking)->with('success', "{$booking->reference} is opened. Start with the supplier's availability and rates.");
    }

    public function show(LocalTourBooking $booking): View
    {
        return view('local-tours.show', [
            'booking' => $booking->load(['creator:id,name', 'reviewedBy:id,name', 'documents', 'payments.receivedBy:id,name']),
        ]);
    }

    public function edit(LocalTourBooking $booking): View|RedirectResponse
    {
        if ($booking->isFinal()) {
            return redirect()->route('local-tours.bookings.show', $booking)->with('error', "This booking is {$booking->status_label}, so its details can no longer be changed.");
        }

        return view('local-tours.edit', ['booking' => $booking]);
    }

    public function update(Request $request, LocalTourBooking $booking): RedirectResponse
    {
        if ($booking->isFinal()) {
            return redirect()->route('local-tours.bookings.show', $booking)->with('error', "This booking is {$booking->status_label}, so its details can no longer be changed.");
        }

        $validated = $this->validated($request, $booking);
        if ((float) $validated['total_amount'] < (float) $booking->amount_paid) {
            return back()->withInput()->withErrors(['total_amount' => 'The amount quoted cannot be less than the ₱'.number_format((float) $booking->amount_paid, 2).' already paid.']);
        }

        $booking->fill($validated);
        // Only what the approver signed off on sends it back to them.
        $changed = $booking->isDirty(LocalTourBooking::APPROVED_DETAILS);
        $booking->save();

        // Once it has been sent to an approver, a change to what they checked goes back to them.
        if ($booking->approval_status !== null) {
            DeskApproval::saved($booking, $request->user(), 'Local Tours', $booking->lead_guest_name, 'admin.local-tour-approvals.index', $changed);
        }

        $this->rememberClient($booking);
        ActivityLogger::log('Local Tours', 'UPDATE', "Updated {$booking->reference}");

        return redirect()->route('local-tours.bookings.show', $booking)
            ->with('success', "{$booking->reference} is saved.".($booking->isAwaitingApproval() ? ' It is with an approver.' : ''));
    }

    /** Save the work of the current step: the tasks ticked off and the step's own details. */
    public function recordStage(Request $request, LocalTourBooking $booking): RedirectResponse
    {
        if ($refusal = $this->save($request, $booking)) {
            return back()->withInput()->with('error', $refusal);
        }

        return back()->with('success', "{$booking->status_label}: saved.");
    }

    /** Save the current step, then move the booking on if everything the step needs is done. */
    public function advance(Request $request, LocalTourBooking $booking): RedirectResponse
    {
        if ($refusal = $this->save($request, $booking)) {
            return back()->withInput()->with('error', $refusal);
        }

        $next = $booking->nextStage();
        if ($next === null) {
            return back()->with('error', 'This booking has no further step.');
        }
        if ($blocker = $booking->stageBlocker()) {
            return back()->with('error', $blocker);
        }

        $from = $booking->status;
        $fromLabel = $booking->status_label;
        $booking->status = $next;
        if ($next === 'completed') {
            $booking->completed_at = now();
        }
        $booking->save();

        ActivityLogger::log('Local Tours', 'ADVANCE', "{$booking->reference}: {$fromLabel} → {$booking->status_label}");

        // Before anything is submitted or paid to the supplier, an approver checks the booking.
        if ($from === 'guest_details') {
            DeskApproval::saved($booking, $request->user(), 'Local Tours', $booking->lead_guest_name, 'admin.local-tour-approvals.index');

            return back()->with('success', $booking->isAwaitingApproval()
                ? "{$booking->reference} is sent for approval. Book with the supplier once it is approved."
                : "{$booking->reference} is approved. Book with the supplier next.");
        }

        return back()->with('success', $next === 'completed' ? "{$booking->reference} is completed." : "{$booking->reference} is now at {$booking->status_label}.");
    }

    public function cancel(Request $request, LocalTourBooking $booking): RedirectResponse
    {
        if ($booking->isFinal()) {
            return back()->with('error', 'This booking is already closed.');
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why the booking is cancelled.']);

        $booking->forceFill([
            'status' => 'cancelled',
            'remarks' => trim(($booking->remarks ? $booking->remarks."\n" : '').'Cancelled: '.trim($validated['reason'])),
        ])->save();
        ActivityLogger::log('Local Tours', 'CANCEL', "Cancelled {$booking->reference}: ".trim($validated['reason']));

        return back()->with('success', "{$booking->reference} is cancelled.".((float) $booking->amount_paid > 0 ? ' It has payments on it: arrange the refund with the cashier.' : ''));
    }

    /** Permanent delete: admins only (DeletesAreForAdmins). */
    public function destroy(LocalTourBooking $booking): RedirectResponse
    {
        if ((float) $booking->amount_paid > 0) {
            return back()->with('error', 'This booking has payments recorded on it, so it cannot be deleted. Cancel it instead.');
        }

        $reference = $booking->reference;
        foreach ($booking->documents as $document) {
            DocumentStorage::disk()->delete($document->file_path);
        }
        $booking->delete();

        ActivityLogger::log('Local Tours', 'DELETE', "Deleted {$reference}");

        return redirect()->route('local-tours.dashboard')->with('success', "{$reference} deleted.");
    }

    /** The AMEGA booking voucher/order, to print for the client. */
    public function voucher(LocalTourBooking $booking): View|RedirectResponse
    {
        if ($blocker = $booking->voucherBlocker()) {
            return redirect()->route('local-tours.bookings.show', $booking)->with('error', $blocker);
        }

        return view('local-tours.voucher', ['booking' => $booking->load('creator:id,name')]);
    }

    /** File a scan on the booking: a guest's ID, the supplier's confirmation, proof of payment. */
    public function storeDocument(Request $request, LocalTourBooking $booking): RedirectResponse
    {
        $request->validate([
            'kind' => ['required', Rule::in(array_keys(LocalTourDocument::KINDS))],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => explode('|', ClientProfileService::SCAN_RULE),
        ], [
            'files.required' => 'Choose at least one file.',
            'files.*.mimes' => 'Upload a PDF, JPG, PNG or WEBP file.',
            'files.*.max' => 'Each file can be up to 5 MB.',
        ]);

        foreach ($request->file('files') as $upload) {
            $booking->documents()->create([
                'kind' => $request->input('kind'),
                'file_path' => $upload->store("local-tours/{$booking->reference}", DocumentStorage::diskName()),
                'original_name' => $upload->getClientOriginalName(),
                'file_size' => $upload->getSize(),
                'mime_type' => $upload->getClientMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
            ActivityLogger::log('Local Tours', 'UPLOAD_DOCUMENT', "Filed {$upload->getClientOriginalName()} ({$request->input('kind')}) on {$booking->reference}");
        }

        return back()->with('success', 'Filed on the booking.');
    }

    /** Download a filed scan: only from a booking this person can open (OwnFilesScope). */
    public function downloadDocument(LocalTourDocument $document): StreamedResponse|RedirectResponse
    {
        abort_unless(LocalTourBooking::whereKey($document->local_tour_booking_id)->exists(), 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The document could not be found.');
        }

        return DocumentStorage::disk()->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    /** Take off a scan uploaded by mistake (a correction, not deleting the booking). */
    public function destroyDocument(LocalTourDocument $document): RedirectResponse
    {
        $booking = LocalTourBooking::find($document->local_tour_booking_id) ?? abort(404);

        DocumentStorage::disk()->delete($document->file_path);
        $document->delete();
        ActivityLogger::log('Local Tours', 'REMOVE_DOCUMENT', "Removed {$document->original_name} from {$booking->reference}");

        return back()->with('success', "{$document->original_name} is removed.");
    }

    /**
     * Keep what was ticked and filled in at the booking's current step. Returns why it could not be
     * saved, or null once it is.
     */
    private function save(Request $request, LocalTourBooking $booking): ?string
    {
        if ($booking->isFinal()) {
            return 'This booking is '.strtolower($booking->status_label).'.';
        }

        $stage = (string) $booking->status;
        // From the supplier booking on, nothing is done while an approver holds the booking.
        if (in_array($stage, ['supplier', 'voucher', 'transport'], true) && ($held = $booking->approvalBlocker('worked on'))) {
            return $held;
        }

        $tasks = $booking->tasks();
        $validated = $request->validate([
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['string', Rule::in(array_keys($tasks))],
        ] + match ($stage) {
            'availability' => [
                'total_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'supplier_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            ],
            'supplier' => [
                'supplier_confirmation' => ['nullable', 'string', 'max:100'],
                'supplier_paid_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'supplier_payment_reference' => ['nullable', 'string', 'max:100'],
            ],
            'transport' => [
                'driver' => ['nullable', Rule::in(array_keys(LocalTours::DRIVERS))],
                'vehicle' => ['nullable', 'string', 'max:100'],
                'pickup_time' => ['nullable', 'string', 'max:20'],
            ],
            default => [],
        });

        $ticked = array_values(array_intersect(array_keys($tasks), $validated['tasks'] ?? []));

        // The voucher goes to the client only once the booking is approved and paid in full.
        if (in_array('voucher_released', $ticked, true) && ! $booking->hasTicked('voucher_released') && ($blocker = $booking->voucherBlocker())) {
            return $blocker;
        }

        $fields = collect($validated)->except('tasks')->all();
        if (array_key_exists('total_amount', $fields)) {
            $fields['total_amount'] = (float) ($fields['total_amount'] ?? 0);
        }

        // The other steps' ticks stay as they are; this step's are replaced by what was sent.
        $booking->fill($fields + [
            'checklist' => array_values(array_unique([...array_diff($booking->checklist ?? [], array_keys($tasks)), ...$ticked])),
        ]);
        if (in_array('agreement_secured', $ticked, true)) {
            $booking->agreement_at ??= now();
        }
        if (in_array('voucher_released', $ticked, true)) {
            $booking->voucher_released_at ??= now();
        }
        $booking->save();

        return null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?LocalTourBooking $booking = null): array
    {
        $validated = $request->validate([
            // The tour is fixed once the booking is with an approver or the supplier.
            'tour' => ['required', Rule::in($booking && $booking->hasReached('supplier') ? [$booking->tour] : array_keys(LocalTours::TOURS))],
            'lead_guest_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'adults' => ['required', 'integer', 'min:1', 'max:500'],
            'children' => ['nullable', 'integer', 'min:0', 'max:500'],
            'children_ages' => ['nullable', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date', $booking ? 'date' : 'after_or_equal:today'],
            'pickup_time' => ['nullable', 'string', 'max:20'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
            'attractions' => ['nullable', 'string', 'max:2000'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'transport' => ['required', Rule::in(array_keys(LocalTours::TRANSPORT))],
            'total_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'supplier_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'tour.required' => 'Choose the tour.',
            'tour.in' => $booking ? 'The tour cannot be changed once the booking has gone for approval. Cancel it and open a new one.' : 'Choose the tour.',
            'lead_guest_name.required' => "Enter the lead guest's complete name.",
            'adults.min' => 'There must be at least one adult.',
            'travel_date.after_or_equal' => 'The travel date cannot be in the past.',
        ]);

        $validated['lead_guest_name'] = trim($validated['lead_guest_name']);
        $validated['children'] = (int) ($validated['children'] ?? 0);
        $validated['total_amount'] = (float) ($validated['total_amount'] ?? 0);

        return $validated;
    }

    /** Keep the lead guest on the client list, and the booking on their record. */
    private function rememberClient(LocalTourBooking $booking): void
    {
        $client = ClientAccountService::findOrCreateClient([
            'name' => $booking->lead_guest_name,
            'email' => $booking->contact_email,
            'phone' => $booking->contact_phone,
            'nationality' => $booking->nationality,
        ]);

        if ($booking->user_id !== $client->id) {
            $booking->forceFill(['user_id' => $client->id])->save();
        }
    }
}
