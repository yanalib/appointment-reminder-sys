<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\{AuthController, AppointmentController, ClientController, ReminderDespatchController};

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/clients', [ClientController::class, 'store']);
    Route::post('/clients/reminder-preferences/{client}', [ClientController::class, 'updateReminderPreference']);

    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/assign-clients', [AppointmentController::class, 'assignClients']);
    //View upcoming and past appointments - need type param e.g. ?type=upcoming or ?type=past
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::any('/appointments/{appointmentId}/status', [AppointmentController::class, 'changeStatus']);
    
    Route::get('/appointments/{appointment}/reminders', [AppointmentController::class, 'reminders']);
    Route::post('/appointments/{appointment}/reminders', [AppointmentController::class, 'scheduleReminder']);
    Route::post('/appointments/{appointment}/reminders/simulate', [AppointmentController::class, 'simulateReminder']);
    Route::post('/appointments/{appointment}/reminders/send', [AppointmentController::class, 'sendReminder']);
    
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/reminders', [ReminderDespatchController::class, 'index']);
        Route::post('/reminders', [ReminderDespatchController::class, 'store']);
        Route::get('/reminders/analytics', [ReminderDespatchController::class, 'analytics']);
    });
});