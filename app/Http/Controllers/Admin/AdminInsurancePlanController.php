<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsurancePlan;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The travel insurance plans offered in the ticket wizard's extras step.
 *
 * Plans are switched off rather than deleted, so a booking made on one keeps
 * its plan name. With every plan switched off the wizard hides insurance.
 */
class AdminInsurancePlanController extends Controller
{
    public function index(): View
    {
        $plans = InsurancePlan::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.insurance-plans.index', compact('plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = InsurancePlan::create($data + ['key' => InsurancePlan::keyFor($data['name']), 'is_active' => true]);

        ActivityLogger::log('Insurance', 'CREATE', "Added insurance plan '{$plan->name}' at ₱{$plan->price_per_pax} per passenger");

        return redirect()->route('admin.insurance-plans.index')->with('success', "{$plan->name} added.");
    }

    public function update(Request $request, InsurancePlan $insurancePlan): RedirectResponse
    {
        $insurancePlan->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        ActivityLogger::log('Insurance', 'UPDATE', "Updated insurance plan '{$insurancePlan->name}'");

        return redirect()->route('admin.insurance-plans.index')->with('success', "{$insurancePlan->name} updated.");
    }

    /**
     * @return array{name: string, price_per_pax: float, coverage: list<string>, is_popular: bool, sort_order: int}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_per_pax' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'coverage' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // One benefit per line.
        $coverage = collect(preg_split('/\R/', (string) ($validated['coverage'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->take(10)
            ->map(fn ($line) => mb_substr($line, 0, 120))
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'price_per_pax' => (float) $validated['price_per_pax'],
            'coverage' => $coverage,
            'is_popular' => $request->boolean('is_popular'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
