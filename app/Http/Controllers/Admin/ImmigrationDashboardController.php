<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCategory;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationPricingTier;
use App\Support\ImmigrationCases;
use App\Support\ImmigrationServices;

class ImmigrationDashboardController extends Controller
{
    /**
     * Counter dashboard: an overview of every service the counter runs, and what is
     * waiting on the desk. Only immigration reporting, nothing from the wider agency.
     */
    public function index()
    {
        // Cases not released yet, across every service, most recently changed first.
        $openCases = ImmigrationCases::search(null, 'open', '', 500);
        $openByKind = ImmigrationCases::openCounts();

        // Visa Extension has no cases: its work is the visas that lapsed or are about to.
        $needsAttention = ImmigrationClient::requiringAttention()
            ->orderByRaw('visa_expiry_date IS NULL')
            ->orderBy('visa_expiry_date')
            ->limit(8)
            ->get();
        $attentionCount = ImmigrationClient::requiringAttention()->count();

        $kindByService = collect(ImmigrationCases::kinds())->mapWithKeys(fn (array $kind, string $key) => [$kind['service'] => $key]);

        // One card per service that runs here, showing what is open on it.
        $services = collect(ImmigrationServices::all())
            ->filter(fn (array $service) => $service['available'])
            ->map(function (array $service) use ($kindByService, $openByKind, $attentionCount): array {
                $kind = $kindByService->get($service['key']);

                return [
                    'label' => $service['label'],
                    'icon' => $service['icon'],
                    'count' => $kind ? ($openByKind[$kind] ?? 0) : $attentionCount,
                    'unit' => $kind ? 'open' : 'to chase',
                    // The service's active applications, with New to start one (the cards are the way in).
                    'url' => $service['url'],
                ];
            })
            ->values();

        $stats = [
            'clients' => ImmigrationClient::count(),
            'open' => $openCases->count(),
            'submitted' => $openCases->where('progress', 'submitted')->count(),
            'attention' => $attentionCount,
        ];

        $pricing = [
            'needsReview' => ImmigrationPricingTier::where('needs_review', true)->count(),
            'categories' => ImmigrationCategory::where('is_active', true)->count(),
        ];

        return view('admin.immigration.dashboard', [
            'stats' => $stats,
            'services' => $services,
            'recentCases' => $openCases->take(6),
            'needsAttention' => $needsAttention,
            'pricing' => $pricing,
        ]);
    }
}
