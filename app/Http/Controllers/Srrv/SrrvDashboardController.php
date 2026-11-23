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
        // Each service's icon colour on its card, as on the Immigration and Visa Assistance dashboards.
        $colors = ['classic' => 'sky', 'courtesy' => 'violet', 'conversion' => 'emerald', 'termination' => 'rose', 'restamping' => 'orange'];

        $card = function (string $key): array {
            $service = SrrvClientLookupController::SERVICES[$key];
            // What its list shows first and in progress: still open, or held for the admin whatever the status.
            [$files, $closed] = match (true) {
                in_array($key, SrrvApplication::VISA_CLASSES, true) => [SrrvApplication::where('visa_class', $key), 'released'],
                str_starts_with($key, 'renewal_') => [SrrvRenewal::where('visa_class', $service['params']['visa_class']), 'collected'],
                default => [SrrvServiceRequest::where('type', $key), 'completed'],
            };

            return [
                'label' => $service['label'],
                'icon' => $service['icon'],
                'count' => (clone $files)->openOrHeld([$closed])->count(),
                'waiting' => (clone $files)->where('status', '!=', 'cancelled')->held()->count(),
                'unit' => 'open',
                'url' => route('srrv.services', $key),
            ];
        };

        $services = collect($colors)
            ->map(function (string $color, string $key) use ($card): array {
                if (! in_array($key, SrrvApplication::VISA_CLASSES, true)) {
                    return $card($key) + ['color' => $color];
                }

                // SRRV Classic and SRRV Courtesy each ask first: a new application or a renewal (each its own list and form).
                $application = $card($key);
                $choices = [['label' => 'New Application'] + $application, ['label' => 'Renewal'] + $card('renewal_'.$key)];

                return ['color' => $color, 'choices' => $choices,
                    'count' => array_sum(array_column($choices, 'count')), 'waiting' => array_sum(array_column($choices, 'waiting'))] + $application;
            })->values()->all();

        $recentApplications = SrrvApplication::with('documents')
            ->latest()
            ->limit(6)
            ->get();

        return view('srrv.dashboard', compact('stats', 'services', 'recentApplications', 'today'));
    }
}
