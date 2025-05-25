<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        $query = Appointment::all();
        if ($request->type === 'upcoming') {
            $query->where('start_time', '>=', now());
        } elseif ($request->type === 'past') {
            $query->where('start_time', '<', now());
        }
        $appointments = $query->get()->orderBy('start_time', 'asc');
        return response()->json($appointments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'           => 'required|string',
            'description'     => 'required|nullable|string',
            'start_time'      => 'required|date',
            'end_time'        => 'required|date',
            'user_id'         => 'required|exists:users,id',
            'client_id'       => 'nullable|exists:clients,id',
            'notes'           => 'nullable|string',
            'status'          => 'nullable|string',
            'is_reccuring'    => 'nullable|boolean',
            'reccurable_rule' => 'nullable|string' //e.g. FREQ=WEEKLY;UNTIL=20250731T235959Z
        ]);

        $appointment = Appointment::create([
            'title'           => $request->title,
            'description'     => $request->description,
            'start_time'      => $request->start_time,
            'end_time'        => $request->end_time,
            'user_id'         => $request->user_id,
            'client_id'       => $request->client_id,
            'notes'           => $request->notes,
            'status'          => $request->status,
            'is_reccuring'    => $request->is_reccuring,
            'reccurable_rule' => $request->reccurable_rule
        ]);

        return response()->json($appointment, 201);
    }       

    public function show($id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }
        return response()->json($appointment);
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::find($id);
        return response()->json($appointment);
    }

    public function destroy($id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }
        $appointment->delete();
        return response()->json(null, 204);
    }

    public function assignClient(Request $request, Appointment $appointment)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
        ]);

        $appointment->client_id = $request->client_id;
        $appointment->save();

        return response()->json([
            'message' => 'Client associated successfully.',
            'appointment' => $appointment->load('client'),
        ]);
    }

    public function changeStatus(Request $request, $appointmentId)
    {
        $request->validate([
            'status' => 'required|string|in:pending,confirmed,cancelled',
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $appointment->status = $request->status;
        $appointment->save();

        return response()->json($appointment);
    }
}
