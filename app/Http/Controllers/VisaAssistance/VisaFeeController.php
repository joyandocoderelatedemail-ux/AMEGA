<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\VisaPricingTier;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Visa Assistance Counter's fee schedule on its own page. Everyone at the counter reads it;
 * adding or changing a fee takes "Manage prices" (the routes), and deleting one is admin-only.
 */
class VisaFeeController extends Controller
{
    /** The services that are priced, as the counter names them. */
    public const SERVICES = [
        'visit_visa' => 'Non-Immigrant',
        'e_visa' => 'e-Visa',
        'passporting' => 'Passporting',
    ];

    public function index(Request $request): View
    {
        $fees = VisaPricingTier::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('service_type');

        return view('visa-assistance.fees', [
            'groups' => collect(self::SERVICES)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'fees' => $fees->get($key, collect()),
            ])->values(),
            'canManage' => (bool) $request->user()?->hasPermission('prices.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $fee = VisaPricingTier::create($data);

        ActivityLogger::log('Visa Fees', 'CREATE', "Added the fee '{$this->describe($fee)}'");

        return redirect()->route('visa.fees.index')->with('success', 'Fee added.');
    }

    public function update(Request $request, VisaPricingTier $fee): RedirectResponse
    {
        $before = $this->describe($fee);
        $fee->update($this->validated($request, false));

        ActivityLogger::log('Visa Fees', 'UPDATE', "Changed the fee '{$before}' to '{$this->describe($fee)}'");

        return redirect()->route('visa.fees.index')->with('success', 'Fee saved.');
    }

    public function destroy(VisaPricingTier $fee): RedirectResponse
    {
        $description = $this->describe($fee);
        $fee->delete();

        ActivityLogger::log('Visa Fees', 'DELETE', "Deleted the fee '{$description}'");

        return redirect()->route('visa.fees.index')->with('success', 'Fee deleted.');
    }

    /**
     * The row as saved. When the service fee or visa fee is filled in, the price is their sum,
     * as on the fee breakdown; otherwise it is the price keyed in.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'service_type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(self::SERVICES))],
            'label' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'service_fee' => 'nullable|numeric|min:0',
            'visa_fee' => 'nullable|numeric|min:0',
            'insurance_fee' => 'nullable|numeric|min:0',
            'amount' => 'required_without_all:service_fee,visa_fee|nullable|numeric|min:0',
            'inclusions' => 'nullable|string|max:255',
            'processing_time' => 'nullable|string|max:255',
            'condition_notes' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->filled('service_fee') || $request->filled('visa_fee')) {
            $data['amount'] = (float) ($data['service_fee'] ?? 0) + (float) ($data['visa_fee'] ?? 0);
        }

        $data['amount'] = (float) ($data['amount'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        // "Confirmed" on the page: an unconfirmed fee stays off the counter's price list.
        $data['needs_review'] = ! $request->boolean('confirmed');

        return $data;
    }

    private function describe(VisaPricingTier $fee): string
    {
        return implode(' - ', array_filter([
            self::SERVICES[$fee->service_type] ?? $fee->service_type,
            $fee->label,
            'PHP '.number_format((float) $fee->amount, 2),
            $fee->is_active ? null : 'hidden',
            $fee->needs_review ? 'not confirmed' : null,
        ]));
    }
}
