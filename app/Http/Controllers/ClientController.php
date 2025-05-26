<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::with(['appointments', 'reminders'])
            ->paginate(10);
        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients',
            'phone' => 'nullable|string',
            'timezone' => 'nullable|string|max:255',
            'reminder_preference' => 'nullable|string|in:email,sms,both',
        ]); 

        $client = Client::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'timezone' => $request->timezone ?? 'UTC',
            'reminder_preference' => $request->reminder_preference ?? 'email',
        ]);

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json($client->load(['appointments', 'reminders']));
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:clients,email,' . $client->id,
            'phone' => 'nullable|string',
            'timezone' => 'nullable|string|max:255',
            'reminder_preference' => 'nullable|string|in:email,sms,both',
        ]);

        $client->update($request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'timezone',
            'reminder_preference'
        ]));

        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $client->appointments()->detach();
        $client->delete();
        return response()->json(null, 204);
    }

    public function updateReminderPreference(Request $request, Client $client)
    {
        $request->validate([
            'reminder_preference' => 'required|string|in:email,sms,both'
        ]);
        
        $client->update(['reminder_preference' => $request->reminder_preference]);
        return response()->json($client);
    }

    public function appointments(Client $client)
    {
        return response()->json($client->appointments()
            ->with('reminders')
            ->orderBy('start_datetime', 'asc')
            ->paginate(10));
    }

    public function reminders(Client $client)
    {
        return response()->json($client->reminders()
            ->with('appointment')
            ->orderBy('scheduled_for', 'desc')
            ->paginate(10));
    }
}