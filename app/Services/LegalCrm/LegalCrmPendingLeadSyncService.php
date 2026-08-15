<?php

namespace App\Services\LegalCrm;

use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

class LegalCrmPendingLeadSyncService
{
    /**
     * Push queued Migration CRM leads (send_to_legal_crm = 2) to Legal CRM.
     * On success marks send_to_legal_crm = 1; on failure leaves pending for retry.
     *
     * @return array{scanned: int, sent: int, failed: int}
     */
    public function syncPending(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $leads = Lead::query()
            ->where('send_to_legal_crm', Lead::LEGAL_CRM_PENDING)
            ->where('is_archived', 0)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [
            'scanned' => $leads->count(),
            'sent' => 0,
            'failed' => 0,
        ];

        if ($leads->isEmpty()) {
            return $stats;
        }

        $client = app(LegalCrmApiClient::class);

        foreach ($leads as $lead) {
            try {
                $apiResult = $client->createLeadFromMigrationLead($lead);
                $lead->markSentToLegalCrm();
                $stats['sent']++;

                Log::channel('migration_legal_crm')->info('Cron Legal CRM sync succeeded', [
                    'migration_lead_id' => (int) $lead->id,
                    'legal_lead_id' => $apiResult['lead_id'] ?? null,
                    'legal_already_exists' => (bool) ($apiResult['already_exists'] ?? false),
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'api_message' => $apiResult['message'] ?? null,
                ]);
            } catch (Throwable $e) {
                $stats['failed']++;

                Log::channel('migration_legal_crm')->error('Cron Legal CRM sync failed — left pending for retry', [
                    'migration_lead_id' => (int) $lead->id,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
