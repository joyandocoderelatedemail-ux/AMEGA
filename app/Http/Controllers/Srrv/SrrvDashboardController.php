<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvPricingTier;
use App\Models\SrrvRenewal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The SRRV desk dashboard: PRA retiree visa work, three jobs sharing one desk.
 */
class SrrvDashboardController extends Controller
{
    /**
     * Display the desk dashboard with per-job stats and pipelines.
     */
    public function index(): View
    {
        $today = Carbon::today();

        $stats = [
            'total' => SrrvApplication::count(),
            'classic' => SrrvApplication::where('visa_class', 'classic')->count(),
            'courtesy' => SrrvApplication::where('visa_class', 'courtesy')->count(),
            'open' => SrrvApplication::whereNotIn('status', ['released', 'cancelled'])->count(),
            'awaitingOath' => SrrvApplication::whereNull('oath_at')
                ->whereNotNull('payment_in_full_at')
                ->count(),
            'renewalsDue' => SrrvRenewal::whereNotIn('status', ['collected', 'cancelled'])->count(),
        ];

        $jobs = [
            [
                'key' => 'renewal_application',
                'label' => 'New SRRV Application',
                'blurb' => 'A retiree applying for the first time. Classic or courtesy is decided by who they are.',
                'icon' => 'file-badge',
                'stages' => array_map(fn (string $stage) => SrrvApplication::STAGE_LABELS[$stage], SrrvApplication::SERVICE_STAGES['renewal_application']),
                'notes' => [
                    'Classic: police clearance and proof of pension.',
                    'Courtesy: government or military, aged 50 and above, with proof of military service.',
                    'Processing takes 2-3 months; fully paid before release.',
                ],
            ],
            [
                'key' => 'renewal',
                'label' => 'Annual Renewal',
                'blurb' => 'Due once a year, for as long as the retiree stays.',
                'icon' => 'calendar-check',
                'count' => $stats['renewalsDue'],
                'stages' => array_map(fn (string $stage) => SrrvRenewal::STAGE_LABELS[$stage], SrrvRenewal::STAGES),
                'notes' => [
                    'Classic USD 360 a year, courtesy USD 10 a year.',
                    'Two years at most can be paid ahead.',
                    'Collected in person at the PRA office — never posted.',
                ],
            ],
            // Re-stamping returns here once its flow is defined.
        ];

        $recentApplications = SrrvApplication::with('documents')
            ->latest()
            ->limit(6)
            ->get();

        $recentRenewals = SrrvRenewal::latest()
            ->limit(5)
            ->get();

        $pricing = SrrvPricingTier::published()
            ->orderBy('service_type')
            ->orderBy('sort_order')
            ->get();

        return view('srrv.dashboard', compact(
            'stats', 'jobs', 'recentApplications', 'recentRenewals', 'pricing', 'today'
        ));
    }
}
