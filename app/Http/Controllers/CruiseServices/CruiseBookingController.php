<?php

namespace App\Http\Controllers\CruiseServices;

use App\Http\Controllers\Controller;
use App\Models\CruiseBooking;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Cruise Services desk: the dashboard of bookings, and each booking worked step by step.
 * Save keeps what is filled in at the current step; Continue also needs everything the step asks
 * for (CruiseBooking::stageBlocker), which for Check Availability includes the slot being confirmed.
 */
class CruiseBookingController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $filter = in_array($request->input('slot'), ['waiting', 'confirmed'], true) ? $request->input('slot') : null;

        $bookings = CruiseBooking::query()
            ->when($filter === 'waiting', fn ($q) => $q->whereNull('slot_confirmed_at'))
            ->when($filter === 'confirmed', fn ($q) => $q->whereNotNull('slot_confirmed_at'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('client_name', 'like', $like)
                    ->orWhere('destination', 'like', $like)->orWhere('ship', 'like', $like)->orWhere('cruise_line', 'like', $like));
            })
            ->with('creator:id,name')
            ->orderByRaw('departure_date IS NULL')
            ->orderBy('departure_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('cruise-services.index', [
            'bookings' => $bookings,
            'search' => $search,
            'filter' => $filter,
            'counts' => [
                'all' => CruiseBooking::count(),
                'waiting' => CruiseBooking::whereNull('slot_confirmed_at')->count(),
                'confirmed' => CruiseBooking::whereNotNull('slot_confirmed_at')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('cruise-services.booking', ['booking' => new CruiseBooking(['status' => 'availability', 'adults' => 2, 'children' => 0, 'infants' => 0])]);
    }

    /** The registered client accounts matching what is typed in Client name: name, email, phone or passport. */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = User::clientSearch($term)->orderBy('name')->limit(8)->get(['id', 'name', 'email', 'phone']);

        return response()->json(['clients' => $clients->map(fn (User $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'detail' => collect([$client->phone, $client->email])->filter()->implode(' · '),
        ])->values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $booking = new CruiseBooking(['reference' => CruiseBooking::generateReference(), 'status' => 'availability']);
        $booking->created_by = $request->user()->id;

        return $this->save($request, $booking);
    }

    public function show(Request $request, CruiseBooking $booking): View
    {
        $step = (string) $request->query('step', $booking->status);
        if (! array_key_exists($step, CruiseBooking::STAGES) || ! $booking->hasReached($step)) {
            $step = (string) $booking->status;
        }

        return view('cruise-services.booking', [
            'booking' => $booking->load('creator:id,name', 'slotConfirmedBy:id,name'),
            'step' => $step,
        ]);
    }

    /** Step 1, Check Availability. */
    public function update(Request $request, CruiseBooking $booking): RedirectResponse
    {
        return $this->save($request, $booking);
    }

    /**
     * Step 2, Prepare the Quotation: the cost and the day-by-day itinerary. "Save and print" also
     * marks the quotation prepared (it then shows on the Quotations page) and opens it to print.
     */
    public function updateQuotation(Request $request, CruiseBooking $booking): RedirectResponse
    {
        if (! $booking->hasReached('quotation') || ! $booking->isSlotConfirmed()) {
            return redirect()->route('cruise-services.bookings.show', $booking)->with('error', 'Confirm the slot at Check Availability first.');
        }

        $printing = $request->input('action') === 'print';

        // Empty add-on rows (the form's spare ones) are not add-ons.
        $request->merge(['add_ons' => collect((array) $request->input('add_ons', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['description'] ?? null) || (float) ($row['amount'] ?? 0) > 0))
            ->values()->all()]);

        $amount = ['nullable', 'numeric', 'min:0', 'max:99999999.99'];
        $validated = $request->validate([
            'currency' => ['required', Rule::in(array_keys(CruiseBooking::CURRENCIES))],
            'base_fare' => [$printing ? 'required' : 'nullable', 'numeric', $printing ? 'gt:0' : 'min:0', 'max:99999999.99'],
            'tipping' => $amount,
            'insurance' => $amount,
            'travel_taxes' => $amount,
            'add_ons' => ['nullable', 'array', 'max:30'],
            'add_ons.*.description' => ['required', 'string', 'max:255'],
            'add_ons.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'itinerary' => ['nullable', 'array', 'max:60'],
            'itinerary.*.date' => ['nullable', 'date'],
            'itinerary.*.description' => ['nullable', 'string', 'max:1000'],
        ], [
            'base_fare.required' => 'Enter the base fare before printing the quotation.',
            'base_fare.gt' => 'Enter the base fare before printing the quotation.',
            'add_ons.*.description.required' => 'Say what each add-on is.',
            'add_ons.*.amount.required' => "Enter each add-on's amount.",
        ]);

        foreach (array_keys(CruiseBooking::COST_LINES) as $line) {
            $booking->{$line} = (float) ($validated[$line] ?? 0);
        }
        $booking->currency = $validated['currency'];
        $booking->add_ons = collect($validated['add_ons'] ?? [])
            ->map(fn (array $row) => ['description' => trim($row['description']), 'amount' => round((float) $row['amount'], 2)])->values()->all();
        // The days are numbered in the order they are listed.
        $booking->itinerary = collect($validated['itinerary'] ?? [])->values()
            ->map(fn (array $row, int $i) => ['day' => $i + 1, 'date' => $row['date'] ?? null, 'description' => trim((string) ($row['description'] ?? ''))])->all();
        $booking->total_amount = $booking->computedTotal();

        if ($printing) {
            $booking->quotation_at ??= now();
            $booking->quotation_by ??= $request->user()->id;
        }
        $booking->save();

        ActivityLogger::log('Cruise Services', $printing ? 'QUOTATION' : 'UPDATE', ($printing ? 'Prepared the quotation for ' : 'Saved the quotation of ')."{$booking->reference}: ".$booking->money($booking->total_amount));

        return $printing
            ? redirect()->route('cruise-services.bookings.quotation', ['booking' => $booking, 'autoprint' => 1])
            : redirect()->route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'quotation'])->with('success', 'The quotation is saved.');
    }

    /** The quotation, to print or save as PDF for the client. */
    public function quotation(CruiseBooking $booking): View|RedirectResponse
    {
        if (! $booking->hasQuotation()) {
            return redirect()->route('cruise-services.bookings.show', ['booking' => $booking, 'step' => $booking->hasReached('quotation') ? 'quotation' : 'availability'])
                ->with('error', 'Prepare the quotation and use Save and print quotation first.');
        }

        return view('cruise-services.quotation-print', ['booking' => $booking->load('creator:id,name', 'quotationBy:id,name')]);
    }

    /** Every quotation prepared, newest first. */
    public function quotations(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $quotations = CruiseBooking::query()
            ->whereNotNull('quotation_at')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('client_name', 'like', $like)->orWhere('destination', 'like', $like));
            })
            ->with('quotationBy:id,name')
            ->latest('quotation_at')
            ->paginate(20)
            ->withQueryString();

        return view('cruise-services.quotations', ['quotations' => $quotations, 'search' => $search]);
    }

    /** Keep what was filled in; on Continue, also check the step is complete and move on. */
    private function save(Request $request, CruiseBooking $booking): RedirectResponse
    {
        $continuing = $request->input('action') === 'continue';

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
            'client_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'destination' => [$continuing ? 'required' : 'nullable', 'string', 'max:255'],
            'departure_date' => [$continuing ? 'required' : 'nullable', 'date', ...($booking->exists ? [] : ['after_or_equal:today'])],
            'return_date' => [$continuing ? 'required' : 'nullable', 'date', 'after_or_equal:departure_date'],
            'adults' => [$continuing ? 'required' : 'nullable', 'integer', 'min:1', 'max:500'],
            'children' => ['nullable', 'integer', 'min:0', 'max:500'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:500'],
            'cruise_line' => ['nullable', 'string', 'max:100'],
            'ship' => ['nullable', 'string', 'max:100'],
            'slot_confirmed' => [$continuing ? 'accepted' : 'nullable', 'boolean'],
        ], [
            'client_id.exists' => 'Choose the client again from the search.',
            'client_name.required' => "Enter the client's name.",
            'destination.required' => 'Enter the destination.',
            'departure_date.required' => 'Enter the sailing date.',
            'departure_date.after_or_equal' => 'The sailing date cannot be in the past.',
            'return_date.required' => 'Enter the return date.',
            'return_date.after_or_equal' => 'The return date cannot be before the sailing date.',
            'adults.required' => 'Enter the number of adults.',
            'adults.min' => 'There must be at least one adult.',
            'slot_confirmed.accepted' => 'Confirm the slot before continuing.',
        ]);

        $booking->fill(collect($validated)->except(['slot_confirmed', 'client_id'])->all());
        $booking->children = (int) ($validated['children'] ?? 0);
        $booking->infants = (int) ($validated['infants'] ?? 0);

        // Ticking the box records who confirmed the slot and when; clearing it takes the confirmation off.
        if ($request->boolean('slot_confirmed') && ! $booking->isSlotConfirmed()) {
            $booking->slot_confirmed_at = now();
            $booking->slot_confirmed_by = $request->user()->id;
        } elseif (! $request->boolean('slot_confirmed')) {
            $booking->slot_confirmed_at = null;
            $booking->slot_confirmed_by = null;
        }

        $new = ! $booking->exists;
        // A client picked from the search is that account; a name typed in is added to the client list.
        if (filled($validated['client_id'] ?? null)) {
            $booking->user_id = (int) $validated['client_id'];
        }
        $booking->save();
        if (blank($validated['client_id'] ?? null)) {
            $this->rememberClient($booking);
        }
        ActivityLogger::log('Cruise Services', $new ? 'CREATE' : 'UPDATE', ($new ? 'Opened' : 'Updated')." cruise booking {$booking->reference} for {$booking->client_name}");

        // Without a confirmed slot the quotation cannot be worked on: the booking goes back to step 1.
        if (! $booking->isSlotConfirmed() && $booking->status !== 'availability') {
            $booking->forceFill(['status' => 'availability'])->save();
            ActivityLogger::log('Cruise Services', 'UPDATE', "{$booking->reference}: slot no longer confirmed, back to Check Availability");
        }

        if (! $continuing) {
            return redirect()->route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'availability'])->with('success', "{$booking->reference} is saved.");
        }

        if ($blocker = $booking->blockerFor('availability')) {
            return redirect()->route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'availability'])->with('error', $blocker);
        }

        if ($booking->status === 'availability') {
            $booking->forceFill(['status' => 'quotation'])->save();
            ActivityLogger::log('Cruise Services', 'ADVANCE', "{$booking->reference}: Check Availability → Prepare the Quotation");
        }

        return redirect()->route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'quotation'])
            ->with('success', 'The slot is confirmed. Prepare the quotation.');
    }

    /** Keep the client on the client list, and the booking on their record. */
    private function rememberClient(CruiseBooking $booking): void
    {
        $client = ClientAccountService::findOrCreateClient([
            'name' => $booking->client_name,
            'phone' => $booking->contact_number,
        ]);

        if ($booking->user_id !== $client->id) {
            $booking->forceFill(['user_id' => $client->id])->save();
        }
    }
}
