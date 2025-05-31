<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ReminderDespatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Appointment;
use App\Mail\ReminderEmail;

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

    public function __construct(ReminderDespatch $reminder)
    {
        $this->reminder = $reminder;
    }

    public function handle(): void
    {
        try {
            $appointment = $this->reminder->appointment;
            $clients = $appointment->clients;
    
            foreach ($clients as $client) {
                Mail::to($client->email)->later($this->reminder->scheduled_for, new ReminderEmail($appointment, $client));
            }
            $this->reminder->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Failed to send reminder', [
                'reminder_id' => $this->reminder->id,
                'error' => $e->getMessage()
            ]);
    
            $this->reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
    
            throw $e;
        }
    }

  
    public function failed(\Throwable $exception): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->reminder->update([
                'status' => 'failed',
                'error_message' => "Final failure after {$this->tries} attempts: " . $exception->getMessage()
            ]);
        }
    }

}