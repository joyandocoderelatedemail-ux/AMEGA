<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\TravelPackage;
use App\Support\Countries;
use Illuminate\Http\Request;

class TravelPackageController extends Controller
{
    /** Other names packages and destinations use for a country in the home page's list. */
    private const COUNTRY_ALIASES = [
        'United Arab Emirates' => ['United Arab Emirates', 'UAE', 'Dubai', 'Abu Dhabi'],
        'United States' => ['United States', 'USA', 'U.S.A.'],
        'United Kingdom' => ['United Kingdom', 'UK', 'England', 'London'],
        'South Korea' => ['South Korea', 'Korea', 'Seoul'],
        'Hong Kong' => ['Hong Kong', 'HK'],
    ];

    /**
     * Display a listing of public travel packages.
     */
    public function index(Request $request)
    {
        $query = TravelPackage::query();

        // Include active packages (or packages without status set)
        $query->where(function ($q) {
            $q->where('status', 'active')
                ->orWhereNull('status');
        });

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $lowerSearch = strtolower($search);

            // A country from the home page's list. The Philippines means the local packages; others
            // match under the names packages and destinations use for them ("UAE" for the Emirates).
            $isCountry = in_array($search, Countries::all(), true);
            $terms = self::COUNTRY_ALIASES[$search] ?? [$search];

            if (in_array($lowerSearch, ['local', 'domestic', 'philippines'])) {
                $query->where(function ($q) {
                    $q->where('category', 'domestic')
                        ->orWhereHas('destination', function ($dq) {
                            $dq->where('type', 'domestic');
                        });
                });
            } elseif (in_array($lowerSearch, ['international', 'foreign', 'short_haul', 'long_haul'])) {
                $query->where(function ($q) {
                    $q->whereIn('category', ['short_haul', 'long_haul'])
                        ->orWhereHas('destination', function ($dq) {
                            $dq->where('type', 'international');
                        });
                });
            } elseif ($isCountry) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere('title', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%")
                            ->orWhereHas('destination', fn ($dq) => $dq->where('name', 'like', "%{$term}%")->orWhere('location', 'like', "%{$term}%"));
                    }
                });
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('destination', function ($dq) use ($search) {
                            $dq->where('name', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        });
                });
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $packages = $query->latest()->paginate(9)->withQueryString();
        $destinations = Destination::where('is_featured', true)->get();

        return view('packages.index', compact('packages', 'destinations'));
    }

    /**
     * Display the specified travel package details and itinerary.
     */
    public function show(TravelPackage $package)
    {
        $package->load('destination');
        $relatedPackages = TravelPackage::where('id', '!=', $package->id)
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->take(3)
            ->get();

        return view('packages.show', compact('package', 'relatedPackages'));
    }
}
