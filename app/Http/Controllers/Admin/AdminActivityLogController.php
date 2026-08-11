<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    /**
     * Display live audit logs feed for Administrators.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

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

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }

    /**
     * Return real-time JSON stream of recent logs for live AJAX updates.
     */
    public function stream(Request $request)
    {
        $lastId = $request->input('last_id', 0);

        $newLogs = ActivityLog::with('user')
            ->where('id', '>', $lastId)
            ->latest()
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
