<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCategory;
use App\Models\ImmigrationPricingTier;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminImmigrationPricingController extends Controller
{
    public function index(Request $request)
    {
        $categories = ImmigrationCategory::orderBy('sort_order', 'asc')->get();

        $tiers = ImmigrationPricingTier::with('category')
            ->when($request->filled('category'), fn ($query) => $query->where('immigration_category_id', $request->integer('category')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->when($request->boolean('needs_review'), fn ($query) => $query->where('needs_review', true))
            ->orderBy('immigration_category_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->get();

        $needsReviewCount = ImmigrationPricingTier::where('needs_review', true)->count();

        return view('admin.immigration-pricing.index', compact('categories', 'tiers', 'needsReviewCount'));
    }

    public function create()
    {
        $categories = ImmigrationCategory::orderBy('sort_order', 'asc')->get();

        return view('admin.immigration-pricing.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTier($request);

        $validated['needs_review'] = $request->has('needs_review');
        $validated['is_active'] = $request->has('is_active');

        $tier = ImmigrationPricingTier::create($validated);

        ActivityLogger::log('Immigration Pricing', 'CREATE', "Created price row '{$this->describe($tier)}'");

        return redirect()->route('admin.immigration-pricing.index')->with('success', 'Price row created successfully!');
    }

    public function edit(ImmigrationPricingTier $immigrationPricing)
    {
        $categories = ImmigrationCategory::orderBy('sort_order', 'asc')->get();

        return view('admin.immigration-pricing.edit', ['tier' => $immigrationPricing, 'categories' => $categories]);
    }

    public function update(Request $request, ImmigrationPricingTier $immigrationPricing)
    {
        $validated = $this->validateTier($request);

        $validated['needs_review'] = $request->has('needs_review');
        $validated['is_active'] = $request->has('is_active');

        $immigrationPricing->update($validated);

        ActivityLogger::log('Immigration Pricing', 'UPDATE', "Updated price row '{$this->describe($immigrationPricing)}'");

        return redirect()->route('admin.immigration-pricing.index')->with('success', 'Price row updated successfully!');
    }

    public function toggleStatus(Request $request, ImmigrationPricingTier $immigrationPricing)
    {
        $immigrationPricing->update(['is_active' => ! $immigrationPricing->is_active]);

        $statusStr = $immigrationPricing->is_active ? 'Enabled ●' : 'Disabled ○';
        ActivityLogger::log('Immigration Pricing', 'TOGGLE_STATUS', "Toggled price row '{$this->describe($immigrationPricing)}' to {$statusStr}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $immigrationPricing->is_active,
                'message' => $immigrationPricing->is_active ? 'Price row enabled!' : 'Price row disabled!',
            ]);
        }

        $statusMessage = $immigrationPricing->is_active ? 'Price row enabled!' : 'Price row disabled!';

        return back()->with('success', $statusMessage);
    }

    /**
     * Clear the review flag once staff have confirmed the figure against the source sheet.
     */
    public function confirmReview(Request $request, ImmigrationPricingTier $immigrationPricing)
    {
        $immigrationPricing->update(['needs_review' => false, 'is_active' => true]);

        ActivityLogger::log('Immigration Pricing', 'UPDATE', "Confirmed and published flagged price row '{$this->describe($immigrationPricing)}'");

        return back()->with('success', 'Price row confirmed and published!');
    }

    public function destroy(ImmigrationPricingTier $immigrationPricing)
    {
        $description = $this->describe($immigrationPricing);
        $immigrationPricing->delete();

        ActivityLogger::log('Immigration Pricing', 'DELETE', "Deleted price row '{$description}'");

        return redirect()->route('admin.immigration-pricing.index')->with('success', 'Price row deleted successfully!');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTier(Request $request): array
    {
        return $request->validate([
            'immigration_category_id' => 'required|exists:immigration_categories,id',
            'extension_label' => 'nullable|string|max:255',
            'duration_label' => 'nullable|string|max:255',
            'process_type' => 'required|in:regular,express',
            'payment_method' => 'required|in:cash,card',
            'condition_notes' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'processing_time' => 'nullable|string|max:255',
            'needs_review' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
    }

    /**
     * Human-readable label for a price row, used in the activity log.
     */
    private function describe(ImmigrationPricingTier $tier): string
    {
        $parts = array_filter([
            $tier->category?->name,
            $tier->extension_label,
            $tier->duration_label,
            ucfirst($tier->process_type),
            ucfirst($tier->payment_method),
            'PHP '.number_format((float) $tier->price, 2),
        ]);

        return implode(' - ', $parts);
    }
}
