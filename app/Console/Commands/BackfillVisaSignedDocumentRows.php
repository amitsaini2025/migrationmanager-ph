<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates missing visa/nomination "companion" rows (checklist = {parent}_signed) for parent documents
 * that are already status signed with signed_doc_link, mirroring PublicDocumentController.
 */
class BackfillVisaSignedDocumentRows extends Command
{
    protected $signature = 'documents:backfill-visa-signed-rows
                            {--dry-run : List candidates without inserting}
                            {--id=* : Limit to parent document id(s), comma-separated or repeated --id=}
                            {--doc-type=visa : Limit to visa and/or nomination (comma-separated). Default: visa}';

    protected $description = 'Backfill missing visa/nomination *_signed document rows for signed parents';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = $this->normalizeIds((array) $this->option('id'));
        $docTypes = $this->normalizeDocTypes($this->option('doc-type'));

        if ($dryRun) {
            $this->warn('Dry run: no rows will be inserted.');
        }

        $this->info('Backfilling doc_type(s): '.implode(', ', $docTypes));

        $query = Document::query()
            ->whereRaw(
                "LOWER(TRIM(COALESCE(doc_type, ''))) IN (".implode(',', array_fill(0, count($docTypes), '?')).")",
                $docTypes
            )
            ->where('status', 'signed')
            ->whereNotNull('signed_doc_link')
            ->where('signed_doc_link', '!=', '')
            ->whereNotNull('client_id')
            ->whereNull('not_used_doc');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $parents = $query->orderBy('id')->get();

        $parents = $parents->filter(function (Document $d) {
            $folder = trim((string) ($d->folder_name ?? ''));
            if ($folder === '') {
                return false;
            }
            $base = trim((string) str_replace("\0", '', (string) ($d->checklist ?? '')));
            if ($base === '') {
                $base = 'Document';
            }
            if (str_ends_with($base, '_signed')) {
                return false;
            }
            $signedChecklist = $base . '_signed';
            if ($this->activeSignedCopyExists($d, $signedChecklist)) {
                return false;
            }
            return true;
        })->values();

        if ($parents->isEmpty()) {
            $this->info('No parent documents need a _signed companion row.');
            return self::SUCCESS;
        }

        $this->info('Found '.$parents->count().' parent document(s) missing an active _signed row.');

        $created = 0;
        $failed = 0;

        foreach ($parents as $parent) {
            $signedPdfUrl = trim((string) $parent->signed_doc_link);
            $baseChecklist = trim((string) str_replace("\0", '', (string) ($parent->checklist ?? '')));
            if ($baseChecklist === '') {
                $baseChecklist = 'Document';
            }
            $signedChecklist = $baseChecklist . '_signed';
            $parentType = strtolower(trim((string) ($parent->doc_type ?? '')));

            $this->line("- Parent id={$parent->id} doc_type={$parentType} checklist=".json_encode($parent->checklist)." -> ".json_encode($signedChecklist));

            if ($dryRun) {
                continue;
            }

            try {
                DB::transaction(function () use ($parent, $signedPdfUrl, $signedChecklist, &$created) {
                    if ($this->activeSignedCopyExists($parent, $signedChecklist)) {
                        return;
                    }
                    $signedDoc = $this->buildSignedCopyModel($parent, $signedPdfUrl, $signedChecklist);
                    $signedDoc->save();
                    $created++;
                    Log::info('Backfill created _signed companion row', [
                        'parent_id' => $parent->id,
                        'signed_id' => $signedDoc->id,
                        'doc_type' => $signedDoc->doc_type,
                    ]);
                });
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Backfill failed _signed companion row', [
                    'parent_id' => $parent->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed parent id={$parent->id}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info('Dry run finished. Re-run without --dry-run to insert.');
        } else {
            $this->info("Done. Created: {$created}, failed: {$failed}.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeDocTypes(mixed $raw): array
    {
        $allowed = ['visa', 'nomination'];
        if ($raw === null || $raw === '') {
            return ['visa'];
        }
        $parts = preg_split('/\s*,\s*/', strtolower(trim((string) $raw)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $types = [];
        foreach ($parts as $part) {
            if (in_array($part, $allowed, true)) {
                $types[$part] = $part;
            }
        }
        return $types !== [] ? array_values($types) : ['visa'];
    }

    /**
     * @param  array<int, string|null>  $raw
     * @return array<int, int>
     */
    private function normalizeIds(array $raw): array
    {
        $ids = [];
        foreach ($raw as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', (string) $part, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        return array_values($ids);
    }

    private function activeSignedCopyExists(Document $parent, string $signedChecklist): bool
    {
        return Document::query()
            ->where('client_id', $parent->client_id)
            ->where('folder_name', $parent->folder_name)
            ->where('client_matter_id', $parent->client_matter_id)
            ->where('checklist', $signedChecklist)
            ->whereNull('not_used_doc')
            ->exists();
    }

    private function buildSignedCopyModel(Document $document, string $signedPdfUrl, string $signedChecklist): Document
    {
        $parentKey = (string) ($document->myfile_key ?? '');
        $parentStem = preg_replace('/\.(pdf|PDF)$/', '', $document->file_name ?? 'document');
        if ($parentKey !== '' && !preg_match('/^\d+_signed\.pdf$/i', $parentKey)) {
            $signedKey = pathinfo($parentKey, PATHINFO_FILENAME) . '_signed.pdf';
        } else {
            $signedKey = $parentStem . '_signed.pdf';
        }
        $signedKey = preg_replace('/[^a-zA-Z0-9_\-\.\s\$]+/', '_', $signedKey);
        $signedKey = trim($signedKey);
        if ($signedKey === '' || strcasecmp($signedKey, '_signed.pdf') === 0 || strlen($signedKey) > 200) {
            $signedKey = mb_substr(preg_replace('/[^a-zA-Z0-9_\-\.\s\$]+/', '_', $parentStem . '_signed.pdf'), 0, 200);
        }
        if ($signedKey === '' || strcasecmp($signedKey, '_signed.pdf') === 0) {
            $signedKey = $document->id . '_signed.pdf';
        }

        $signedDoc = new Document();
        $signedDoc->checklist = $signedChecklist;
        $signedDoc->file_name = preg_replace('/\.(pdf|PDF)$/', '', $document->file_name ?? 'document') . '_signed';
        $signedDoc->filetype = 'pdf';
        $signedDoc->myfile = $signedPdfUrl;
        $signedDoc->myfile_key = $signedKey;
        $signedDoc->client_id = $document->client_id;
        $signedDoc->client_matter_id = $document->client_matter_id;
        $signedDoc->folder_name = (string) $document->folder_name;
        $docTypeNorm = strtolower(trim((string) ($document->doc_type ?? '')));
        $signedDoc->doc_type = in_array($docTypeNorm, ['visa', 'nomination'], true) ? $docTypeNorm : 'visa';
        $signedDoc->type = 'client';
        $signedDoc->user_id = $document->user_id;
        $signedDoc->status = 'signed';
        $signedDoc->signed_doc_link = $signedPdfUrl;

        return $signedDoc;
    }
}
