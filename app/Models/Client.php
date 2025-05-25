<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'timezone',
        'reminder_preference'
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reminders()
    {
        return $this->hasMany(ReminderDespatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
