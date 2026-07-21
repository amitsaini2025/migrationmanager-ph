<?php

namespace App\Console\Commands;

use App\Models\ClientEoiReference;
use App\Services\EoiClientConfirmationNotificationService;
use Illuminate\Console\Command;

class BackfillEoiAmendmentActions extends Command
{
    protected $signature = 'eoi:backfill-amendment-actions
                            {--dry-run : Preview changes without writing to the database}
                            {--skip-notifications : Only backfill PA actions, skip verifier bell notifications}
                            {--limit= : Maximum number of EOI records to process}
                            {--client-id= : Process amendments for a single client id only}
                            {--eoi-id= : Process a single client_eoi_references id only}';

    protected $description = 'Backfill missing PA actions and verifier notifications for existing EOI/ROI amendment requests';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withNotifications = ! (bool) $this->option('skip-notifications');
        $limit = $this->option('limit') !== null ? max((int) $this->option('limit'), 1) : null;
        $clientId = $this->option('client-id') !== null ? (int) $this->option('client-id') : null;
        $eoiId = $this->option('eoi-id') !== null ? (int) $this->option('eoi-id') : null;

        if ($dryRun) {
            $this->info('Running in DRY-RUN mode — no database changes will be saved.');
        }

        if (!$withNotifications) {
            $this->comment('Verifier notifications are disabled for this run (--skip-notifications).');
        }

        $query = ClientEoiReference::query()
            ->with('client')
            ->where('client_confirmation_status', 'amendment_requested')
            ->orderBy('id');

        if ($clientId !== null && $clientId > 0) {
            $query->where('client_id', $clientId);
        }

        if ($eoiId !== null && $eoiId > 0) {
            $query->where('id', $eoiId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->warn('No EOI records with amendment_requested status found.');

            return self::SUCCESS;
        }

        $this->info("Found {$records->count()} amendment request(s) to inspect.");
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $summary = [
            'actions_created' => 0,
            'actions_would_create' => 0,
            'actions_skipped' => 0,
            'notifications_created' => 0,
            'notifications_would_create' => 0,
            'notifications_skipped' => 0,
            'notifications_disabled' => 0,
            'failed' => 0,
        ];

        foreach ($records as $eoi) {
            $result = EoiClientConfirmationNotificationService::backfillAmendmentRequest(
                $eoi,
                $withNotifications,
                $dryRun
            );

            switch ($result['action']) {
                case 'created':
                    $summary['actions_created']++;
                    break;
                case 'would_create':
                    $summary['actions_would_create']++;
                    break;
                default:
                    $summary['actions_skipped']++;
                    break;
            }

            switch ($result['notification']) {
                case 'created':
                    $summary['notifications_created']++;
                    break;
                case 'would_create':
                    $summary['notifications_would_create']++;
                    break;
                case 'disabled':
                    $summary['notifications_disabled']++;
                    break;
                case 'failed':
                    $summary['failed']++;
                    break;
                default:
                    $summary['notifications_skipped']++;
                    break;
            }

            if ($this->output->isVerbose() && $result['reason']) {
                $bar->clear();
                $this->line("EOI #{$eoi->id} ({$eoi->EOI_number}): {$result['reason']}");
                $bar->display();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill summary');
        $this->table(
            ['Metric', 'Count'],
            [
                ['PA actions created', $summary['actions_created']],
                ['PA actions would create (dry-run)', $summary['actions_would_create']],
                ['PA actions skipped', $summary['actions_skipped']],
                ['Verifier notifications created', $summary['notifications_created']],
                ['Verifier notifications would create (dry-run)', $summary['notifications_would_create']],
                ['Verifier notifications skipped', $summary['notifications_skipped']],
                ['Verifier notifications disabled', $summary['notifications_disabled']],
                ['Failures', $summary['failed']],
            ]
        );

        if ($dryRun && ($summary['actions_would_create'] > 0 || $summary['notifications_would_create'] > 0)) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
