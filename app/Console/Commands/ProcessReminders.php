<?php
//php artisan reminders:process
//php artisan schedule:work 
//php artisan queue:work --queue=reminders --tries=3 --backoff=30

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReminderDespatch;
use App\Jobs\SendReminder;
use Carbon\Carbon;

class ProcessReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending reminders that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process reminders...');

        // Get all pending reminders that are due to be sent
        $dueReminders = ReminderDespatch::with(['appointment'])
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->get();

        $count = $dueReminders->count();
        $this->info("Found {$count} reminders to process");

        foreach ($dueReminders as $reminder) {
            try {
                SendReminder::dispatch($reminder);
                $this->info("Dispatched reminder ID: {$reminder->id}");
            } catch (\Exception $e) {
                $this->error("Failed to dispatch reminder ID: {$reminder->id} - {$e->getMessage()}");
            }
        }

        $this->info('Finished processing reminders');
    }
}
