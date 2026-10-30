<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
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

        // One card per service of the Services menu, with what is open on it (the same files its list shows).
        $services = collect(['classic', 'courtesy', 'renewal_classic', 'renewal_courtesy', 'conversion', 'termination', 'restamping'])
            ->map(function (string $key): array {
                $service = SrrvClientLookupController::SERVICES[$key];
                $open = match (true) {
                    in_array($key, ['classic', 'courtesy'], true) => SrrvApplication::where('visa_class', $key)->whereNotIn('status', ['released', 'cancelled']),
                    str_starts_with($key, 'renewal_') => SrrvRenewal::where('visa_class', $service['params']['visa_class'])->whereNotIn('status', ['collected', 'cancelled']),
                    default => SrrvServiceRequest::where('type', $key)->whereNotIn('status', ['completed', 'cancelled']),
                };

                return [
                    'label' => $service['label'],
                    'icon' => $service['icon'],
                    'count' => $open->count(),
                    'unit' => 'open',
                    'url' => route('srrv.services', $key),
                ];
            })->all();

        $recentApplications = SrrvApplication::with('documents')
            ->latest()
            ->limit(6)
            ->get();

        return view('srrv.dashboard', compact('stats', 'services', 'recentApplications', 'today'));
    }
}
