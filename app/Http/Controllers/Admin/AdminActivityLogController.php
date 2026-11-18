<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    /**
     * Display live audit logs feed for Administrators.
     */
    public function index(Request $request)
    {
        $logs = $this->filtered($request)->latest()->paginate(20)->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            // The live feed adds only logs newer than this, whatever page or filter is shown.
            'lastLogId' => ActivityLog::max('id') ?? 0,
        ]);
    }

    /**
     * The page's filters (module, role, search, date range), shared by the page and its live feed.
     */
    private function filtered(Request $request): Builder
    {
        $query = ActivityLog::with('user');

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('role')) {
            $query->where('user_role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // The date range picked on the page (App\Support\DateRange).
        return DateRange::fromRequest($request)->apply($query);
    }

    /**
     * Return real-time JSON stream of recent logs for live AJAX updates.
     */
    public function stream(Request $request)
    {
        $lastId = $request->input('last_id', 0);

        // Oldest first: the page puts each one on top, so the newest ends up first.
        $newLogs = $this->filtered($request)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->take(15)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user_name,
                    'user_role' => $log->user_role,
                    'module' => $log->module,
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'time_ago' => $log->created_at->diffForHumans(),
                    'created_at_formatted' => $log->created_at->format('M j, Y • g:i:s A'),
                ];
            });

        return response()->json([
            'success' => true,
            'logs' => $newLogs,
            'max_id' => ActivityLog::max('id') ?? 0,
        ]);
    }
}
