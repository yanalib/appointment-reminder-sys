<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Appointment;
use App\Models\Client;

class ReminderEmail extends Mailable
{
    use Queueable, SerializesModels;
    
    public Appointment $appointment;
    public Client $client;

    public function __construct(Appointment $appointment, Client $client)
    {
        $this->appointment = $appointment;
        $this->client = $client;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Appointment Reminder',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
        with: [
            'appointment' => $this->appointment,
            'client' => $this->client,
        ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
