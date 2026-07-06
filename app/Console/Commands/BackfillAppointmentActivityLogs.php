<?php

namespace App\Console\Commands;

use App\Models\ActivitiesLog;
use App\Support\AppointmentActivityLogBackfill;
use Illuminate\Console\Command;

class BackfillAppointmentActivityLogs extends Command
{
    protected $signature = 'appointments:backfill-activity-logs
                            {--dry-run : Preview matches without saving changes}
                            {--limit= : Maximum number of activity logs to process}';

    protected $description = 'Rebuild legacy appointment scheduling activity log descriptions from booking_appointments data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max((int) $this->option('limit'), 1) : null;

        if ($dryRun) {
            $this->info('Running in DRY-RUN mode — no database changes will be saved.');
        }

        $query = ActivitiesLog::query()
            ->where('description', 'not like', '%appointment-activity-detail%')
            ->where(function ($subjectQuery) {
                $subjectQuery
                    ->whereRaw('LOWER(subject) LIKE ?', ['scheduled an appointment'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['scheduled a appointment'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['scheduled an free appointment'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['scheduled a free appointment'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['scheduled an paid appointment'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['scheduled a paid appointment']);
            })
            ->where(function ($typeQuery) {
                $typeQuery
                    ->whereNull('activity_type')
                    ->orWhere('activity_type', 'activity');
            })
            ->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $logs = $query->get()->filter(static fn (ActivitiesLog $log): bool => AppointmentActivityLogBackfill::isEligible($log));

        if ($logs->isEmpty()) {
            $this->info('No legacy appointment activity logs found to backfill.');

            return self::SUCCESS;
        }

        $this->info("Found {$logs->count()} legacy appointment activity log(s) to process.");
        $bar = $this->output->createProgressBar($logs->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $ambiguous = 0;
        $usedBookingIds = [];

        foreach ($logs as $log) {
            $appointment = AppointmentActivityLogBackfill::resolveBookingAppointment($log, $usedBookingIds);

            if ($appointment === null) {
                $skipped++;
                $bar->advance();

                continue;
            }

            if (in_array($appointment->id, $usedBookingIds, true)) {
                $ambiguous++;
                $skipped++;
                $bar->advance();

                continue;
            }

            $fields = AppointmentActivityLogBackfill::refreshedFields($appointment);

            if (! $dryRun) {
                $log->update([
                    'subject' => $fields['subject'],
                    'description' => $fields['description'],
                ]);
            }

            $usedBookingIds[] = $appointment->id;
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill summary');
        $this->line("Processed: {$logs->count()}");
        $this->info("Updated: {$updated}");
        $this->comment("Skipped (no safe match): {$skipped}");

        if ($ambiguous > 0) {
            $this->comment("Ambiguous booking reuse prevented: {$ambiguous}");
        }

        if ($dryRun && $updated > 0) {
            $this->warn('Dry-run complete. Re-run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}
