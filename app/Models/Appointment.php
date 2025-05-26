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
        'appointment_date',
        'start_time',
        'end_time',
        'user_id',
        'notes',
        'status',
        'is_recurring',
        'recurrence_rule',
        'reminder_sent'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_recurring' => 'boolean',
        'reminder_sent' => 'boolean',
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
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }
}
