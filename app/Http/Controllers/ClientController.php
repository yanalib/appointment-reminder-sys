<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();
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
            'reminder_preference' => 'nullable|string',
        ]); 

        $client = Client::create(
            [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'timezone' => $request->timezone,
                'reminder_preference' => $request->reminder_preference,
            ]
        );
        return response()->json($client, 201);
    }

    public function updateReminderPreference(Request $request, $clientId)
    {
        $request->validate([
            'reminder_preference' => 'required|string'
        ]);
        
        $client = Client::findOrFail($clientId);
        $client->update(['reminder_preference' => $request->reminder_preference]);
        return response()->json($client);
    }

    public function show(Client $client)
    {
        return response()->json($client);
    }

    public function update(Request $request, Client $client)
    {
        $client->update($request->all());
        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(null, 204);
    }
}