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
            'openRush' => VisaApplication::where('processing_speed', 'rush')->whereNotIn('status', ['released', 'cancelled'])->count(),
            'released' => VisaApplication::where('status', 'released')->count(),
            'cancelled' => VisaApplication::where('status', 'cancelled')->count(),
        ];

        // One card per service, with its open files (as on the Immigration and SRRV dashboards).
        $services = collect([
            'visit_visa' => ['Non-Immigrant', 'globe'],
            'e_visa' => ['e-Visa', 'zap'],
            'passporting' => ['Passporting', 'book-user'],
        ])->map(fn (array $service, string $key): array => [
            'key' => $key,
            'label' => $service[0],
            'icon' => $service[1],
            'count' => VisaApplication::where('service_type', $key)->whereNotIn('status', ['released', 'cancelled'])->count(),
            'unit' => 'open',
            'url' => route('visa.services', $key),
        ])->values()->all();

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
