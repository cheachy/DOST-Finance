<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $events = ActivityLog::orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'level' => $log->level,
                'title' => $log->title,
                'description' => $log->description,
                'date' => $log->created_at?->format('F j, Y'),
                'time' => $log->created_at?->format('g:i A'),
                'at' => $log->created_at?->format('M j, Y g:i A'),
            ]);

        return Inertia::render('activitylogs/Index', [
            'events' => $events,
        ]);
    }
}
