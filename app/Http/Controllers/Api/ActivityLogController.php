<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs (Admin only)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = ActivityLog::with('causer:id,name,avatar')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $activities = $query->paginate($request->per_page ?? 20);

        return response()->json($activities);
    }

    /**
     * Get recent activities for dashboard widget
     */
    public function recent(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $activities = ActivityLog::with('causer:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->limit($request->limit ?? 10)
            ->get();

        return response()->json(['data' => $activities]);
    }

    /**
     * Get activity stats for dashboard
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $today = now()->startOfDay();
        $week = now()->subDays(7);

        $stats = [
            'today' => ActivityLog::whereDate('created_at', $today)->count(),
            'this_week' => ActivityLog::where('created_at', '>=', $week)->count(),
            'by_type' => ActivityLog::where('created_at', '>=', $week)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];

        return response()->json($stats);
    }
}
