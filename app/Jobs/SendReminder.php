<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ReminderDespatch;
use Illuminate\Support\Facades\Log;

class SendReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reminder;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120]; // Wait 30s, then 60s, then 120s between retries

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'reminders';

    /**
     * Create a new job instance.
     */
    public function __construct(ReminderDespatch $reminder)
    {
        $this->reminder = $reminder;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the appointment and client details
            $appointment = $this->reminder->appointment;
            $client = $this->reminder->client;

            // Prepare email content
            $emailContent = $this->prepareEmailContent($appointment, $client);

            // Log the email (for now)
            Log::info('Sending reminder email', [
                'reminder_id' => $this->reminder->id,
                'client_email' => $client->email,
                'appointment_id' => $appointment->id,
                'content' => $emailContent
            ]);

            // Update reminder status
            $this->reminder->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null
            ]);

        } catch (\Exception $e) {
            // Log the error
            Log::error('Failed to send reminder', [
                'reminder_id' => $this->reminder->id,
                'error' => $e->getMessage()
            ]);

            // Update reminder status
            $this->reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Throw the exception to trigger job failure and retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Update reminder status on final failure
        if ($this->attempts() >= $this->tries) {
            $this->reminder->update([
                'status' => 'failed',
                'error_message' => "Final failure after {$this->tries} attempts: " . $exception->getMessage()
            ]);
        }
    }

    /**
     * Prepare email content based on appointment and client details
     */
    private function prepareEmailContent($appointment, $client): string
    {
        $startTime = $appointment->start_datetime;
        
        return "Dear {$client->name},\n\n" .
               "This is a reminder for your upcoming appointment:\n\n" .
               "Title: {$appointment->title}\n" .
               "Date: {$startTime->format('l, F j, Y')}\n" .
               "Time: {$startTime->format('g:i A')}\n" .
               ($appointment->location ? "Location: {$appointment->location}\n" : "") .
               ($appointment->description ? "\nDetails: {$appointment->description}\n" : "") .
               "\nIf you need to reschedule, please contact us as soon as possible.\n\n" .
               "Best regards,\n" .
               "Your Appointment Team";
    }
}
