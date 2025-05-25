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
        'status',
        'reminder_sent'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
