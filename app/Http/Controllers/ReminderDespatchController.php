<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReminderDespatch;
use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendReminder;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReminderEmail;

class ReminderDespatchController extends Controller
{
    public function index(Request $request)
    {
        $query = ReminderDespatch::with(['appointment']);

        if ($request->has('status')) {
            $query = $this->filter($query, $request->status);
        }

        $reminders = $query->get();                          
        return response()->json($reminders);
    }

    public function filter($query, $status)
    {
        $query->where('status', $status)->orderBy('status', 'desc')->paginate(10);
        return $query;                    
    }

    public function analytics()
    {
        // Get counts for different reminder statuses
        $totalReminders = ReminderDespatch::count();
        $sentReminders = ReminderDespatch::where('status', 'sent')->count();
        $failedReminders = ReminderDespatch::where('status', 'failed')->count();
        $upcomingReminders = ReminderDespatch::where('status', 'scheduled')->where('scheduled_for', '>', now())->count();

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
        $query = ReminderDespatch::where('status', 'failed')
            ->with(['appointment', 'appointment.clients', 'user']);
        
        if ($request->has('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        $failedReminders = $query->get();
        
        if ($failedReminders->isEmpty()) {
            return response()->json([
                'message' => 'No failed reminders found',
                'total_processed' => 0
            ]);
        }
        
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($failedReminders as $reminder) {
            try {
                $appointment = $reminder->appointment;
                $clients = $appointment->clients;
                $userTimezone = $reminder->user->timezone ?? 'UTC';

                // Convert times to user's timezone
                $scheduledFor = Carbon::parse($reminder->scheduled_for)->setTimezone($userTimezone);
                $appointmentStart = Carbon::parse($appointment->start_datetime)->setTimezone($userTimezone);
                $appointmentEnd = Carbon::parse($appointment->end_datetime)->setTimezone($userTimezone);

                // Reset the reminder status
                $reminder->update([
                    'status' => 'pending',
                    'error_message' => null,
                    'retry_count' => ($reminder->retry_count ?? 0) + 1,
                    'scheduled_for' => now(),
                    'sent_at' => null
                ]);

                // Send the reminder email to all clients
                foreach ($clients as $client) {
                    // Convert times to client's timezone
                    $clientTimezone = $client->timezone ?? $userTimezone;
                    $clientAppointmentStart = $appointmentStart->copy()->setTimezone($clientTimezone);
                    $clientAppointmentEnd = $appointmentEnd->copy()->setTimezone($clientTimezone);

                    // Update appointment times for this specific client's email
                    $clientAppointment = clone $appointment;
                    $clientAppointment->start_datetime = $clientAppointmentStart;
                    $clientAppointment->end_datetime = $clientAppointmentEnd;

                    Mail::to($client->email)->send(new ReminderEmail($clientAppointment, $client));
                }

                // Dispatch the reminder job
                SendReminder::dispatch($reminder)->onQueue('reminders');

                $results['success'][] = [
                    'id' => $reminder->id,
                    'appointment' => [
                        'title' => $appointment->title,
                        'start_datetime' => $appointmentStart->format('Y-m-d H:i:s T'),
                        'end_datetime' => $appointmentEnd->format('Y-m-d H:i:s T'),
                        'timezone' => $userTimezone
                    ],
                    'clients' => $clients->map(function($client) use ($appointmentStart, $appointmentEnd) {
                        $clientTimezone = $client->timezone ?? 'UTC';
                        return [
                            'email' => $client->email,
                            'name' => $client->first_name . ' ' . $client->last_name,
                            'timezone' => $clientTimezone,
                            'appointment_time' => [
                                'start' => $appointmentStart->copy()->setTimezone($clientTimezone)->format('Y-m-d H:i:s T'),
                                'end' => $appointmentEnd->copy()->setTimezone($clientTimezone)->format('Y-m-d H:i:s T')
                            ]
                        ];
                    }),
                    'message' => 'Reminder rescheduled and email sent successfully'
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $reminder->id,
                    'appointment' => $appointment ? [
                        'title' => $appointment->title,
                        'start_datetime' => isset($appointmentStart) ? $appointmentStart->format('Y-m-d H:i:s T') : null,
                        'timezone' => $userTimezone ?? 'UTC'
                    ] : null,
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

    /**
     * Store a newly created reminder despatch for all clients in an appointment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
            'scheduled_for' => 'required_without:offset_minutes|date',
            'offset_minutes' => 'required_without:scheduled_for|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the appointment with its clients
            $appointment = Appointment::with('clients')->findOrFail($request->appointment_id);

            if ($appointment->clients->isEmpty()) {
                return response()->json([
                    'message' => 'No clients found for this appointment'
                ], 404);
            }

            // Calculate scheduled_for if offset_minutes is provided
            $scheduledFor = $request->scheduled_for;
            if ($request->has('offset_minutes')) {
                $scheduledFor = Carbon::parse($appointment->start_datetime)
                    ->subMinutes($request->offset_minutes);
            }

            $createdReminders = [];
            $failedReminders = [];

            // Create a reminder for each client
            foreach ($appointment->clients as $client) {
                try {
                    $reminder = ReminderDespatch::create([
                        'appointment_id' => $request->appointment_id,
                        'client_id' => $client->id,
                        'user_id' => $request->user_id,
                        'offset_minutes' => $request->offset_minutes,
                        'scheduled_for' => $scheduledFor,
                        'type' => $client->preferred_notification_method ?? 'email', // Use client's preference
                        'status' => 'pending',
                        'message_template' => 'default',
                        'error_message' => null,
                        'sent_at' => null
                    ]);

                    $reminder->load(['appointment', 'client']);
                    
                    // Dispatch the job to send the reminder
                    // If scheduled_for is in the future, delay the job
                    $delay = Carbon::parse($scheduledFor)->isAfter(now()) 
                        ? Carbon::parse($scheduledFor) 
                        : now();
                    
                    SendReminder::dispatch($reminder)
                        ->delay($delay)
                        ->onQueue('reminders');

                    $createdReminders[] = $reminder;

                } catch (\Exception $e) {
                    $failedReminders[] = [
                        'client_id' => $client->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'message' => 'Reminders created and queued for sending',
                'data' => [
                    'successful' => [
                        'count' => count($createdReminders),
                        'reminders' => $createdReminders,
                        'scheduled_for' => $scheduledFor
                    ],
                    'failed' => [
                        'count' => count($failedReminders),
                        'details' => $failedReminders
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
