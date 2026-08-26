<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCategory;
use App\Models\ImmigrationRequirement;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminImmigrationCategoryController extends Controller
{
    public function index()
    {
        $categories = ImmigrationCategory::withCount(['requirements', 'pricingTiers'])
            ->orderBy('sort_order', 'asc')
            ->get();

        return view('admin.immigration-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.immigration-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);

        $validated['slug'] = Str::slug(($validated['slug'] ?? null) ?: $validated['name']);
        $validated['is_active'] = $request->has('is_active');

        $category = ImmigrationCategory::create($validated);

        $this->syncRequirements($category, $request);

        ActivityLogger::log('Immigration Pricing', 'CREATE', "Created immigration process category '{$category->name}'");

        return redirect()->route('admin.immigration-categories.index')->with('success', 'Immigration category created successfully!');
    }

    public function edit(ImmigrationCategory $immigrationCategory)
    {
        $immigrationCategory->load('requirements');

        return view('admin.immigration-categories.edit', ['category' => $immigrationCategory]);
    }

    public function update(Request $request, ImmigrationCategory $immigrationCategory)
    {
        $validated = $this->validateCategory($request, $immigrationCategory);

        $validated['slug'] = Str::slug(($validated['slug'] ?? null) ?: $validated['name']);
        $validated['is_active'] = $request->has('is_active');

        $immigrationCategory->update($validated);

        $this->syncRequirements($immigrationCategory, $request);

        ActivityLogger::log('Immigration Pricing', 'UPDATE', "Updated immigration process category '{$immigrationCategory->name}'");

        return redirect()->route('admin.immigration-categories.index')->with('success', 'Immigration category updated successfully!');
    }

    public function toggleStatus(Request $request, ImmigrationCategory $immigrationCategory)
    {
        $immigrationCategory->update(['is_active' => ! $immigrationCategory->is_active]);

        $statusStr = $immigrationCategory->is_active ? 'Enabled ●' : 'Disabled ○';
        ActivityLogger::log('Immigration Pricing', 'TOGGLE_STATUS', "Toggled immigration category '{$immigrationCategory->name}' to {$statusStr}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $immigrationCategory->is_active,
                'message' => $immigrationCategory->is_active ? 'Category enabled!' : 'Category disabled!',
            ]);
        }

        $statusMessage = $immigrationCategory->is_active ? 'Category enabled!' : 'Category disabled!';

        return back()->with('success', $statusMessage);
    }

    public function destroy(ImmigrationCategory $immigrationCategory)
    {
        $name = $immigrationCategory->name;
        $immigrationCategory->delete();

        ActivityLogger::log('Immigration Pricing', 'DELETE', "Deleted immigration category '{$name}' and all of its price rows");

        return redirect()->route('admin.immigration-categories.index')->with('success', 'Immigration category deleted successfully!');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?ImmigrationCategory $category = null): array
    {
        $slugRule = 'nullable|string|max:255|unique:immigration_categories,slug';

        if ($category) {
            $slugRule .= ",{$category->id}";
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => $slugRule,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'processing_time' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'requirements' => 'nullable|array',
            'requirements.*.id' => 'nullable|integer',
            'requirements.*.label' => 'nullable|string',
            'requirements.*.type' => 'nullable|in:requirement,note',
            'requirements.*.needs_review' => 'nullable|boolean',
        ]);

        unset($validated['requirements']);

        return $validated;
    }

    /**
     * Replace the category's checklist and process notes with the submitted rows.
     */
    private function syncRequirements(ImmigrationCategory $category, Request $request): void
    {
        $rows = collect($request->input('requirements', []))
            ->filter(fn ($row): bool => filled($row['label'] ?? null))
            ->values();

        $keptIds = [];

        foreach ($rows as $index => $row) {
            $attributes = [
                'label' => $row['label'],
                'type' => $row['type'] ?? 'requirement',
                'needs_review' => (bool) ($row['needs_review'] ?? false),
                'sort_order' => $index + 1,
            ];

            $existing = filled($row['id'] ?? null)
                ? $category->requirements()->whereKey($row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $category->requirements()->create($attributes)->id;
        }

        ImmigrationRequirement::where('immigration_category_id', $category->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }
}
