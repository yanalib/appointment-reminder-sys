<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    //Create and manage appointments
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/assign-client', [AppointmentController::class, 'assignClient']);
    
    //Associate a client with an appointment
    Route::post('/appointments/{appointment}/clients', [AppointmentController::class, 'associateClient']);
    
    //View upcoming and past appointments
    Route::get('/appointments/upcoming', [AppointmentController::class, 'upcoming']);
    Route::get('/appointments/past', [AppointmentController::class, 'past']);
    
    //View scheduled and sent reminders
    Route::get('/appointments/{appointment}/reminders', [AppointmentController::class, 'reminders']);

    //Schedule a reminder for each appointment
    Route::post('/appointments/{appointment}/reminders', [AppointmentController::class, 'scheduleReminder']);
    //Reminder should trigger before the appointment time, based on a configurable offset
    Route::post('/appointments/{appointment}/reminders/simulate', [AppointmentController::class, 'simulateReminder']);
    //Simulate sending via logs or local mail service (e.g. Mailpit)
    Route::post('/appointments/{appointment}/reminders/send', [AppointmentController::class, 'sendReminder']);

    Route::post('/clients', [ClientController::class, 'store']);
    Route::post('/clients/reminder-preferences/{client}', [ClientController::class, 'updateReminderPreference']);
    
    // View all reminders (scheduled and sent)
    Route::get('/reminders', [ReminderDespatchController::class, 'index']);
    Route::get('/reminders/scheduled', [ReminderDespatchController::class, 'scheduled']);
    Route::get('/reminders/sent', [ReminderDespatchController::class, 'sent']);
});