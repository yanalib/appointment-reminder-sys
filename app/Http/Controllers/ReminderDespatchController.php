<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReminderDespatch;
use Illuminate\Http\Request;

class ReminderDespatchController extends Controller
{
    /**
     * Get all reminders
     */
    public function index()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
            ->orderBy('scheduled_for', 'desc')
            ->paginate(10);
            
        return response()->json($reminders);
    }


    /**
     * Get scheduled (pending) reminders
     */
    public function scheduled()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
            ->where('status', 'pending')
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for', 'asc')
            ->paginate(10);
            
        return response()->json($reminders);
    }

    /**
     * Get sent reminders
     */
    public function sent()
    {
        $reminders = ReminderDespatch::with(['appointment', 'client'])
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->paginate(10);
            
        return response()->json($reminders);
    }
}
