<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationPricingTier;
use App\Models\ImmigrationQuotation;
use App\Models\ImmigrationQuotationItem;
use App\Models\User;
use App\Notifications\ImmigrationQuotationNotification;
use App\Services\ActivityLogger;
use App\Services\ClientNotifier;
use App\Services\ImmigrationQuotationPdf;
use App\Support\ImmigrationServices;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quotations at the Immigration counter: price the services a client asks about (from the BI rates, plus
 * AMEGA's fees), print or email it, and once they agree open each service's file with the quoted fee.
 */
class ImmigrationQuotationController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_ITEMS = 30;

    /** BI rate categories => the counter service a line from them opens. */
    private const CATEGORY_SERVICE = [
        'visa-extension' => 'visa_extension',
        'hongkong-9g' => 'visa_extension',
        'exit-clearance' => 'ecc',
        're-stamping' => 're_stamping_amendment',
    ];

    public function index(Request $request): View
    {
        $status = (string) $request->input('status', '');
        $term = trim((string) $request->input('q'));

        $quotations = ImmigrationQuotation::query()
            ->with('createdBy:id,name')
            ->withCount('items')
            ->when($status !== '', fn ($q) => $q->showing($status))
            ->when($term !== '', fn ($q) => $q->search($term))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.immigration.quotations.index', compact('quotations', 'status', 'term'));
    }

    public function create(Request $request): View
    {
        $quotation = new ImmigrationQuotation(['valid_until' => today()->addDays(ImmigrationQuotation::DEFAULT_VALID_DAYS)]);

        // Started from a client's folder: fill in who it is for.
        if ($request->filled('folder') && $folder = ImmigrationClient::find($request->integer('folder'))) {
            $quotation->fill($this->fromFolder($folder));
        }

        return view('admin.immigration.quotations.create', ['quotation' => $quotation] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);

        $quotation = DB::transaction(function () use ($data, $items, $request) {
            $quotation = ImmigrationQuotation::create($data + ['status' => ImmigrationQuotation::STATUS_OPEN, 'created_by' => $request->user()->id]);
            $this->saveItems($quotation, $items);

            return $quotation;
        });

        ActivityLogger::log('Immigration', 'QUOTE', "Created quotation {$quotation->quote_number} for {$quotation->client_name} (₱".number_format((float) $quotation->total, 2).')');

        return redirect()->route('admin.immigration.quotations.show', $quotation)->with('success', "Quotation {$quotation->quote_number} saved.");
    }

    public function show(ImmigrationQuotation $quotation): View
    {
        $quotation->load(['items', 'createdBy:id,name', 'immigrationClient']);

        return view('admin.immigration.quotations.show', compact('quotation'));
    }

    public function edit(ImmigrationQuotation $quotation): View|RedirectResponse
    {
        if (! $quotation->isOpen()) {
            return redirect()->route('admin.immigration.quotations.show', $quotation)->with('error', 'Only an open quotation can be edited. Reopen it first.');
        }

        return view('admin.immigration.quotations.edit', ['quotation' => $quotation->load('items')] + $this->formData());
    }

    public function update(Request $request, ImmigrationQuotation $quotation): RedirectResponse
    {
        if (! $quotation->isOpen()) {
            return redirect()->route('admin.immigration.quotations.show', $quotation)->with('error', 'Only an open quotation can be edited. Reopen it first.');
        }

        [$data, $items] = $this->validated($request, $quotation);

        DB::transaction(function () use ($quotation, $data, $items) {
            $quotation->update($data);
            $quotation->items()->delete();
            $this->saveItems($quotation, $items);
        });

        ActivityLogger::log('Immigration', 'QUOTE_UPDATE', "Updated quotation {$quotation->quote_number} for {$quotation->client_name} (₱".number_format((float) $quotation->total, 2).')');

        return redirect()->route('admin.immigration.quotations.show', $quotation)->with('success', "Quotation {$quotation->quote_number} updated.");
    }

    /** Admins only (DeletesAreForAdmins). */
    public function destroy(ImmigrationQuotation $quotation): RedirectResponse
    {
        $number = $quotation->quote_number;
        $quotation->delete();

        ActivityLogger::log('Immigration', 'QUOTE_DELETE', "Deleted quotation {$number}");

        return redirect()->route('admin.immigration.quotations.index')->with('success', "Quotation {$number} deleted.");
    }

    public function pdf(ImmigrationQuotation $quotation): Response
    {
        return response(ImmigrationQuotationPdf::render($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.ImmigrationQuotationPdf::filename($quotation).'"',
        ]);
    }

    public function email(Request $request, ImmigrationQuotation $quotation): RedirectResponse
    {
        if (! ClientNotifier::canReceive($quotation->client_email)) {
            return back()->with('error', 'This quotation has no email address. Add one (Edit), or print the PDF and hand it over.');
        }

        if (! ClientNotifier::send($quotation->client_email, $quotation->client_name, new ImmigrationQuotationNotification($quotation, $request->user()->name))) {
            return back()->with('error', "The quotation could not be emailed to {$quotation->client_email}. Please try again.");
        }

        $quotation->forceFill(['emailed_at' => now()])->save();
        ActivityLogger::log('Immigration', 'QUOTE_EMAIL', "Emailed quotation {$quotation->quote_number} to {$quotation->client_email}");

        return back()->with('success', "Quotation {$quotation->quote_number} was emailed to {$quotation->client_email} with the PDF attached.");
    }

    /** Accepted, declined, or back to open. */
    public function status(Request $request, ImmigrationQuotation $quotation): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in([ImmigrationQuotation::STATUS_OPEN, ImmigrationQuotation::STATUS_ACCEPTED, ImmigrationQuotation::STATUS_DECLINED])]])['status'];

        $quotation->forceFill([
            'status' => $status,
            'accepted_at' => $status === ImmigrationQuotation::STATUS_ACCEPTED ? ($quotation->accepted_at ?? now()) : null,
            'declined_at' => $status === ImmigrationQuotation::STATUS_DECLINED ? now() : null,
        ])->save();

        ActivityLogger::log('Immigration', 'QUOTE_STATUS', "Marked quotation {$quotation->quote_number} as {$status}");

        $message = match ($status) {
            ImmigrationQuotation::STATUS_ACCEPTED => "Quotation {$quotation->quote_number} accepted. Open each service's file below.",
            ImmigrationQuotation::STATUS_DECLINED => "Quotation {$quotation->quote_number} marked declined.",
            default => "Quotation {$quotation->quote_number} is open again.",
        };

        return back()->with('success', $message);
    }

    /**
     * The client agreed: open the line's service file in their folder (or start one), with the quoted fee filled in.
     * The quotation counts as accepted from here.
     */
    public function start(Request $request, ImmigrationQuotation $quotation, ImmigrationQuotationItem $item): RedirectResponse
    {
        if ($quotation->status === ImmigrationQuotation::STATUS_DECLINED) {
            return back()->with('error', 'This quotation was declined. Reopen it before opening a file from it.');
        }

        if (! $item->opensFile()) {
            return back()->with('error', 'This line is not a counter service, so it has no file to open.');
        }

        if ($quotation->status !== ImmigrationQuotation::STATUS_ACCEPTED) {
            $quotation->forceFill(['status' => ImmigrationQuotation::STATUS_ACCEPTED, 'accepted_at' => now()])->save();
            ActivityLogger::log('Immigration', 'QUOTE_STATUS', "Marked quotation {$quotation->quote_number} as accepted");
        }

        $params = ['service' => $item->service_key, 'quote_item' => $item->id];
        $folder = $quotation->immigration_client_id ? ImmigrationClient::find($quotation->immigration_client_id) : null;

        $url = match (true) {
            $folder !== null => route('admin.client-sheets.edit', ['client_sheet' => $folder->id] + $params),
            $quotation->user_id !== null => route('admin.client-sheets.create', ['client' => $quotation->user_id] + $params),
            default => route('admin.client-sheets.create', $params + array_filter(['name' => $quotation->client_name, 'passport' => $quotation->passport_number])),
        };

        $fee = $item->service_key === ImmigrationServices::DEFAULT ? '' : ' The quoted fee of ₱'.number_format((float) $item->amount, 2).' is filled in on the file.';

        return redirect()->to($url)->with('success', "Opening {$item->serviceLabel()} for {$quotation->client_name} from quotation {$quotation->quote_number}.{$fee}");
    }

    /** Type-ahead for "who is it for": client folders, then registered clients without one. */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $folders = ImmigrationClient::search($term)->orderBy('last_name')->orderBy('given_name')->limit(8)->get()
            ->map(fn (ImmigrationClient $folder) => ['kind' => 'folder', 'id' => $folder->id] + $this->fromFolder($folder));

        $clients = User::clientSearch($term)->whereDoesntHave('immigrationClients')->orderBy('name')->limit(8)->get()
            ->map(fn (User $client) => [
                'kind' => 'client',
                'id' => $client->id,
                'immigration_client_id' => null,
                'user_id' => $client->id,
                'client_name' => $client->full_name,
                'client_email' => $client->email,
                'client_phone' => $client->phone,
                'nationality' => $client->nationality,
                'passport_number' => $client->passport_number,
            ]);

        return response()->json($folders->concat($clients)->values());
    }

    /** @return array<string, mixed> */
    private function fromFolder(ImmigrationClient $folder): array
    {
        return [
            'immigration_client_id' => $folder->id,
            'user_id' => $folder->user_id,
            'client_name' => $folder->full_name,
            'client_email' => $folder->email,
            'client_phone' => $folder->mobile_number,
            'nationality' => $folder->nationality,
            'passport_number' => $folder->passport_number,
        ];
    }

    /** The services and BI rates the form offers. */
    private function formData(): array
    {
        $services = collect(ImmigrationServices::all())->where('available', true)->map(fn (array $s) => ['key' => $s['key'], 'label' => $s['label']])->values();

        $rates = ImmigrationPricingTier::with('category:id,slug,name')
            ->where('is_active', true)
            ->orderBy('immigration_category_id')->orderBy('sort_order')
            ->get()
            ->map(fn (ImmigrationPricingTier $tier) => [
                'id' => $tier->id,
                'group' => $tier->category?->name ?? 'BI rates',
                'label' => collect([$tier->category?->name, $tier->extension_label, $tier->duration_label, $tier->process_type ? ucfirst($tier->process_type) : null, $tier->payment_method ? ucfirst($tier->payment_method) : null])->filter()->implode(' - '),
                'price' => (float) $tier->price,
                'service' => self::CATEGORY_SERVICE[$tier->category?->slug] ?? null,
                'check' => (bool) $tier->needs_review,
            ])
            ->values();

        return ['services' => $services, 'rates' => $rates];
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function validated(Request $request, ?ImmigrationQuotation $quotation = null): array
    {
        $serviceKeys = collect(ImmigrationServices::all())->where('available', true)->pluck('key')->all();

        $data = $request->validate([
            'immigration_client_id' => ['nullable', 'integer', Rule::exists('immigration_clients', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'valid_until' => ['required', 'date', $quotation ? 'after_or_equal:'.$quotation->created_at->toDateString() : 'after_or_equal:today'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.service_key' => ['nullable', 'string', Rule::in($serviceKeys)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'client_name.required' => 'Enter who the quotation is for.',
            'items.required' => 'Add at least one service or fee.',
            'items.min' => 'Add at least one service or fee.',
            'items.*.description.required' => 'Describe each line.',
            'items.*.unit_price.required' => 'Enter a price for each line.',
            'valid_until.after_or_equal' => 'The quotation cannot expire before it was made.',
        ]);

        // Typed in capitals on the form; stored that way whatever was sent. Email stays as typed.
        foreach (['client_name', 'nationality', 'passport_number', 'client_phone', 'notes'] as $key) {
            if (filled($data[$key] ?? null)) {
                $data[$key] = mb_strtoupper(trim($data[$key]));
            }
        }

        $items = array_map(fn (array $item) => ['description' => mb_strtoupper(trim($item['description']))] + $item, array_values($data['items']));
        unset($data['items']);

        $subtotal = collect($items)->sum(fn (array $item) => round((int) $item['quantity'] * (float) $item['unit_price'], 2));
        $data['discount'] = round((float) ($data['discount'] ?? 0), 2);

        if ($data['discount'] > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'The discount cannot be more than the subtotal (₱'.number_format($subtotal, 2).').']);
        }

        return [$data, $items];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function saveItems(ImmigrationQuotation $quotation, array $items): void
    {
        foreach ($items as $index => $item) {
            $quantity = (int) $item['quantity'];
            $price = round((float) $item['unit_price'], 2);

            $quotation->items()->create([
                'service_key' => filled($item['service_key'] ?? null) ? $item['service_key'] : null,
                'description' => trim($item['description']),
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => round($quantity * $price, 2),
                'sort_order' => $index,
            ]);
        }

        $quotation->recalculate();
    }
}
