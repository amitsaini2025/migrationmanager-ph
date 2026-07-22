<?php

namespace App\Console\Commands;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:retry-not-found-sync')]
class RetryAppointmentNotFoundSync extends Command
{
    protected $signature = 'booking:retry-not-found-sync
                            {--id= : Process a single booking_appointments.id}
                            {--limit= : Maximum number of appointments to process}
                            {--include-past : Also retry appointments before today}
                            {--dry-run : Preview recovery actions without calling the Bansal API}
                            {--force : Skip confirmation prompt}
                            {--delay=1 : Seconds to wait between API calls}';

    protected $description = 'Retry Bansal sync for appointments that failed with appointment not found (404) errors';

    public function handle(BansalAppointmentRecoveryService $recoveryService): int
    {
        $query = $recoveryService->eligibleNotFoundQuery((bool) $this->option('include-past'));

        if ($this->option('id')) {
            $query->where('id', (int) $this->option('id'));
        }

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $appointments = $query->get();
        $count = $appointments->count();

        if ($count === 0) {
            $this->info('No eligible appointments found.');
            $this->line('Criteria: sync_status=error, 404/appointment not found sync_error, appointment date today or later, not cancelled.');
            $this->line('Use --include-past to retry older appointments. Completed appointments are included.');
            $this->line('Excluded: invalid enquiry type errors (use booking:retry-invalid-enquiry-sync).');

            return self::SUCCESS;
        }

        $this->info("Found {$count} eligible appointment(s).");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no API calls or database updates will be made.');
            $this->newLine();
            $this->displayPreviewTable($appointments, $recoveryService);

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Recover {$count} appointment(s) on Bansal and update CRM records?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $delay = max(0, (int) $this->option('delay'));
        $stats = ['linked' => 0, 'created' => 0, 'failed' => 0];

        $this->newLine();
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        foreach ($appointments as $index => $appointment) {
            try {
                $result = $recoveryService->retryNotFoundSync($appointment);

                if ($result['synced']) {
                    if ($result['action'] === 'created') {
                        $stats['created']++;
                    } else {
                        $stats['linked']++;
                    }

                    $this->newLine();
                    $this->info(sprintf(
                        '  ✓ #%d → Bansal ID %s (%s)',
                        $appointment->id,
                        $result['bansal_appointment_id'],
                        $result['action']
                    ));
                } else {
                    $stats['failed']++;
                    $this->newLine();
                    $this->error("  ✗ #{$appointment->id}: {$result['error']}");
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->newLine();
                $this->error("  ✗ #{$appointment->id}: {$e->getMessage()}");
            }

            $progressBar->advance();

            if ($delay > 0 && $index < ($count - 1)) {
                sleep($delay);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                ['Linked existing Bansal record', $stats['linked']],
                ['Created new Bansal record', $stats['created']],
                ['Failed', $stats['failed']],
                ['Total', $count],
            ]
        );

        if ($stats['failed'] > 0) {
            $this->warn('Some appointments could not be synced. Check storage/logs/laravel.log for details.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<int, BookingAppointment> $appointments
     */
    protected function displayPreviewTable($appointments, BansalAppointmentRecoveryService $recoveryService): void
    {
        $rows = [];

        foreach ($appointments as $appointment) {
            $existingId = $recoveryService->findExistingBansalAppointmentId($appointment);

            $rows[] = [
                $appointment->id,
                $appointment->bansal_appointment_id,
                $appointment->client_email,
                $appointment->appointment_datetime?->format('Y-m-d H:i'),
                $appointment->location,
                $existingId ?? '—',
                $existingId !== null ? 'link' : 'create',
            ];
        }

        $this->table(
            ['CRM ID', 'Current Bansal ID', 'Email', 'DateTime', 'Location', 'Matching Bansal ID', 'Planned action'],
            $rows
        );
    }
}
