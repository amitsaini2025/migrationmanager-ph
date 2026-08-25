<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Collection;

/**
 * Document tab query helpers for client detail fragments.
 * One Document query per tab (eager staff); blades group by category in memory.
 */
final class ClientDetailDocumentsTab
{
    /**
     * Active personal documents for a client, keyed by folder_name (string).
     *
     * @return Collection<string, Collection<int, Document>>
     */
    public static function personalDocumentsByFolder(int $clientId): Collection
    {
        return Document::with('staff')
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->where('doc_type', 'personal')
            ->where('type', 'client')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->groupBy(static fn (Document $doc): string => (string) ($doc->folder_name ?? ''));
    }

    /**
     * Active visa documents for a client, keyed by folder_name (string).
     * Includes signers for Form 956 / signing UI.
     *
     * @return Collection<string, Collection<int, Document>>
     */
    public static function visaDocumentsByFolder(int $clientId): Collection
    {
        return Document::with(['staff', 'signers'])
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->where('doc_type', 'visa')
            ->where('type', 'client')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->groupBy(static fn (Document $doc): string => (string) ($doc->folder_name ?? ''));
    }

    /**
     * Not-used personal/visa/nomination documents for a client (eager staff).
     *
     * @return Collection<int, Document>
     */
    public static function notUsedDocuments(int $clientId): Collection
    {
        return Document::with('staff')
            ->where('client_id', $clientId)
            ->where('not_used_doc', 1)
            ->where('type', 'client')
            ->where(function ($query) {
                $query->where('doc_type', 'personal')
                    ->orWhere('doc_type', 'visa')
                    ->orWhere('doc_type', 'nomination');
            })
            ->orderBy('type', 'DESC')
            ->get();
    }
}
