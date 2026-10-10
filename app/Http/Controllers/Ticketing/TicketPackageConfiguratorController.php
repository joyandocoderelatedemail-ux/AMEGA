<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Admin\AdminPackageConfiguratorController;
use App\Models\Airline;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The ready-made side of the admin package configurator, served inside the
 * ticketing portal: the same catalog, listed first, then added to or edited
 * with the ticketing desk's own views.
 */
class TicketPackageConfiguratorController extends AdminPackageConfiguratorController
{
    protected string $routePrefix = 'ticketing.packages';

    protected string $viewPrefix = 'ticketing.packages';

    /**
     * The package catalog, with a search and category / status filters.
     */
    public function catalog(Request $request)
    {
        $packages = TravelPackage::with(['destination', 'airline:id,name'])
            ->when($request->filled('search'), fn ($query) => $this->matching($query, $request->input('search')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('ticketing.packages.index', compact('packages'));
    }

    /**
     * Narrow a package query to those whose title or destination matches.
     */
    protected function matching(Builder $query, string $term): Builder
    {
        // Typed % and _ are matched literally, not as wildcards.
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($inner) => $inner
            ->where('title', 'like', $like)
            ->orWhereHas('destination', fn ($destination) => $destination->where('name', 'like', $like)));
    }

    public function edit(TravelPackage $package)
    {
        $destinations = Destination::orderBy('name')->get();
        $airlines = Airline::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code', 'is_active']);

        return view('ticketing.packages.configurator', compact('package', 'destinations', 'airlines'));
    }

    public function update(Request $request, TravelPackage $package): RedirectResponse
    {
        $package->update($this->readyMadeAttributes($request, $package));

        ActivityLogger::log('Packages', 'UPDATE', "Updated ready-made package '{$package->title}' from the ticketing desk", ['package_id' => $package->id]);

        return redirect()->route('ticketing.packages.index')
            ->with('success', "Package '{$package->title}' updated.");
    }

    protected function readyMadeSaved(): RedirectResponse
    {
        return redirect()->route('ticketing.packages.index');
    }
}
