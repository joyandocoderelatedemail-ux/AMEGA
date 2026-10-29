<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The SRRV desk dashboard: the counts and the latest retiree files. The services are under the
 * Services menu, and the blank PRA forms under Blank PRA Forms in the top bar.
 */
class SrrvDashboardController extends Controller
{
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

        $recentApplications = SrrvApplication::with('documents')
            ->latest()
            ->limit(6)
            ->get();

        return view('srrv.dashboard', compact('stats', 'recentApplications', 'today'));
    }
}
