<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ReminderDespatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::query();
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        
        if ($request->type === 'upcoming') {
            $query->where('start_datetime', '>=', now());
        } elseif ($request->type === 'past') {
            $query->where('start_datetime', '<', now());
        }
        
        $appointments = $query->with(['clients', 'reminders'])
            ->orderBy('start_datetime', 'asc')
            ->paginate(10);
            
        // Convert times to user's timezone
        $appointments->through(function ($appointment) use ($userTimezone) {
            $appointment->start_datetime = Carbon::parse($appointment->start_datetime)
                ->setTimezone($userTimezone)
                ->format('Y-m-d H:i:s T');
            $appointment->end_datetime = Carbon::parse($appointment->end_datetime)
                ->setTimezone($userTimezone)
                ->format('Y-m-d H:i:s T');
            $appointment->display_timezone = $userTimezone;
            return $appointment;
        });

        return response()->json($appointments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,confirmed,cancelled',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|required_if:is_recurring,true',
            'recurring_until' => 'nullable|date|required_if:is_recurring,true|after:start_datetime',
            'timezone' => 'nullable|string',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id'
        ]);

        // Convert input times from user's timezone to UTC for storage
        $userTimezone = $request->timezone ?? auth()->user()->timezone ?? 'UTC';
        $startDatetime = Carbon::parse($request->start_datetime, $userTimezone)->setTimezone('UTC');
        $endDatetime = Carbon::parse($request->end_datetime, $userTimezone)->setTimezone('UTC');

        $appointment = Appointment::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'user_id' => $request->user_id,
            'notes' => $request->notes,
            'status' => $request->status ?? 'pending',
            'is_recurring' => $request->is_recurring ?? false,
            'recurrence_rule' => $request->recurrence_rule,
            'recurring_until' => $request->recurring_until ? Carbon::parse($request->recurring_until, $userTimezone)->setTimezone('UTC') : null,
            'timezone' => $userTimezone
        ]);

        if ($request->has('client_ids')) {
            $appointment->clients()->attach($request->client_ids);
        }

        // Convert times back to user's timezone for response
        $appointment->start_datetime = $startDatetime->setTimezone($userTimezone)->format('Y-m-d H:i:s T');
        $appointment->end_datetime = $endDatetime->setTimezone($userTimezone)->format('Y-m-d H:i:s T');
        $appointment->display_timezone = $userTimezone;

        return response()->json($appointment->load('clients'), 201);
    }

    public function show($appointmentId)
    {
        $appointment = Appointment::with(['clients', 'reminders'])->findOrFail($appointmentId);
        $userTimezone = auth()->user()->timezone ?? $appointment->timezone ?? 'UTC';

        // Convert times to user's timezone
        $appointment->start_datetime = Carbon::parse($appointment->start_datetime)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s T');
        $appointment->end_datetime = Carbon::parse($appointment->end_datetime)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s T');
        $appointment->display_timezone = $userTimezone;

        // Convert reminder times if they exist
        if ($appointment->reminders->isNotEmpty()) {
            $appointment->reminders->transform(function ($reminder) use ($userTimezone) {
                if ($reminder->scheduled_for) {
                    $reminder->scheduled_for = Carbon::parse($reminder->scheduled_for)
                        ->setTimezone($userTimezone)
                        ->format('Y-m-d H:i:s T');
                }
                if ($reminder->sent_at) {
                    $reminder->sent_at = Carbon::parse($reminder->sent_at)
                        ->setTimezone($userTimezone)
                        ->format('Y-m-d H:i:s T');
                }
                return $reminder;
            });
        }

        return response()->json($appointment);
    }

    public function update(Request $request, $appointmentId)
    {
        $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'start_datetime' => 'nullable|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,confirmed,cancelled',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|required_if:is_recurring,true',
            'recurring_until' => 'nullable|date|required_if:is_recurring,true|after:start_datetime',
            'timezone' => 'nullable|string',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id'
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $userTimezone = $request->timezone ?? auth()->user()->timezone ?? $appointment->timezone ?? 'UTC';

        $updateData = $request->only([
            'title',
            'description',
            'notes',
            'status',
            'is_recurring',
            'recurrence_rule',
            'timezone'
        ]);

        // Convert datetime fields from user's timezone to UTC for storage
        if ($request->has('start_datetime')) {
            $updateData['start_datetime'] = Carbon::parse($request->start_datetime, $userTimezone)->setTimezone('UTC');
        }
        if ($request->has('end_datetime')) {
            $updateData['end_datetime'] = Carbon::parse($request->end_datetime, $userTimezone)->setTimezone('UTC');
        }
        if ($request->has('recurring_until')) {
            $updateData['recurring_until'] = Carbon::parse($request->recurring_until, $userTimezone)->setTimezone('UTC');
        }

        $appointment->update($updateData);

        if ($request->has('client_ids')) {
            $appointment->clients()->sync($request->client_ids);
        }

        // Convert times back to user's timezone for response
        $appointment->refresh();
        $appointment->start_datetime = Carbon::parse($appointment->start_datetime)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s T');
        $appointment->end_datetime = Carbon::parse($appointment->end_datetime)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s T');
        if ($appointment->recurring_until) {
            $appointment->recurring_until = Carbon::parse($appointment->recurring_until)
                ->setTimezone($userTimezone)
                ->format('Y-m-d H:i:s T');
        }
        $appointment->display_timezone = $userTimezone;

        return response()->json($appointment->load('clients'));
    }

    public function destroy($appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $appointment->clients()->detach();
        $appointment->delete();
        return response()->json('Appointment deleted successfully', 204);
    }

    public function assignClients(Request $request, $appointmentId)
    {
        $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id'
        ]);

        $appointment = Appointment::find($appointmentId);
        $appointment->clients()->attach($request->client_ids);

        return response()->json([
            'message' => 'Clients associated successfully.',
            'appointment' => $appointment->load('clients'),
        ]);
    }

    public function removeClients(Request $request, $appointmentId)
    {
        $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id'
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $appointment->clients()->detach($request->client_ids);

        return response()->json([
            'message' => 'Clients removed successfully.',
            'appointment' => $appointment->load('clients'),
        ]);
    }

    public function changeStatus(Request $request, $appointmentId)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $appointment->update(['status' => $request->status]);

        return response()->json($appointment);
    }

  
    public function scheduleReminder(Request $request, $appointmentId)
    {
        $request->validate([
            'offset_minutes' => 'required|integer|min:1|max:10080', // Max 1 week in minutes
        ]);

        $appointment = Appointment::with('clients')->findOrFail($appointmentId);
        $userTimezone = auth()->user()->timezone ?? $appointment->timezone ?? 'UTC';

        // Calculate scheduled_for time in UTC
        $scheduledFor = Carbon::parse($appointment->start_datetime)
            ->subMinutes($request->offset_minutes);

        if ($scheduledFor->isPast()) {
            return response()->json([
                'message' => 'Cannot schedule reminders in the past'
            ], 422);
        }

        $reminders = [];
        foreach ($appointment->clients as $client) {
            $reminder = ReminderDespatch::create([
                'appointment_id' => $appointment->id,
                'user_id' => auth()->id(),
                'scheduled_for' => $scheduledFor,
                'status' => 'scheduled',
                'offset_minutes' => $request->offset_minutes,
            ]);

            // Convert scheduled time to user's timezone for response
            $reminder->scheduled_for = $scheduledFor
                ->setTimezone($userTimezone)
                ->format('Y-m-d H:i:s T');
            $reminder->display_timezone = $userTimezone;
            
            $reminders[] = $reminder;
        }

        return response()->json([
            'message' => 'Reminders scheduled successfully',
            'reminders' => $reminders,
        ], 201);
    }

   
    public function reminders(Request $request, $appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $userTimezone = auth()->user()->timezone ?? $appointment->timezone ?? 'UTC';
        
        $query = ReminderDespatch::where('appointment_id', $appointmentId);

        // Add any filters from the request
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $reminders = $query->get();

        // Convert times to user's timezone
        $reminders->transform(function ($reminder) use ($userTimezone) {
            if ($reminder->scheduled_for) {
                $reminder->scheduled_for = Carbon::parse($reminder->scheduled_for)
                    ->setTimezone($userTimezone)
                    ->format('Y-m-d H:i:s T');
            }
            if ($reminder->sent_at) {
                $reminder->sent_at = Carbon::parse($reminder->sent_at)
                    ->setTimezone($userTimezone)
                    ->format('Y-m-d H:i:s T');
            }
            $reminder->display_timezone = $userTimezone;
            return $reminder;
        });

        return response()->json($reminders);
    }
}
