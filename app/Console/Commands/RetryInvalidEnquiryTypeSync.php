<?php

namespace App\Console\Commands;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\RetryInvalidEnquirySyncService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:retry-invalid-enquiry-sync')]
class RetryInvalidEnquiryTypeSync extends Command
{
    protected $signature = 'booking:retry-invalid-enquiry-sync
                            {--id= : Process a single booking_appointments.id}
                            {--limit= : Maximum number of appointments to process}
                            {--dry-run : Preview payloads without calling the Bansal API}
                            {--force : Skip confirmation prompt}
                            {--delay=1 : Seconds to wait between API calls}';

    protected $description = 'Retry Bansal sync for appointments that failed with invalid enquiry type';

    public function handle(RetryInvalidEnquirySyncService $retryService): int
    {
        $query = $retryService->eligibleQuery();

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
            $this->line('Criteria: sync_status=error, invalid enquiry type sync_error, temp bansal_appointment_id, upcoming, not cancelled.');
            $this->line('Matching sync_error values:');
            foreach (RetryInvalidEnquirySyncService::invalidEnquirySyncErrors() as $syncError) {
                $this->line('  - ' . $syncError);
            }
            $this->line('  - (or any sync_error containing "' . RetryInvalidEnquirySyncService::INVALID_ENQUIRY_SYNC_ERROR . '")');

            return self::SUCCESS;
        }

        $this->info("Found {$count} eligible appointment(s).");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no API calls or database updates will be made.');
            $this->newLine();
            $this->displayPreviewTable($appointments, $retryService);

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Create {$count} appointment(s) on Bansal and update CRM records?", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $delay = max(0, (int) $this->option('delay'));
        $stats = ['success' => 0, 'failed' => 0];

        $this->newLine();
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        foreach ($appointments as $index => $appointment) {
            try {
                $bansalId = $retryService->syncAppointmentToBansal($appointment);
                $stats['success']++;
                $this->newLine();
                $this->info("  ✓ #{$appointment->id} → Bansal ID {$bansalId}");
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
                ['Synced', $stats['success']],
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
    protected function displayPreviewTable($appointments, RetryInvalidEnquirySyncService $retryService): void
    {
        $rows = [];

        foreach ($appointments as $appointment) {
            $payload = $retryService->buildCreatePayload($appointment);

            $rows[] = [
                $appointment->id,
                $appointment->bansal_appointment_id,
                $appointment->appointment_datetime?->format('Y-m-d H:i'),
                $appointment->location,
                $appointment->noe_id,
                $appointment->enquiry_type,
                $payload['enquiry_type'],
                $payload['service_type'],
            ];
        }

        $this->table(
            ['CRM ID', 'Temp Bansal ID', 'DateTime', 'Location', 'NOE', 'Stored enquiry_type', 'API enquiry_type', 'API service_type'],
            $rows
        );
    }
}
