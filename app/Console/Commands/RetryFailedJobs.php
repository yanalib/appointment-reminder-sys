<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReminderEmail;
use App\Models\{Appointment, Client, ReminderDespatch};

class RetryFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:retry {id?* : The IDs of the failed jobs}
                          {--all : Retry all failed jobs}
                          {--queue= : Retry all failed jobs for the specified queue}
                          {--notify= : Email address to send notification to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed jobs from the failed jobs table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->retryAllJobs();
        } elseif ($this->option('queue')) {
            $this->retryQueueJobs();
        } elseif ($this->argument('id')) {
            $this->retrySpecificJobs();
        } else {
            $this->error('Please specify either --all, --queue=name, or provide specific job IDs.');
            return 1;
        }
    }

    /**
     * Retry all failed jobs.
     */
    protected function retryAllJobs(): void
    {
        $count = DB::table('failed_jobs')->count();
        
        if ($count === 0) {
            $this->info('No failed jobs found.');
            return;
        }

        $this->info("Retrying all {$count} failed jobs...");
        
        $failedJobs = DB::table('failed_jobs')->get();
        $results = $this->processJobs($failedJobs);
        
        $this->info('All failed jobs have been queued for retry.');
        
        $this->sendNotification($results);
    }

    /**
     * Retry jobs for a specific queue.
     */
    protected function retryQueueJobs(): void
    {
        $queue = $this->option('queue');
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', $queue)
            ->get();

        $count = $failedJobs->count();

        if ($count === 0) {
            $this->info("No failed jobs found for queue: {$queue}");
            return;
        }

        $this->info("Retrying {$count} failed jobs from queue: {$queue}...");
        
        $results = $this->processJobs($failedJobs);
        
        $this->info("All failed jobs from queue {$queue} have been queued for retry.");
        
        $this->sendNotification($results);
    }

    /**
     * Retry specific jobs by their IDs.
     */
    protected function retrySpecificJobs(): void
    {
        $ids = $this->argument('id');
        $this->info('Retrying specific failed jobs...');
        
        $failedJobs = DB::table('failed_jobs')
            ->whereIn('id', $ids)
            ->get();
            
        $results = $this->processJobs($failedJobs);
        
        $this->info('Finished retrying specified jobs.');
        
        $this->sendNotification($results);
    }

    /**
     * Process the jobs and return results
     */
    protected function processJobs($jobs): array
    {
        $results = [
            'successful' => [],
            'failed' => []
        ];

        foreach ($jobs as $job) {
            try {
                // For reminder jobs, update the ReminderDespatch status
                $payload = json_decode($job->payload, true);
                $command = unserialize($payload['data']['command']);
                
                if ($command instanceof \App\Jobs\SendReminder) {
                    $reminder = $command->reminder;
                    if ($reminder) {
                        $reminder->update([
                            'status' => 'pending',
                            'error_message' => null,
                            'retry_count' => ($reminder->retry_count ?? 0) + 1
                        ]);
                    }
                }

                Artisan::call('queue:retry', ['id' => $job->id]);
                $results['successful'][] = [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'payload' => $payload
                ];
                $this->info("Retried job {$job->id}");
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'error' => $e->getMessage()
                ];
                $this->error("Failed to retry job {$job->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send email notification about the retry results
     */
    protected function sendNotification(array $results): void
    {
        $notifyEmail = $this->option('notify');
        if (!$notifyEmail) {
            return;
        }

        // Create a dummy appointment and client for the email template
        $appointment = new Appointment([
            'title' => 'Job Retry Report',
            'description' => sprintf(
                "Successfully retried: %d jobs\nFailed to retry: %d jobs",
                count($results['successful']),
                count($results['failed'])
            ),
            'start_datetime' => now()
        ]);

        $client = new Client([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => $notifyEmail
        ]);

        Mail::to($notifyEmail)->send(new ReminderEmail($appointment, $client));
        $this->info("Notification sent to {$notifyEmail}");
    }
} 