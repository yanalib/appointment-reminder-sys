<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'timezone',
        'reminder_preference'
    ];

    /**
     * Get all appointments for the client
     */
    public function appointments()
    {
        return $this->belongsToMany(Appointment::class, 'clients_appointments')
            ->withTimestamps();
    }

    /**
     * Get all reminders for the client
     */
    public function reminders()
    {
        return $this->hasMany(ReminderDespatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
