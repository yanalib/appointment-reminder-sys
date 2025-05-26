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
        
        if ($request->type === 'upcoming') {
            $query->where('start_datetime', '>=', now());
        } elseif ($request->type === 'past') {
            $query->where('start_datetime', '<', now());
        }
        
        $appointments = $query->with(['clients', 'reminders'])
            ->orderBy('start_datetime', 'asc')
            ->paginate(10);
            
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

        $appointment = Appointment::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_datetime' => $request->start_datetime,
            'end_datetime' => $request->end_datetime,
            'user_id' => $request->user_id,
            'notes' => $request->notes,
            'status' => $request->status ?? 'pending',
            'is_recurring' => $request->is_recurring ?? false,
            'recurrence_rule' => $request->recurrence_rule,
            'recurring_until' => $request->recurring_until,
            'timezone' => $request->timezone ?? 'UTC'
        ]);

        if ($request->has('client_ids')) {
            $appointment->clients()->attach($request->client_ids);
        }

        return response()->json($appointment->load('clients'), 201);
    }

    public function show($appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        return response()->json($appointment->load(['clients', 'reminders']));
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
        $appointment->update($request->only([
            'title',
            'description',
            'start_datetime',
            'end_datetime',
            'notes',
            'status',
            'is_recurring',
            'recurrence_rule',
            'recurring_until',
            'timezone'
        ]));

        if ($request->has('client_ids')) {
            $appointment->clients()->sync($request->client_ids);
        }

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
            'status' => 'required|string|in:pending,confirmed,cancelled',
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

        $appointment = Appointment::findOrFail($appointmentId);
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
        
        $query = ReminderDespatch::where('appointment_id', $appointment->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'scheduled') {
                $query->where('status', 'scheduled')
                      ->whereNull('sent_at');
            } elseif ($status === 'sent') {
                $query->where('status', 'sent')
                      ->whereNotNull('sent_at');
            }
        }
 
        $reminders = $query->with(['user' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'email');
            }])
            ->orderBy($sortBy, $sortOrder)
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'appointment' => $appointment->only([
                'id', 
                'title', 
                'start_datetime', 
                'end_datetime'
            ]),
            'filters' => [
                'status' => $request->status
            ],
            'reminders' => $reminders
        ]);
    }
}
