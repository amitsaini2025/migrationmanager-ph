<?php

namespace App\Console\Commands;

use App\Services\LegalCrm\LegalCrmPendingLeadSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPendingLegalCrmLeads extends Command
{
    protected $signature = 'legal-crm:sync-pending-leads {--limit=50 : Max pending leads to process per run}';

    protected $description = 'Push queued Migration CRM leads (send_to_legal_crm=2) to Legal CRM via API';

    public function handle(LegalCrmPendingLeadSyncService $syncService): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Syncing pending Legal CRM leads (limit {$limit})...");

        try {
            $stats = $syncService->syncPending($limit);

            $this->info(sprintf(
                'Scanned %d, sent %d, failed %d.',
                $stats['scanned'],
                $stats['sent'],
                $stats['failed']
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
