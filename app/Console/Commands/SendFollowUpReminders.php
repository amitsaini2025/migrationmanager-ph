<?php

namespace App\Console\Commands;

use App\Services\FollowUpReminderService;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature = 'followups:send-reminders';

    protected $description = 'Send in-app reminder notifications to assignees one day before a Follow Up assign date';

    public function handle(FollowUpReminderService $reminders): int
    {
        $this->info('Sending Follow Up reminders...');

        try {
            $stats = $reminders->sendDueReminders();

            $this->info("Due date {$stats['due_date']}: scanned {$stats['scanned']}, sent {$stats['sent']}, skipped {$stats['skipped']}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
