<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCategory;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
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
        // Cases still open, across every service: those held for the admin first (even when they carry a release
        // date), then the rest by latest change. The same files the service cards count.
        $by = ImmigrationCases::ownerFilter(request()->user());
        $openCases = ImmigrationCases::search(null, 'service', '', 500, $by)
            ->reject(fn (array $case) => $case['closed'] && ! in_array($case['approval'], ImmigrationPetition::APPROVAL_HELD, true))
            ->values();
        $activeByKind = ImmigrationCases::activeCounts($by);
        $heldExtensions = ImmigrationCases::heldExtensionFolders($by);

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
            ->map(function (array $service) use ($kindByService, $activeByKind, $attentionCount, $heldExtensions): array {
                $kind = $kindByService->get($service['key']);

                return [
                    'label' => $service['label'],
                    'icon' => $service['icon'],
                    'count' => $kind ? ($activeByKind[$kind]['open'] ?? 0) : $attentionCount,
                    'unit' => $kind ? 'open' : 'to chase',
                    // Files held for the admin (waiting or returned), counted in the open ones and listed first.
                    'waiting' => $kind ? ($activeByKind[$kind]['waiting'] ?? 0) : $heldExtensions,
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
