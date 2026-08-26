<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCategory;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationPricingTier;
use Illuminate\Support\Carbon;

class ImmigrationDashboardController extends Controller
{
    /**
     * Counter dashboard: only immigration reporting, nothing from the wider agency.
     */
    public function index()
    {
        $today = Carbon::today();
        $expressWindow = $today->copy()->addDays(7);

        $stats = [
            'clients' => ImmigrationClient::count(),
            'expiringSoon' => ImmigrationClient::whereNotNull('visa_expiry_date')
                ->whereBetween('visa_expiry_date', [$today, $expressWindow])
                ->count(),
            'flagged' => ImmigrationClient::where('is_expired', true)
                ->orWhere('has_penalty', true)
                ->orWhere('visa_expiry_date', '<', $today)
                ->count(),
            'extensionsThisMonth' => ImmigrationClientExtension::whereBetween('extension_date', [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ])->count(),
        ];

        $collectedThisMonth = (float) ImmigrationClientExtension::whereBetween('extension_date', [
            $today->copy()->startOfMonth(),
            $today->copy()->endOfMonth(),
        ])->sum('amount_paid');

        // Clients whose visa has lapsed or is inside the express window, soonest first
        $needsAttention = ImmigrationClient::query()
            ->where(fn ($query) => $query
                ->where('is_expired', true)
                ->orWhere('has_penalty', true)
                ->orWhere('visa_expiry_date', '<=', $expressWindow)
            )
            ->whereNotNull('visa_expiry_date')
            ->orderBy('visa_expiry_date')
            ->limit(8)
            ->get();

        $recentExtensions = ImmigrationClientExtension::with('client')
            ->whereNotNull('extension_date')
            ->orderByDesc('extension_date')
            ->limit(6)
            ->get();

        $pricing = [
            'categories' => ImmigrationCategory::where('is_active', true)->count(),
            'publishedRows' => ImmigrationPricingTier::published()->count(),
            'needsReview' => ImmigrationPricingTier::where('needs_review', true)->count(),
        ];

        return view('admin.immigration.dashboard', compact(
            'stats', 'collectedThisMonth', 'needsAttention', 'recentExtensions', 'pricing'
        ));
    }
}
