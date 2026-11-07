<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\User;
use App\Support\DateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The commissions admins set when approving services, added up: per agent, per month and file by file.
 * Admin only (the route sits behind admin.only): nobody else sees what an agent earns.
 */
class AdminCommissionController extends Controller
{
    /** The desks a commission can come from, as the page names them. */
    public const DESKS = [
        'ticketing' => 'Ticketing',
        'visa_assistance' => 'Visa Assistance',
        'srrv' => 'PRA-SRRV',
        'immigration' => 'Immigration',
    ];

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);

        $query = AgentCommission::query()
            ->when($request->filled('agent'), fn ($q) => $request->input('agent') === 'none'
                ? $q->whereNull('agent_id')
                : $q->where('agent_id', $request->integer('agent')))
            ->when(array_key_exists((string) $request->input('desk'), self::DESKS), fn ($q) => $q->where('desk', $request->input('desk')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('client_name', 'like', $like));
            });

        $range->apply($query);

        // Light rows for the totals; the list below is paged.
        $rows = (clone $query)->get(['id', 'agent_id', 'amount', 'created_at']);
        $agentNames = User::whereIn('id', $rows->pluck('agent_id')->filter()->unique())->pluck('name', 'id');

        $perAgent = $rows->groupBy(fn (AgentCommission $c) => $c->agent_id ?? 0)
            ->map(fn ($group, $agentId) => [
                'agent_id' => (int) $agentId,
                'name' => $agentId ? ($agentNames[$agentId] ?? 'Removed staff') : 'No agent recorded',
                'files' => $group->count(),
                'total' => round($group->sum(fn (AgentCommission $c) => (float) $c->amount), 2),
            ])
            ->sortByDesc('total')
            ->values();

        $perMonth = $rows->groupBy(fn (AgentCommission $c) => $c->created_at->format('Y-m'))
            ->map(fn ($group, $month) => [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y'),
                'files' => $group->count(),
                'total' => round($group->sum(fn (AgentCommission $c) => (float) $c->amount), 2),
            ])
            ->sortByDesc('month')
            ->values();

        $commissions = $query->with(['agent:id,name', 'setBy:id,name'])->latest('id')->paginate(25)->withQueryString();

        return view('admin.commissions.index', [
            'commissions' => $commissions,
            'perAgent' => $perAgent,
            'perMonth' => $perMonth,
            'grandTotal' => round($rows->sum(fn (AgentCommission $c) => (float) $c->amount), 2),
            'fileCount' => $rows->count(),
            // Everyone who has earned a commission, for the agent filter (not narrowed by the other filters).
            'agents' => User::whereIn('id', AgentCommission::query()->whereNotNull('agent_id')->distinct()->pluck('agent_id'))->orderBy('name')->get(['id', 'name']),
            'desks' => self::DESKS,
        ]);
    }
}
