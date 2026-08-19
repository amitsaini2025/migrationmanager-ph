<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Collection;

/**
 * Loads document-tab rows in one query per tab, with staff eager-loaded.
 */
final class ClientDetailDocumentsTab
{
    /**
     * Personal documents keyed by folder_name (category id as string).
     *
     * @return Collection<string, Collection<int, Document>>
     */
    public static function personalDocumentsByFolder(int $clientId): Collection
    {
        return self::groupByFolder(self::personalDocuments($clientId));
    }

    /**
     * @return Collection<int, Document>
     */
    public static function personalDocuments(int $clientId): Collection
    {
        return Document::query()
            ->with('staff')
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->where('doc_type', 'personal')
            ->where('type', 'client')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Visa documents keyed by folder_name (category id as string).
     *
     * @return Collection<string, Collection<int, Document>>
     */
    public static function visaDocumentsByFolder(int $clientId): Collection
    {
        return self::groupByFolder(self::visaDocuments($clientId));
    }

    /**
     * @return Collection<int, Document>
     */
    public static function visaDocuments(int $clientId): Collection
    {
        return Document::query()
            ->with(['staff', 'signers'])
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->where('doc_type', 'visa')
            ->where('type', 'client')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Document>
     */
    public static function notUsedDocuments(int $clientId): Collection
    {
        return Document::query()
            ->with('staff')
            ->where('client_id', $clientId)
            ->where('not_used_doc', 1)
            ->where('type', 'client')
            ->whereIn('doc_type', ['personal', 'visa', 'nomination'])
            ->orderByDesc('type')
            ->get();
    }

    /**
     * @param  Collection<string, Collection<int, Document>>  $byFolder
     * @return Collection<int, Document>
     */
    public static function documentsForFolder(Collection $byFolder, mixed $folderName): Collection
    {
        return $byFolder->get((string) $folderName, collect());
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return Collection<string, Collection<int, Document>>
     */
    private static function groupByFolder(Collection $documents): Collection
    {
        return $documents->groupBy(static fn (Document $document): string => (string) ($document->folder_name ?? ''));
    }
}
