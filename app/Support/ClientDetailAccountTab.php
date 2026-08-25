<?php

namespace App\Support;

use App\Models\AccountClientReceipt;
use App\Models\ClientMatter;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Loads Account tab ledger/invoice/office-receipt data outside the Blade view.
 */
final class ClientDetailAccountTab
{
    /**
     * @return array{
     *     client_selected_matter_id: int|null,
     *     calculated_balance: float,
     *     receipts_lists: Collection,
     *     latest_outstanding_balance: float,
     *     receipts_lists_invoice: array<int, object>,
     *     receipts_lists_office: Collection
     * }
     */
    public static function build(object $client, ?string $matterRefNo = null): array
    {
        $clientId = (int) ($client->id ?? 0);
        $crmClientRef = (string) ($client->client_id ?? '');
        $matterId = self::resolveMatterId($clientId, $matterRefNo);

        $ledgerEntries = self::receiptsQuery($clientId, $matterId)
            ->where('receipt_type', 1)
            ->get();

        $calculatedBalance = 0.0;
        foreach ($ledgerEntries as $entry) {
            if (isset($entry->void_fee_transfer) && (int) $entry->void_fee_transfer === 1) {
                continue;
            }
            $calculatedBalance += (float) $entry->deposit_amount - (float) $entry->withdraw_amount;
        }

        $receiptsLists = self::receiptsQuery($clientId, $matterId)
            ->where('receipt_type', 1)
            ->orderBy('id', 'desc')
            ->get();

        $latestOutstandingBalance = (float) self::receiptsQuery($clientId, $matterId)
            ->where('receipt_type', 3)
            ->where(function ($query) {
                $query->whereIn('invoice_status', [0, 2])
                    ->orWhere(function ($q) {
                        $q->where('invoice_status', 1)
                            ->where('balance_amount', '!=', 0);
                    });
            })
            ->sum('balance_amount');

        $invoiceRows = self::latestInvoices($clientId, $matterId);

        $officeReceipts = self::receiptsQuery($clientId, $matterId)
            ->where('receipt_type', 2)
            ->orderByRaw("CASE WHEN invoice_no IS NULL OR invoice_no = '' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->get();

        $allRows = $receiptsLists
            ->concat($officeReceipts)
            ->concat($invoiceRows);

        self::attachDocumentUrls($allRows, $crmClientRef);

        return [
            'client_selected_matter_id' => $matterId,
            'calculated_balance' => $calculatedBalance,
            'receipts_lists' => $receiptsLists,
            'latest_outstanding_balance' => $latestOutstandingBalance,
            'receipts_lists_invoice' => $invoiceRows,
            'receipts_lists_office' => $officeReceipts,
        ];
    }

    private static function resolveMatterId(int $clientId, ?string $matterRefNo): ?int
    {
        $matterCount = ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->count();

        if (! (($matterRefNo !== null && $matterRefNo !== '') || $matterCount > 0)) {
            return null;
        }

        if ($matterRefNo !== null && $matterRefNo !== '') {
            $matter = ClientMatter::query()
                ->select('id')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } else {
            $matter = ClientMatter::query()
                ->select('id')
                ->where('client_id', $clientId)
                ->orderBy('id', 'desc')
                ->first();
        }

        return $matter?->id;
    }

    private static function receiptsQuery(int $clientId, ?int $matterId): Builder
    {
        return AccountClientReceipt::query()
            ->where('client_id', $clientId)
            ->where(function ($query) use ($matterId) {
                if ($matterId !== null) {
                    $query->where('client_matter_id', $matterId);
                } else {
                    $query->whereNull('client_matter_id');
                }
            });
    }

    /**
     * @return array<int, object>
     */
    private static function latestInvoices(int $clientId, ?int $matterId): array
    {
        $connection = AccountClientReceipt::query()->getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            if ($matterId !== null) {
                return $connection->select('
                    SELECT DISTINCT ON (receipt_id) *
                    FROM account_client_receipts
                    WHERE client_matter_id = ?
                    AND client_id = ?
                    AND receipt_type = 3
                    ORDER BY receipt_id, id DESC
                ', [$matterId, $clientId]);
            }

            return $connection->select('
                SELECT DISTINCT ON (receipt_id) *
                FROM account_client_receipts
                WHERE client_matter_id IS NULL
                AND client_id = ?
                AND receipt_type = 3
                ORDER BY receipt_id, id DESC
            ', [$clientId]);
        }

        $ids = self::receiptsQuery($clientId, $matterId)
            ->where('receipt_type', 3)
            ->selectRaw('MAX(id) as id')
            ->groupBy('receipt_id')
            ->pluck('id');

        return AccountClientReceipt::query()
            ->whereIn('id', $ids)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * @param  iterable<int, object>  $rows
     */
    private static function attachDocumentUrls(iterable $rows, string $crmClientRef): void
    {
        $docIds = [];
        $matterIds = [];
        foreach ($rows as $row) {
            $docId = (int) ($row->uploaded_doc_id ?? 0);
            if ($docId > 0) {
                $docIds[] = $docId;
            }
            $matterId = (int) ($row->client_matter_id ?? 0);
            if ($matterId > 0) {
                $matterIds[] = $matterId;
            }
        }

        $documents = collect();
        if ($docIds !== []) {
            $documents = Document::query()
                ->whereIn('id', array_unique($docIds))
                ->get()
                ->keyBy('id');
        }

        $matters = collect();
        if ($matterIds !== []) {
            $matters = ClientMatter::query()
                ->select('id', 'client_unique_matter_no')
                ->whereIn('id', array_unique($matterIds))
                ->get()
                ->keyBy('id');
        }

        foreach ($rows as $row) {
            $docId = (int) ($row->uploaded_doc_id ?? 0);
            $document = $docId > 0 ? $documents->get($docId) : null;
            $url = null;
            if ($document && ! empty($document->myfile)) {
                if (filter_var($document->myfile, FILTER_VALIDATE_URL)) {
                    $url = $document->myfile;
                } else {
                    $matter = $matters->get((int) ($row->client_matter_id ?? 0));
                    $matterRef = $matter?->client_unique_matter_no;
                    $filePath = $matterRef
                        ? $crmClientRef.'/'.$matterRef.'/accounts/'.$document->myfile
                        : $crmClientRef.'/accounts/'.$document->myfile;
                    /** @var FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    $url = $disk->url($filePath);
                }
            }
            $row->account_inline_doc_url = $url;
            $row->account_dropdown_doc_url = $url;
        }
    }
}
