<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReminderDespatch;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReminderDespatchController extends Controller
{
    public function index()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
                                    ->orderBy('scheduled_for', 'desc')
                                    ->paginate(10);
            
        return response()->json($reminders);
    }

    public function scheduled()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
            ->where('status', 'pending')
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for', 'asc')
            ->paginate(10);
            
        return response()->json($reminders);
    }

    public function sent()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->paginate(10);
            
        return response()->json($reminders);
    }

    public function analytics()
    {
        // Get counts for different reminder statuses
        $totalReminders = ReminderDespatch::count();
        $sentReminders = ReminderDespatch::where('status', 'sent')->count();
        $failedReminders = ReminderDespatch::where('status', 'failed')->count();
        $upcomingReminders = ReminderDespatch::where('status', 'scheduled')
            ->where('scheduled_for', '>', now())
            ->count();

        // Get the latest reminders for each status
        $latestSent = ReminderDespatch::with(['appointment', 'user'])
            ->where('status', 'sent')
            ->orderBy('sent_at', 'desc')
            ->take(5)
            ->get();

        $latestFailed = ReminderDespatch::with(['appointment', 'user'])
            ->where('status', 'failed')
            ->orderBy('scheduled_for', 'desc')
            ->take(5)
            ->get();

        $upcomingNext = ReminderDespatch::with(['appointment', 'user'])
            ->where('status', 'scheduled')
            ->where('scheduled_for', '>', now())
            ->orderBy('scheduled_for', 'asc')
            ->take(5)
            ->get();

        // Get today's statistics
        $today = Carbon::today();
        $todayStats = [
            'sent' => ReminderDespatch::where('status', 'sent')
                ->whereDate('sent_at', $today)
                ->count(),
            'failed' => ReminderDespatch::where('status', 'failed')
                ->whereDate('scheduled_for', $today)
                ->count(),
            'upcoming' => ReminderDespatch::where('status', 'scheduled')
                ->whereDate('scheduled_for', $today)
                ->where('scheduled_for', '>', now())
                ->count()
        ];

        return response()->json([
            'summary' => [
                'total_reminders' => $totalReminders,
                'sent_reminders' => $sentReminders,
                'failed_reminders' => $failedReminders,
                'upcoming_reminders' => $upcomingReminders,
            ],
            'today_stats' => $todayStats,
            'latest_reminders' => [
                'sent' => $latestSent->map(function ($reminder) {
                    return [
                        'id' => $reminder->id,
                        'appointment' => [
                            'title' => $reminder->appointment->title,
                            'start_datetime' => $reminder->appointment->start_datetime,
                        ],
                        'sent_at' => $reminder->sent_at,
                        'user' => $reminder->user->getFullNameAttribute()
                    ];
                }),
                'failed' => $latestFailed->map(function ($reminder) {
                    return [
                        'id' => $reminder->id,
                        'appointment' => [
                            'title' => $reminder->appointment->title,
                            'start_datetime' => $reminder->appointment->start_datetime,
                        ],
                        'scheduled_for' => $reminder->scheduled_for,
                        'error_message' => $reminder->error_message,
                        'user' => $reminder->user->getFullNameAttribute()
                    ];
                }),
                'upcoming' => $upcomingNext->map(function ($reminder) {
                    return [
                        'id' => $reminder->id,
                        'appointment' => [
                            'title' => $reminder->appointment->title,
                            'start_datetime' => $reminder->appointment->start_datetime,
                        ],
                        'scheduled_for' => $reminder->scheduled_for,
                        'user' => $reminder->user->getFullNameAttribute()
                    ];
                })
            ]
        ]);
    }

    public function retryFailedReminders(Request $request)
    {
        // Get all failed reminders or specific ones if IDs are provided
        $query = ReminderDespatch::where('status', 'failed');
        
        if ($request->has('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        $failedReminders = $query->get();
        
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($failedReminders as $reminder) {
            try {
                // Reset the reminder status and clear error message
                $reminder->status = 'pending';
                $reminder->error_message = null;
                $reminder->retry_count = ($reminder->retry_count ?? 0) + 1;
                $reminder->scheduled_for = now(); // Reschedule for immediate delivery
                $reminder->save();

                $results['success'][] = [
                    'id' => $reminder->id,
                    'message' => 'Reminder rescheduled successfully'
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $reminder->id,
                    'message' => 'Failed to reschedule reminder: ' . $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Retry process completed',
            'total_processed' => $failedReminders->count(),
            'successful' => count($results['success']),
            'failed' => count($results['failed']),
            'details' => $results
        ]);
    }
}
