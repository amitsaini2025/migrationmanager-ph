<?php

namespace App\Http\Controllers\CRM;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Services\EmailMatchingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

/**
 * Smart Email Import
 *
 * Three-stage workflow:
 *  1. ANALYZE  — parse .msg via Python, run matching, write temp files to local FS only
 *  2. REVIEW   — UI (handled by blade + JS)
 *  3. CONFIRM  — save via importEmailFromContext() → processEmailFile(), then delete temps
 *
 * No new database tables or migrations are required.
 */
class SmartEmailImportController extends EmailUploadController
{
    private const STAGING_DISK    = 'local';
    private const STAGING_PREFIX  = 'smart-email-import';
    private const BATCH_TTL_HOURS = 24;
    private const MAX_FILES       = 20;

    private EmailMatchingService $matcher;

    public function __construct(EmailMatchingService $matcher)
    {
        parent::__construct();
        $this->matcher = $matcher;
    }

    // -------------------------------------------------------------------------
    // GET /emails/smart-import
    // -------------------------------------------------------------------------

    public function index(): \Illuminate\View\View
    {
        return view('crm.emails.smart-import');
    }

    // -------------------------------------------------------------------------
    // POST /emails/smart-import/analyze
    // -------------------------------------------------------------------------

    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_files'   => 'required|array|max:' . self::MAX_FILES,
            'email_files.*' => 'file|max:' . $this->emailUploadMaxKb() . '|mimes:' . implode(',', $this->allowedEmailUploadExtensions()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId     = (int) Auth::id();
        $batchToken = (string) Str::uuid();

        // Prune stale batches before writing a new one
        $this->pruneOldBatches($userId);

        $stagingDir = self::STAGING_PREFIX . "/{$userId}/{$batchToken}";

        $items = [];

        foreach ((array) $request->file('email_files') as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $itemId   = (string) Str::uuid();
            $filename = $file->getClientOriginalName();

            try {
                // Parse via Python
                $parsed = $this->parseEmailWithPython($file);

                $parseFailed = ! is_array($parsed)
                    || ! empty($parsed['error'])
                    || (isset($parsed['success']) && ! $parsed['success']);

                if ($parseFailed) {
                    $items[] = [
                        'item_id'          => $itemId,
                        'filename'         => $filename,
                        'status'           => 'parse_error',
                        'error'            => is_array($parsed) ? ($parsed['error'] ?? 'Parse failed') : 'Parse failed',
                        'subject'          => '',
                        'from'             => '',
                        'to'               => '',
                        'date'             => '',
                        'snippet'          => '',
                        'attachment_count' => 0,
                        'suggestions'      => [],
                        'suggested_mail_type' => 'inbox',
                    ];
                    continue;
                }

                // Stage the original email file (.msg or .eml)
                $stagedPath = $this->stagedEmailPath($stagingDir, $itemId, $filename);
                Storage::disk(self::STAGING_DISK)->put(
                    $stagedPath,
                    file_get_contents($file->getPathname())
                );

                // Run matching
                $matchResult = $this->matcher->suggest($parsed, Auth::user());

                $item = [
                    'item_id'             => $itemId,
                    'filename'            => $filename,
                    'staged_path'         => $stagedPath,
                    'subject'             => (string) ($parsed['subject'] ?? ''),
                    'from'                => (string) ($parsed['sender_email'] ?? ''),
                    'to'                  => implode(', ', (array) ($parsed['recipients'] ?? [])),
                    'date'                => (string) ($parsed['sent_date'] ?? $parsed['received_date'] ?? ''),
                    'snippet'             => mb_substr((string) ($parsed['text_content'] ?? ''), 0, 300),
                    'attachment_count'    => count((array) ($parsed['attachments'] ?? [])),
                    'suggestions'         => $matchResult['suggestions'],
                    'suggested_mail_type' => $matchResult['suggested_mail_type'],
                    'status'              => 'pending',
                ];

                $items[] = $item;

            } catch (\Exception $e) {
                Log::error('SmartEmailImport: analyze item failed', [
                    'filename' => $filename,
                    'error'    => $e->getMessage(),
                ]);
                $items[] = [
                    'item_id'             => $itemId,
                    'filename'            => $filename,
                    'status'              => 'parse_error',
                    'error'               => 'Processing failed: ' . $e->getMessage(),
                    'subject'             => '',
                    'from'                => '',
                    'to'                  => '',
                    'date'                => '',
                    'snippet'             => '',
                    'attachment_count'    => 0,
                    'suggestions'         => [],
                    'suggested_mail_type' => 'inbox',
                ];
            }
        }

        // Write meta.json for the batch
        $successfulItems = array_filter($items, fn ($i) => $i['status'] === 'pending');
        if ($successfulItems !== []) {
            $this->writeMeta($stagingDir, $batchToken, $userId, $items);
        }

        return response()->json([
            'success'     => true,
            'batch_token' => $batchToken,
            'items'       => array_values($items),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /emails/smart-import/confirm
    // -------------------------------------------------------------------------

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_token'                  => ['required', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
            'assignments'                  => 'required|array|min:1',
            'assignments.*.item_id'        => ['required', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
            'assignments.*.client_id'      => 'required|integer|min:1',
            'assignments.*.client_matter_id' => 'required|integer|min:1',
            'assignments.*.mail_type'      => 'required|in:inbox,sent',
            'assignments.*.record_type'    => 'required|in:client,lead',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId     = (int) Auth::id();
        $batchToken = $request->input('batch_token');
        $stagingDir = self::STAGING_PREFIX . "/{$userId}/{$batchToken}";

        // Load and verify batch ownership
        $meta = $this->readMeta($stagingDir);
        if (! $meta) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found or expired. Please re-upload.',
            ], 404);
        }

        if ((int) ($meta['user_id'] ?? 0) !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $savedCount   = 0;
        $savedItemIds = [];
        $failed       = [];

        foreach ($request->input('assignments') as $assignment) {
            $itemId         = $assignment['item_id'];
            $clientId       = (int) $assignment['client_id'];
            $clientMatterId = (int) $assignment['client_matter_id'];
            $mailType       = $assignment['mail_type'];
            $recordType     = $assignment['record_type'];

            // Verify item exists in batch and is still pending
            if (! isset($meta['items'][$itemId])) {
                $failed[] = ['item_id' => $itemId, 'filename' => '?', 'error' => 'Item not found in batch.'];
                continue;
            }

            $metaItem = $meta['items'][$itemId];
            $filename = $metaItem['filename'] ?? $itemId;

            if (($metaItem['status'] ?? '') === 'saved') {
                $failed[] = ['item_id' => $itemId, 'filename' => $filename, 'error' => 'Already imported.'];
                continue;
            }

            try {
                // Verify matter belongs to client
                $matterBelongsToClient = ClientMatter::where('id', $clientMatterId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (! $matterBelongsToClient) {
                    throw new \RuntimeException('The selected matter does not belong to the selected client.');
                }

                // Staff access check
                $this->ensureCrmRecordAccess($clientId);

                // Recreate UploadedFile from staged email file
                $stagedPath = $meta['items'][$itemId]['staged_path']
                    ?? $this->stagedEmailPath($stagingDir, $itemId, $filename);
                $absPath    = Storage::disk(self::STAGING_DISK)->path($stagedPath);

                if (! file_exists($absPath)) {
                    throw new \RuntimeException('Staged file not found. The batch may have expired.');
                }

                $uploadedFile = new UploadedFile(
                    $absPath,
                    $filename,
                    'application/octet-stream',
                    null,
                    true  // test mode — skips is_uploaded_file() check
                );

                // Delegate to existing pipeline
                $result = $this->importEmailFromContext(
                    $uploadedFile,
                    $clientId,
                    $mailType,
                    $clientMatterId,
                    $recordType
                );

                if ($result['success']) {
                    // Delete staged file
                    Storage::disk(self::STAGING_DISK)->delete($stagedPath);

                    // Update meta
                    $meta['items'][$itemId]['status'] = 'saved';

                    $savedCount++;
                    $savedItemIds[] = $itemId;
                } else {
                    $meta['items'][$itemId]['status'] = 'failed';
                    $failed[] = [
                        'item_id'  => $itemId,
                        'filename' => $filename,
                        'error'    => $result['error'] ?? 'Import failed.',
                    ];
                }

            } catch (HttpResponseException $e) {
                $meta['items'][$itemId]['status'] = 'failed';
                $failed[] = [
                    'item_id'  => $itemId,
                    'filename' => $filename,
                    'error'    => 'You do not have access to this client.',
                ];
            } catch (\Exception $e) {
                Log::error('SmartEmailImport: confirm item failed', [
                    'item_id'  => $itemId,
                    'filename' => $filename,
                    'error'    => $e->getMessage(),
                ]);
                $meta['items'][$itemId]['status'] = 'failed';
                $failed[] = [
                    'item_id'  => $itemId,
                    'filename' => $filename,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        // Persist updated meta
        $this->writeMeta($stagingDir, $batchToken, $userId, array_values($meta['items']));

        // Remove batch folder when no staged email files remain
        $remainingFiles = Storage::disk(self::STAGING_DISK)->files($stagingDir);
        $allowedExts = $this->allowedEmailUploadExtensions();
        $remainingFiles = array_filter($remainingFiles, function ($f) use ($allowedExts) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));

            return in_array($ext, $allowedExts, true);
        });
        if (empty($remainingFiles)) {
            Storage::disk(self::STAGING_DISK)->deleteDirectory($stagingDir);
        }

        return response()->json([
            'success'        => $savedCount > 0,
            'saved'          => $savedCount,
            'saved_item_ids' => $savedItemIds,
            'failed'         => $failed,
        ]);
    }

    // -------------------------------------------------------------------------
    // Staging helpers
    // -------------------------------------------------------------------------

    private function writeMeta(string $stagingDir, string $batchToken, int $userId, array $items): void
    {
        // Items keyed by item_id for easy lookup on confirm
        $keyedItems = [];
        foreach ($items as $item) {
            if (isset($item['item_id'])) {
                $keyedItems[$item['item_id']] = $item;
            }
        }

        $meta = [
            'batch_token' => $batchToken,
            'user_id'     => $userId,
            'created_at'  => now()->toIso8601String(),
            'items'       => $keyedItems,
        ];

        Storage::disk(self::STAGING_DISK)->put(
            "{$stagingDir}/meta.json",
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function readMeta(string $stagingDir): ?array
    {
        $metaPath = "{$stagingDir}/meta.json";
        if (! Storage::disk(self::STAGING_DISK)->exists($metaPath)) {
            return null;
        }

        $contents = Storage::disk(self::STAGING_DISK)->get($metaPath);
        if (! $contents) {
            return null;
        }

        return json_decode($contents, true);
    }

    /**
     * Delete batch folders older than BATCH_TTL_HOURS for this user.
     */
    private function pruneOldBatches(int $userId): void
    {
        try {
            $userDir = self::STAGING_PREFIX . "/{$userId}";
            if (! Storage::disk(self::STAGING_DISK)->exists($userDir)) {
                return;
            }

            $batchDirs = Storage::disk(self::STAGING_DISK)->directories($userDir);
            $cutoff    = now()->subHours(self::BATCH_TTL_HOURS)->timestamp;

            foreach ($batchDirs as $dir) {
                $metaPath = "{$dir}/meta.json";
                if (! Storage::disk(self::STAGING_DISK)->exists($metaPath)) {
                    // No meta = orphaned; remove
                    Storage::disk(self::STAGING_DISK)->deleteDirectory($dir);
                    continue;
                }

                $lastModified = Storage::disk(self::STAGING_DISK)->lastModified($metaPath);
                if ($lastModified < $cutoff) {
                    Storage::disk(self::STAGING_DISK)->deleteDirectory($dir);
                }
            }
        } catch (\Exception $e) {
            Log::warning('SmartEmailImport: prune failed', ['error' => $e->getMessage()]);
        }
    }
}
