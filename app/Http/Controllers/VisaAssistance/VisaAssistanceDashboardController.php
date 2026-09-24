<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use App\Models\VisaPricingTier;
use Illuminate\Contracts\View\View;

/**
 * The visa assistance counter dashboard: three services sharing one desk.
 */
class VisaAssistanceDashboardController extends Controller
{
    /**
     * Display the counter dashboard with per-service stats and pipelines.
     */
    public function index(): View
    {
        $stats = [
            'total' => VisaApplication::count(),
            'visitVisas' => VisaApplication::where('service_type', 'visit_visa')->count(),
            'eVisas' => VisaApplication::where('service_type', 'e_visa')->count(),
            'passporting' => VisaApplication::where('service_type', 'passporting')->count(),
            'open' => VisaApplication::whereNotIn('status', ['released', 'cancelled'])->count(),
            'rush' => VisaApplication::where('processing_speed', 'rush')->count(),
        ];

        $services = [
            [
                'key' => 'visit_visa',
                'label' => 'Visit Visa',
                'blurb' => 'Counter-handled application for foreign nationals. The destination sets everything after.',
                'icon' => 'globe',
                'count' => $stats['visitVisas'],
                'stages' => VisaApplication::SERVICE_STAGES['visit_visa'],
                'notes' => [
                    'Australia and New Zealand need no appearance.',
                    'Rush returns in 1-5 days; regular runs 1-3 weeks.',
                ],
            ],
            [
                'key' => 'e_visa',
                'label' => 'e-Visa',
                'blurb' => 'For foreign nationals. No appearance, no embassy queue.',
                'icon' => 'zap',
                'count' => $stats['eVisas'],
                'stages' => VisaApplication::SERVICE_STAGES['e_visa'],
                'notes' => [
                    'Individual or group package — same steps either way.',
                    'Priced per applicant; back in 1-3 days.',
                ],
            ],
            [
                'key' => 'passporting',
                'label' => 'Passporting',
                'blurb' => 'Passport applications handled at the counter.',
                'icon' => 'book-user',
                'count' => $stats['passporting'],
                'stages' => VisaApplication::SERVICE_STAGES['passporting'],
                'notes' => [
                    'Foreign: USA, Canada, Australia and the UK.',
                    'Local: the Philippine passport, through the DFA.',
                ],
            ],
        ];

        $recentApplications = VisaApplication::with('applicants')
            ->latest()
            ->limit(6)
            ->get();

        $pricing = VisaPricingTier::published()
            ->orderBy('service_type')
            ->orderBy('sort_order')
            ->get();

        return view('visa-assistance.dashboard', compact('stats', 'services', 'recentApplications', 'pricing'));
    }
}
