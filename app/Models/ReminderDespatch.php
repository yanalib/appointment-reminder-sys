<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderDespatch extends Model
{
    protected $fillable = [
        'appointment_id',
        'reminder_time',
        'status',
        'sent_at',
        'error_message'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }


}
