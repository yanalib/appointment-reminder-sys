<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'notes',
        'start_datetime',
        'end_datetime',
        'user_id',
        'is_recurring',
        'recurrence_rule',
        'recurring_until',
        'timezone',
        'status'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_recurring' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all clients for the appointment
     */
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'clients_appointments')
            ->withTimestamps();
    }

    /**
     * Get all reminders for the appointment
     */
    public function reminders()
    {
        return $this->hasMany(ReminderDespatch::class);
    }
}
