<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\Admin;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Traits\LogsClientActivity;

/**
 * Modern Email Upload Controller
 * 
 * Uses Python microservice for email parsing instead of legacy PEAR libraries.
 * This provides better performance, modern code, and PHP 8.2+ compatibility.
 */
class EmailUploadController extends Controller
{
    use EnsuresCrmRecordAccess;
    use LogsClientActivity;

    /**
     * Python service configuration
     */
    protected $pythonServiceUrl;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->pythonServiceUrl = env('PYTHON_SERVICE_URL', 'http://127.0.0.1:5001');
    }

    /**
     * Allowed upload extensions from config (e.g. msg, eml).
     *
     * @return list<string>
     */
    protected function allowedEmailUploadExtensions(): array
    {
        $exts = config('crm.email_upload_allowed_extensions', ['msg']);

        return array_values(array_filter(array_map(
            static fn ($ext) => strtolower(ltrim((string) $ext, '.')),
            is_array($exts) ? $exts : ['msg']
        )));
    }

    protected function emailUploadMaxKb(): int
    {
        return max(1, (int) config('crm.email_upload_max_kb', 30720));
    }

    /**
     * @return array<string, mixed>
     */
    protected function emailUploadValidationRules(): array
    {
        $maxKb = $this->emailUploadMaxKb();
        $mimes = implode(',', $this->allowedEmailUploadExtensions());

        return [
            'email_files' => 'required',
            'email_files.*' => "file|max:{$maxKb}|mimes:{$mimes}",
            'client_id' => 'required',
            'type' => 'required|in:client,lead',
            'attachment_storage' => 'nullable|string',
            'force_upload' => 'nullable|boolean',
        ];
    }

    protected function allowedExtensionsLabel(): string
    {
        return implode(', ', array_map(
            static fn ($ext) => '.' . $ext,
            $this->allowedEmailUploadExtensions()
        ));
    }

    protected function emailUploadExtensionFromFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = $this->allowedEmailUploadExtensions();

        return in_array($ext, $allowed, true) ? $ext : 'msg';
    }

    protected function stagedEmailPath(string $stagingDir, string $itemId, string $filename): string
    {
        return $stagingDir . '/' . $itemId . '.' . $this->emailUploadExtensionFromFilename($filename);
    }

    /**
     * Preview attachment metadata from an email file before upload (no S3 save).
     */
    public function previewEmailAttachments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->emailUploadValidationRules());
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $file = $request->file('email_files')[0] ?? null;
            if (! $file) {
                return response()->json([
                    'status' => false,
                    'message' => 'No file uploaded',
                ], 400);
            }

            $parsedData = $this->parseEmailMetadataWithPython($file);
            if (! $parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && ! $parsedData['success'])) {
                return response()->json([
                    'status' => false,
                    'message' => $parsedData['error'] ?? 'Failed to parse email',
                ], 400);
            }

            $attachments = [];
            foreach ($parsedData['attachments'] ?? [] as $index => $attachmentData) {
                if (! empty($attachmentData['is_inline'])) {
                    continue;
                }
                $filename = $attachmentData['filename'] ?? ('attachment_' . ($index + 1));
                $attachments[] = [
                    'index' => $index,
                    'filename' => $filename,
                    'display_name' => $attachmentData['display_name'] ?? $filename,
                    'file_size' => $attachmentData['file_size'] ?? $attachmentData['size'] ?? 0,
                    'content_type' => $attachmentData['content_type'] ?? 'application/octet-stream',
                ];
            }

            return response()->json([
                'status' => true,
                'attachments' => $attachments,
                'has_attachments' => count($attachments) > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Preview email attachments error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to preview attachments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Personal + visa document categories for the attachment storage modal.
     */
    public function getAttachmentDocumentCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer|min:1',
            'client_matter_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $clientId = (int) $request->client_id;
        $matterId = $request->filled('client_matter_id') ? (int) $request->client_matter_id : null;
        $this->ensureCrmRecordAccess($clientId);

        $personal = PersonalDocumentType::query()
            ->select('id', 'title')
            ->where('status', 1)
            ->where(function ($query) use ($clientId) {
                $query->whereNull('client_id')->orWhere('client_id', $clientId);
            })
            ->whereIn('type', ['personal', 'both'])
            ->orderBy('id')
            ->get()
            ->map(static fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->title,
                'category_name' => $cat->title,
                'doc_type' => 'personal',
            ])
            ->values();

        $visa = collect();
        if ($matterId) {
            $visa = VisaDocumentType::query()
                ->select('id', 'title')
                ->where('status', 1)
                ->where(function ($query) use ($clientId, $matterId) {
                    $query->where(function ($q) {
                        $q->whereNull('client_id')->whereNull('client_matter_id');
                    })
                        ->orWhere(function ($q) use ($clientId) {
                            $q->where('client_id', $clientId)->whereNull('client_matter_id');
                        })
                        ->orWhere(function ($q) use ($clientId, $matterId) {
                            $q->where('client_id', $clientId)->where('client_matter_id', $matterId);
                        });
                })
                ->orderBy('id')
                ->get()
                ->map(static fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->title,
                    'category_name' => $cat->title,
                    'doc_type' => 'visa',
                ])
                ->values();
        }

        $categories = $personal->concat($visa)->values();

        return response()->json([
            'status' => true,
            'categories' => $categories,
            'personal' => $personal,
            'visa' => $visa,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function parseAttachmentStorageMap(Request $request): array
    {
        if (! $request->filled('attachment_storage')) {
            return [];
        }

        $decoded = json_decode((string) $request->input('attachment_storage'), true);
        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['original_filename'] ?? $item['filename'] ?? null;
            if ($key) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    protected function sanitizeAttachmentDisplayName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9_\-\.\s\$\(\),&+]/', '_', $name);
        $name = preg_replace('/_+/', '_', trim((string) $name, '_'));

        return $name !== '' ? $name : 'attachment';
    }

    /**
     * @return array{file_path: string, s3_key: string, file_size: int, display_name: string}|null
     */
    protected function saveEmailAttachmentAsDocument(
        array $attachmentData,
        array $storageConfig,
        string $clientUniqueId,
        int $clientId,
        string $recordType,
        string $decodedData,
        ?int $matterId = null
    ): ?array {
        if (($storageConfig['storage_type'] ?? '') !== 'documents') {
            return null;
        }

        $categoryId = (int) ($storageConfig['category_id'] ?? 0);
        if ($categoryId <= 0) {
            return null;
        }

        $docType = (string) ($storageConfig['doc_type'] ?? 'personal');
        if (! in_array($docType, ['personal', 'visa'], true)) {
            $docType = 'personal';
        }

        if ($docType === 'visa' && ! $this->visaDocumentCategoryAccessible($categoryId, $clientId, $matterId)) {
            throw new \Exception('Selected visa document category is not available for this client and matter.');
        }

        $originalFilename = $attachmentData['filename'] ?? 'attachment';
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $customStem = $this->sanitizeAttachmentDisplayName(
            (string) ($storageConfig['file_name'] ?? pathinfo($originalFilename, PATHINFO_FILENAME))
        );
        $displayName = $extension ? ($customStem . '.' . $extension) : $customStem;

        $sanitizedClientId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);
        $uniqueFileName = time() . '_' . uniqid() . '_' . $this->sanitizeFilename($displayName);
        $s3Folder = $docType === 'visa' ? 'visa' : 'documents';
        $filePath = $sanitizedClientId . '/' . $s3Folder . '/' . $uniqueFileName;

        $uploadSuccess = Storage::disk('s3')->put($filePath, $decodedData);
        if (! $uploadSuccess) {
            throw new \Exception('Failed to upload attachment to document storage.');
        }

        $fileUrl = Storage::disk('s3')->url($filePath);
        $fileSize = strlen($decodedData);

        $document = new Document();
        $document->file_name = $customStem;
        $document->filetype = $extension ?: pathinfo($displayName, PATHINFO_EXTENSION);
        $document->user_id = Auth::user()->id;
        $document->myfile = $fileUrl;
        $document->myfile_key = $uniqueFileName;
        $document->client_id = $clientId;
        $document->type = $recordType;
        $document->file_size = $fileSize;
        $document->doc_type = $docType;
        $document->folder_name = (string) $categoryId;
        $document->checklist = $customStem;
        if ($docType === 'visa') {
            $document->client_matter_id = $matterId;
        } elseif ($matterId) {
            $document->client_matter_id = $matterId;
        }
        $document->save();

        return [
            'file_path' => $fileUrl,
            's3_key' => $filePath,
            'file_size' => $fileSize,
            'display_name' => $displayName,
        ];
    }

    protected function visaDocumentCategoryAccessible(int $categoryId, int $clientId, ?int $matterId): bool
    {
        if ($matterId === null || $matterId <= 0) {
            return false;
        }

        return VisaDocumentType::query()
            ->where('id', $categoryId)
            ->where('status', 1)
            ->where(function ($query) use ($clientId, $matterId) {
                $query->where(function ($q) {
                    $q->whereNull('client_id')->whereNull('client_matter_id');
                })
                    ->orWhere(function ($q) use ($clientId) {
                        $q->where('client_id', $clientId)->whereNull('client_matter_id');
                    })
                    ->orWhere(function ($q) use ($clientId, $matterId) {
                        $q->where('client_id', $clientId)->where('client_matter_id', $matterId);
                    });
            })
            ->exists();
    }

    /**
     * Upload and process inbox emails using Python microservice
     * 
     * Modern replacement for uploadfetchmail method
     */
    public function uploadInboxEmails(Request $request)
    {
        try {
            // Validate file input
            $validator = Validator::make($request->all(), $this->emailUploadValidationRules());

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $clientId = $request->client_id;
            $clientInfo = Admin::select('client_id')->where('id', $clientId)->first();
            $clientUniqueId = !empty($clientInfo) ? $clientInfo->client_id : "";

            if (!$request->hasfile('email_files')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No files uploaded',
                ], 400);
            }

            // Check maximum file limit (10 emails max)
            $emailFiles = $request->file('email_files');
            $fileCount = is_array($emailFiles) ? count($emailFiles) : 0;
            
            if ($fileCount > 10) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum 10 email files allowed per upload. Please select 10 or fewer files.',
                    'uploaded' => 0,
                    'failed' => 0,
                    'errors' => []
                ], 422);
            }

            $uploadedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($request->file('email_files') as $file) {
                try {
                    $result = $this->processEmailFile($file, $clientId, $clientUniqueId, 'inbox', $request);
                    
                    if ($result['success']) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = $this->formatUploadFailureResult(
                            $result,
                            $file->getClientOriginalName()
                        );
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $fileName = $file->getClientOriginalName();
                    $errorMsg = $e->getMessage();
                    
                    // Extract user-friendly error if available
                    $userError = $errorMsg;
                    if (is_array($errorMsg) && isset($errorMsg['error'])) {
                        $userError = $errorMsg['error'];
                    }
                    
                    $errors[] = [
                        'filename' => $fileName,
                        'error' => $userError,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType()
                    ];
                    Log::error('Email upload error', [
                        'file' => $fileName,
                        'file_size' => $file->getSize(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Build user-friendly message
            $message = '';
            $status = true;
            
            if ($uploadedCount > 0 && $failedCount == 0) {
                $message = "Successfully uploaded {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '');
                $status = true;
            } elseif ($uploadedCount > 0 && $failedCount > 0) {
                $message = "Partially successful: {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '') . " uploaded, {$failedCount} failed";
                $status = true; // Partial success is still considered success
            } elseif ($failedCount > 0) {
                $message = "Upload failed: {$failedCount} email" . ($failedCount > 1 ? 's' : '') . " could not be processed";
                $status = false;
            } else {
                $message = "No emails were processed";
                $status = false;
            }
            
            // Return response with proper status
            return response()->json([
                'status' => $status,
                'message' => $message,
                'uploaded' => $uploadedCount,
                'failed' => $failedCount,
                'errors' => $errors,
                'total_files' => $uploadedCount + $failedCount
            ], $status ? 200 : 400);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Make error messages more user-friendly
            if (strpos($errorMessage, 'Validation failed') !== false) {
                $errorMessage = 'File validation failed. Please ensure you are uploading ' . $this->allowedExtensionsLabel() . ' files only (max 30MB each).';
            } elseif (strpos($errorMessage, 'No files uploaded') !== false) {
                $errorMessage = 'No files were selected for upload. Please select at least one email file (' . $this->allowedExtensionsLabel() . ').';
            }
            
            Log::error('Email upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed: ' . $errorMessage,
                'technical_error' => $e->getMessage() // Include original for debugging
            ], 500);
        }
    }

    /**
     * Upload and process sent emails using Python microservice
     */
    public function uploadSentEmails(Request $request)
    {
        try {
            // Validate file input
            $validator = Validator::make($request->all(), $this->emailUploadValidationRules());

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $clientId = $request->client_id;
            $clientInfo = Admin::select('client_id')->where('id', $clientId)->first();
            $clientUniqueId = !empty($clientInfo) ? $clientInfo->client_id : "";

            if (!$request->hasfile('email_files')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No files uploaded',
                ], 400);
            }

            // Check maximum file limit (10 emails max)
            $emailFiles = $request->file('email_files');
            $fileCount = is_array($emailFiles) ? count($emailFiles) : 0;
            
            if ($fileCount > 10) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum 10 email files allowed per upload. Please select 10 or fewer files.',
                    'uploaded' => 0,
                    'failed' => 0,
                    'errors' => []
                ], 422);
            }

            $uploadedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($request->file('email_files') as $file) {
                try {
                    $result = $this->processEmailFile($file, $clientId, $clientUniqueId, 'sent', $request);
                    
                    if ($result['success']) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = $this->formatUploadFailureResult(
                            $result,
                            $file->getClientOriginalName()
                        );
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $fileName = $file->getClientOriginalName();
                    $errorMsg = $e->getMessage();
                    
                    // Extract user-friendly error if available
                    $userError = $errorMsg;
                    if (is_array($errorMsg) && isset($errorMsg['error'])) {
                        $userError = $errorMsg['error'];
                    }
                    
                    $errors[] = [
                        'filename' => $fileName,
                        'error' => $userError,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType()
                    ];
                    Log::error('Email upload error', [
                        'file' => $fileName,
                        'file_size' => $file->getSize(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Build user-friendly message
            $message = '';
            $status = true;
            
            if ($uploadedCount > 0 && $failedCount == 0) {
                $message = "Successfully uploaded {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '');
                $status = true;
            } elseif ($uploadedCount > 0 && $failedCount > 0) {
                $message = "Partially successful: {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '') . " uploaded, {$failedCount} failed";
                $status = true; // Partial success is still considered success
            } elseif ($failedCount > 0) {
                $message = "Upload failed: {$failedCount} email" . ($failedCount > 1 ? 's' : '') . " could not be processed";
                $status = false;
            } else {
                $message = "No emails were processed";
                $status = false;
            }

            return response()->json([
                'status' => $status,
                'message' => $message,
                'uploaded' => $uploadedCount,
                'failed' => $failedCount,
                'errors' => $errors,
                'total_files' => $uploadedCount + $failedCount
            ], $status ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Sent email upload error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function findExistingEmail(
        int $clientId,
        string $mailType,
        string $recordType,
        array $parsedData,
        string $fileHash,
        ?int $matterId = null
    ): ?EmailLog {
        $query = EmailLog::query()
            ->where('client_id', $clientId)
            ->where('mail_body_type', $mailType)
            ->where('type', $recordType)
            ->where('conversion_type', 'conversion_email_fetch');

        if ($matterId) {
            $query->where('client_matter_id', $matterId);
        }

        $byHash = (clone $query)->where('file_hash', $fileHash)->first();
        if ($byHash) {
            return $byHash;
        }

        $messageId = trim((string) ($parsedData['message_id'] ?? ''));
        if ($messageId !== '') {
            $byMessageId = (clone $query)->where('message_id', $messageId)->first();
            if ($byMessageId) {
                return $byMessageId;
            }
        }

        $subject = trim((string) ($parsedData['subject'] ?? ''));
        $sender = trim((string) ($parsedData['sender_email'] ?? ''));
        if ($subject !== '' && $sender !== '') {
            $dupQuery = (clone $query)
                ->where('subject', $subject)
                ->where('from_mail', $sender);

            $sentStorage = $this->sentTimeStorageStringFromParsed($parsedData['sent_date'] ?? null);
            if ($sentStorage) {
                $dupQuery->where('fetch_mail_sent_time', $sentStorage);
            }

            $existing = $dupQuery->first();
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    protected function buildDuplicateErrorMessage(EmailLog $existing): string
    {
        $subject = $existing->subject ?: '(No subject)';
        $from = $existing->from_mail ?: 'Unknown sender';
        $sent = $existing->fetch_mail_sent_time ?: null;

        $message = 'This email already exists.';
        $message .= ' Subject: "' . $subject . '" from ' . $from;
        if ($sent) {
            $message .= ' (sent ' . $sent . ')';
        }

        return $message;
    }

    protected function sentTimeStorageStringFromParsed(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            if (preg_match('/[+-]\d{2}:\d{2}$|Z$/', $dateString)) {
                $sentDate = new \DateTime($dateString);
            } else {
                $sentDate = new \DateTime($dateString, new \DateTimeZone('UTC'));
            }
            $sentDate->setTimezone(new \DateTimeZone('Australia/Melbourne'));

            return $sentDate->format('d/m/Y h:i a');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array{filename: string, error: string, duplicate?: bool, existing?: array<string, mixed>|null}
     */
    protected function formatUploadFailureResult(array $result, string $filename): array
    {
        $entry = [
            'filename' => $filename,
            'error' => $result['error'] ?? 'Unknown error occurred while processing email',
        ];

        if (! empty($result['duplicate'])) {
            $entry['duplicate'] = true;
            $entry['existing'] = $result['existing'] ?? null;
        }

        return $entry;
    }

    /**
     * Process individual email file using Python microservice
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $clientId
     * @param string $clientUniqueId
     * @param string $mailType (inbox|sent)
     * @param Request $request
     * @return array
     */
    protected function processEmailFile($file, $clientId, $clientUniqueId, $mailType, $request)
    {
        try {
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            if ($fileSize <= 0) {
                throw new \Exception('Uploaded file is empty. Save the email from Outlook again and retry.');
            }

            $sanitizedFileName = $this->sanitizeFilename($fileName);
            $uniqueFileName = time() . '-' . $sanitizedFileName;
            $docType = 'conversion_email_fetch';
            $matterId = $mailType === 'sent'
                ? (int) ($request->upload_sent_mail_client_matter_id ?? 0)
                : (int) ($request->upload_inbox_mail_client_matter_id ?? 0);
            $matterId = $matterId > 0 ? $matterId : null;

            // 1. Parse email first (before S3 — enables duplicate check without storage upload)
            $parsedData = $this->parseEmailWithPython($file);

            if (! $parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && ! $parsedData['success'])) {
                throw new \Exception($parsedData['error'] ?? 'Failed to parse email');
            }

            $fileHash = md5_file($file->getRealPath());

            if (! $request->boolean('force_upload')) {
                $existing = $this->findExistingEmail(
                    (int) $clientId,
                    $mailType,
                    (string) $request->type,
                    $parsedData,
                    $fileHash,
                    $matterId
                );

                if ($existing) {
                    return [
                        'success' => false,
                        'duplicate' => true,
                        'error' => $this->buildDuplicateErrorMessage($existing),
                        'existing' => [
                            'id' => $existing->id,
                            'subject' => $existing->subject,
                            'from_mail' => $existing->from_mail,
                            'sent_date' => $existing->fetch_mail_sent_time,
                        ],
                    ];
                }
            }

            // 2. Upload file to S3 (use sanitized filename in path)
            $sanitizedClientId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);
            $filePath = $sanitizedClientId . '/' . $docType . '/' . $mailType . '/' . $uniqueFileName;

            try {
                $fileContents = file_get_contents($file->getPathname());
                if ($fileContents === false) {
                    throw new \Exception('Failed to read email file contents');
                }

                $uploadResult = Storage::disk('s3')->put($filePath, $fileContents);
                if (! $uploadResult) {
                    throw new \Exception('Failed to upload file to storage. Please check storage configuration.');
                }
            } catch (\Exception $s3Exception) {
                Log::error('S3 upload failed for email', [
                    'file' => $fileName,
                    's3_path' => $filePath,
                    'error' => $s3Exception->getMessage(),
                ]);
                throw new \Exception('File storage error: ' . $s3Exception->getMessage());
            }

            try {
                $fileUrl = Storage::disk('s3')->url($filePath);
                if (empty($fileUrl)) {
                    throw new \Exception('Failed to generate file URL');
                }
            } catch (\Exception $urlException) {
                Log::error('S3 URL generation failed', [
                    'file' => $fileName,
                    's3_path' => $filePath,
                    'error' => $urlException->getMessage(),
                ]);
                throw new \Exception('File URL generation error: ' . $urlException->getMessage());
            }

            // 3. Save document record
            $document = new Document();
            $document->file_name = pathinfo($fileName, PATHINFO_FILENAME);
            $document->filetype = pathinfo($fileName, PATHINFO_EXTENSION);
            $document->user_id = Auth::user()->id;
            $document->myfile = $fileUrl;
            $document->myfile_key = $uniqueFileName;
            $document->client_id = $clientId;
            $document->type = $request->type;
            $document->mail_type = $mailType;
            $document->file_size = $fileSize;
            $document->doc_type = $docType;
            $document->client_matter_id = $matterId;
            try {
                $document->save();
            } catch (QueryException $e) {
                Log::error('Failed to save Document record', [
                    'file' => $fileName,
                    'document_data' => $document->toArray(),
                    'error' => $e->getMessage(),
                    'error_info' => $e->errorInfo ?? []
                ]);
                throw new \Exception('Failed to save document record: ' . ($e->errorInfo[2] ?? $e->getMessage()));
            }

            // 4. Save to EmailLog
            $mailReport = new EmailLog();
            $mailReport->user_id = Auth::user()->id;
            $mailReport->from_mail = $parsedData['sender_email'] ?? '';
            $mailReport->to_mail = isset($parsedData['recipients']) && is_array($parsedData['recipients']) 
                ? implode(',', $parsedData['recipients']) 
                : '';
            $mailReport->subject = $parsedData['subject'] ?? '';
            $mailReport->message = $parsedData['html_content'] ?? $parsedData['text_content'] ?? '';
            $mailReport->mail_type = 1;
            $mailReport->type = $request->type; // Set type to 'client' or 'lead' as required by filter
            $mailReport->client_id = $clientId;
            $mailReport->conversion_type = $docType;
            $mailReport->mail_body_type = $mailType;
            $mailReport->uploaded_doc_id = $document->id;
            $mailReport->client_matter_id = $document->client_matter_id;
            
            // Format sent time from Python response
            if (!empty($parsedData['sent_date'])) {
                try {
                    // Parse the ISO date string from Python
                    // If timezone is not specified in the string, treat it as UTC
                    $dateString = $parsedData['sent_date'];
                    
                    // Check if the date string has timezone info
                    // ISO format with timezone: "2025-11-17T18:19:00+00:00" or "2025-11-17T18:19:00Z"
                    // ISO format without timezone: "2025-11-17T18:19:00"
                    if (preg_match('/[+-]\d{2}:\d{2}$|Z$/', $dateString)) {
                        // Has timezone info, parse as-is
                        $sentDate = new \DateTime($dateString);
                    } else {
                        // No timezone info, assume UTC (as Python now sends UTC for naive datetimes)
                        $sentDate = new \DateTime($dateString, new \DateTimeZone('UTC'));
                    }
                    
                    // Convert to Australia/Melbourne timezone for display
                    $sentDate->setTimezone(new \DateTimeZone('Australia/Melbourne'));
                    $mailReport->fetch_mail_sent_time = $sentDate->format('d/m/Y h:i a');
                } catch (\Exception $e) {
                    $mailReport->fetch_mail_sent_time = $parsedData['sent_date'];
                }
            }
            
            // NEW: Add Python AI analysis
            $analysisData = $this->analyzeEmailWithPython($parsedData);
            if ($analysisData && isset($analysisData['success']) && $analysisData['success']) {
                // Ensure JSON fields are properly formatted arrays (not objects or strings)
                $mailReport->python_analysis = is_array($analysisData) ? $analysisData : null;
                $mailReport->sentiment = $analysisData['sentiment'] ?? 'neutral';
                $mailReport->language = $analysisData['language'] ?? null;
                // Ensure these are arrays or null for JSON columns
                $mailReport->security_issues = isset($analysisData['security_issues']) 
                    ? (is_array($analysisData['security_issues']) ? $analysisData['security_issues'] : null)
                    : null;
                $mailReport->thread_info = isset($analysisData['thread_info'])
                    ? (is_array($analysisData['thread_info']) ? $analysisData['thread_info'] : null)
                    : null;
                $mailReport->processed_at = now();
            }
            
            // NEW: Add metadata
            $mailReport->message_id = $parsedData['message_id'] ?? null;
            $mailReport->thread_id = $parsedData['thread_id'] ?? null;
            
            // Handle received_date with timezone awareness
            if (!empty($parsedData['received_date'])) {
                try {
                    $dateString = $parsedData['received_date'];
                    if (preg_match('/[+-]\d{2}:\d{2}$|Z$/', $dateString)) {
                        $receivedDate = new \DateTime($dateString);
                    } else {
                        $receivedDate = new \DateTime($dateString, new \DateTimeZone('UTC'));
                    }
                    // Convert to Australia/Melbourne timezone
                    $receivedDate->setTimezone(new \DateTimeZone('Australia/Melbourne'));
                    $mailReport->received_date = $receivedDate;
                } catch (\Exception $e) {
                    $mailReport->received_date = now();
                }
            } else {
                $mailReport->received_date = now();
            }

            $mailReport->file_hash = $fileHash;

            try {
                $mailReport->save();
            } catch (QueryException $e) {
                Log::error('Failed to save EmailLog record', [
                    'file' => $fileName,
                    'document_id' => $document->id,
                    'email_log_data' => $mailReport->toArray(),
                    'error' => $e->getMessage(),
                    'error_info' => $e->errorInfo ?? [],
                    'sql' => $e->getSql() ?? 'N/A'
                ]);
                throw new \Exception('Failed to save email record: ' . ($e->errorInfo[2] ?? $e->getMessage()));
            }

            // NEW: Save attachments
            if (isset($parsedData['attachments']) && is_array($parsedData['attachments'])) {
                Log::info('Processing attachments', [
                    'count' => count($parsedData['attachments']),
                    'email_log_id' => $mailReport->id
                ]);

                $storageMap = $this->parseAttachmentStorageMap($request);
                $matterIdForDocs = $document->client_matter_id ?? null;
                $attachmentStorageReviewed = $request->has('attachment_storage');

                foreach ($parsedData['attachments'] as $attachmentData) {
                    if (! empty($attachmentData['is_inline'])) {
                        continue;
                    }

                    try {
                        $originalFilename = $attachmentData['filename'] ?? '';
                        $storageConfig = $storageMap[$originalFilename] ?? null;

                        if ($attachmentStorageReviewed && $storageConfig === null) {
                            continue;
                        }

                        if (is_array($storageConfig) && ($storageConfig['storage_type'] ?? '') === 'skip') {
                            continue;
                        }

                        if ($storageConfig && ! empty($storageConfig['file_name'])) {
                            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                            $stem = $this->sanitizeAttachmentDisplayName((string) $storageConfig['file_name']);
                            $attachmentData['display_name'] = $extension ? ($stem . '.' . $extension) : $stem;
                        }

                        $this->saveAttachment(
                            $mailReport->id,
                            $attachmentData,
                            $clientUniqueId,
                            $storageConfig,
                            $request,
                            (int) $clientId,
                            $matterIdForDocs
                        );
                    } catch (\Exception $e) {
                        Log::error('Error in saveAttachment loop', [
                            'error' => $e->getMessage(),
                            'attachment' => $attachmentData['filename'] ?? 'unknown'
                        ]);
                    }
                }
            } else {
                Log::info('No attachments found in parsed data', [
                    'has_attachments_key' => isset($parsedData['attachments']),
                    'email_log_id' => $mailReport->id
                ]);
            }

            // NEW: Auto-assign labels
            $this->autoAssignLabels($mailReport, $mailType);

            // 5. Update client matter timestamp
            $matterId = $document->client_matter_id;
            if (!empty($matterId)) {
                $matter = ClientMatter::find($matterId);
                if ($matter) {
                    $matter->updated_at = now();
                    $matter->save();
                }
            }

            // 6. Create activity log
            if ($request->type == 'client') {
                // Get matter reference
                $matterReference = '';
                if ($matterId) {
                    $matter = ClientMatter::find($matterId);
                    if ($matter && $matter->client_unique_matter_no) {
                        $matterReference = $matter->client_unique_matter_no;
                    }
                }
                
                // Fall back to latest active matter if none found
                if (empty($matterReference)) {
                    $latestMatter = ClientMatter::where('client_id', $clientId)
                        ->where('matter_status', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($latestMatter && $latestMatter->client_unique_matter_no) {
                        $matterReference = $latestMatter->client_unique_matter_no;
                    }
                }
                
                // Format subject with matter reference
                $emailSubject = $parsedData['subject'] ?? 'Email';
                $subject = !empty($matterReference)
                    ? "uploaded Email: {$emailSubject} - {$matterReference}"
                    : "uploaded Email: {$emailSubject}";
                
                // Truncate long subjects
                if (strlen($subject) > 100) {
                    $subject = substr($subject, 0, 97) . '...';
                }
                
                $from = $parsedData['from'] ?? 'Unknown';
                $description = "<p>From: {$from}</p>";
                
                $this->logClientActivity(
                    $clientId,
                    $subject,
                    $description,
                    'email'
                );
            }

            return [
                'success' => true,
                'document_id' => $document->id,
                'email_log_id' => $mailReport->id
            ];

        } catch (\Illuminate\Database\QueryException $e) {
            $errorMessage = $e->getMessage();
            $fileName = $file->getClientOriginalName();
            
            // Extract more specific database error information
            $errorCode = $e->getCode();
            $errorInfo = $e->errorInfo ?? [];
            
            // PostgreSQL specific errors
            if (isset($errorInfo[0]) && $errorInfo[0] === '23502') {
                $errorMessage = "Database constraint error: Required field is missing. Please check the email data.";
            } elseif (isset($errorInfo[0]) && $errorInfo[0] === '23505') {
                $errorMessage = "Duplicate entry: This email may already exist in the database.";
            } elseif (isset($errorInfo[0]) && $errorInfo[0] === '22P02' || strpos($errorMessage, 'invalid input syntax') !== false) {
                $errorMessage = "Data format error: Invalid data format for one or more fields. The email may contain invalid characters or formatting.";
            } elseif (strpos($errorMessage, 'json') !== false || strpos($errorMessage, 'JSON') !== false) {
                $errorMessage = "JSON data error: Unable to save email metadata. Please try again or contact support.";
            } else {
                $errorMessage = "Database error: " . ($errorInfo[2] ?? $errorMessage);
            }
            
            Log::error('Process email file database error', [
                'file' => $fileName,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'error_info' => $errorInfo,
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'technical_error' => $e->getMessage() // Include original for debugging
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $fileName = $file->getClientOriginalName();
            
            // Make error messages more user-friendly
            if (strpos($errorMessage, 'Failed to connect') !== false || strpos($errorMessage, 'Connection refused') !== false) {
                $errorMessage = "Cannot connect to email processing service. Please ensure the Python service is running at {$this->pythonServiceUrl}";
            } elseif (strpos($errorMessage, 'Failed to parse email') !== false || strpos($errorMessage, 'Python service returned') !== false) {
                $errorMessage = "Failed to parse email file. The file may be corrupted or in an unsupported format.";
            } elseif (strpos($errorMessage, 'S3') !== false || strpos($errorMessage, 'AWS') !== false || strpos($errorMessage, 'storage') !== false) {
                $errorMessage = "File storage error. Please check S3 configuration or try again.";
            } elseif (strpos($errorMessage, 'database') !== false || strpos($errorMessage, 'SQL') !== false) {
                $errorMessage = "Database error. Please try again or contact support if the issue persists.";
            }
            
            Log::error('Process email file error', [
                'file' => $fileName,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'technical_error' => $e->getMessage() // Include original for debugging
            ];
        }
    }

    protected function pythonEmailPreviewTimeout(): int
    {
        return max(5, (int) config('services.python.preview_timeout', 30));
    }

    protected function pythonEmailUploadTimeout(): int
    {
        return max(30, (int) config('services.python.timeout', 180));
    }

    /**
     * Parse email metadata only — used for attachment preview before upload.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array|null
     */
    protected function parseEmailMetadataWithPython($file)
    {
        return $this->callPythonEmailParseEndpoint($file, $this->pythonEmailPreviewTimeout());
    }

    /**
     * Parse email file using Python microservice (upload + smart import).
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array|null
     */
    protected function parseEmailWithPython($file)
    {
        return $this->callPythonEmailParseEndpoint($file, $this->pythonEmailUploadTimeout());
    }

    /**
     * @return array<string, mixed>
     */
    protected function callPythonEmailParseEndpoint($file, int $timeout)
    {
        try {
            $originalFileName = $file->getClientOriginalName();
            $sanitizedFileName = $this->sanitizeFilename($originalFileName);

            $response = Http::timeout($timeout)
                ->attach('file', file_get_contents($file->getPathname()), $sanitizedFileName)
                ->post($this->pythonServiceUrl . '/email/parse');

            if ($response->successful()) {
                try {
                    $result = $response->json();
                } catch (\Exception $jsonException) {
                    Log::error('Failed to parse Python service response as JSON', [
                        'status' => $response->status(),
                        'content_type' => $response->header('Content-Type'),
                        'body_preview' => substr($response->body(), 0, 500),
                        'error' => $jsonException->getMessage(),
                        'timeout_seconds' => $timeout,
                    ]);
                    return [
                        'success' => false,
                        'error' => 'Invalid response from email processing service. The service may be experiencing issues.',
                    ];
                }

                if (isset($result['error']) || (isset($result['success']) && ! $result['success'])) {
                    return [
                        'success' => false,
                        'error' => $result['error'] ?? 'Email parsing failed',
                    ];
                }

                return $result;
            }

            Log::error('Python service error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'timeout_seconds' => $timeout,
            ]);

            return [
                'success' => false,
                'error' => 'Python service returned status: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Python service connection error', [
                'error' => $e->getMessage(),
                'url' => $this->pythonServiceUrl,
                'timeout_seconds' => $timeout,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Python service: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if Python service is available
     * 
     * @return array
     */
    public function checkPythonService()
    {
        try {
            $response = Http::timeout(5)->get($this->pythonServiceUrl . '/health');

            return [
                'status' => $response->successful(),
                'url' => $this->pythonServiceUrl,
                'response' => $response->successful() ? $response->json() : null
            ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'url' => $this->pythonServiceUrl,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze email content with Python AI service
     * 
     * @param array $parsedData
     * @return array|null
     */
    protected function analyzeEmailWithPython($parsedData)
    {
        try {
            $response = Http::timeout(30)->post($this->pythonServiceUrl . '/email/analyze', [
                'subject' => $parsedData['subject'] ?? '',
                'text_content' => $parsedData['text_content'] ?? '',
                'html_content' => $parsedData['html_content'] ?? '',
                'sender_email' => $parsedData['sender_email'] ?? '',
                'recipients' => $parsedData['recipients'] ?? [],
            ]);

            if ($response->successful()) {
                return $response->json();
            }
            
            Log::warning('Python analyzer service unavailable', ['status' => $response->status()]);
            return null;
        } catch (\Exception $e) {
            Log::warning('Python analyzer service error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Save attachment to database and S3
     * 
     * @param int $mailReportId
     * @param array $attachmentData
     * @param string $clientUniqueId
     */
    protected function saveAttachment(
        $mailReportId,
        $attachmentData,
        $clientUniqueId,
        $storageConfig = null,
        $request = null,
        $clientId = null,
        $matterId = null
    ) {
        $s3Path = null;
        $s3Key = null;
        $fileSize = $attachmentData['file_size'] ?? $attachmentData['size'] ?? 0;
        $displayName = $attachmentData['display_name'] ?? ($attachmentData['filename'] ?? 'unknown');
        
        try {
            // Check for both 'content' and 'data' keys (Python service uses 'data')
            $attachmentContent = $attachmentData['content'] ?? $attachmentData['data'] ?? null;
            
            Log::info('Processing attachment data', [
                'filename' => $attachmentData['filename'] ?? 'unknown',
                'has_content' => !empty($attachmentContent),
                'content_length' => !empty($attachmentContent) ? strlen($attachmentContent) : 0,
                'expected_size' => $fileSize
            ]);

            $decodedData = null;
            if (!empty($attachmentContent)) {
                // Decode base64-encoded attachment data
                $decodedData = base64_decode($attachmentContent, true);
                
                // Validate base64 decode succeeded
                if ($decodedData === false) {
                    Log::warning('Failed to decode base64 attachment data', [
                        'filename' => $attachmentData['filename'] ?? 'unknown',
                        'content_length' => strlen($attachmentContent)
                    ]);
                } else {
                    // Validate decoded data size matches expected size (with some tolerance for base64 padding)
                    $expectedSize = $fileSize;
                    $actualSize = strlen($decodedData);
                    
                    // Allow up to 3 bytes difference (base64 padding can cause small differences)
                    if ($expectedSize > 0) {
                        $sizeDifference = abs($actualSize - $expectedSize);
                        if ($sizeDifference > 3) {
                            Log::warning('Attachment size mismatch', [
                                'filename' => $attachmentData['filename'] ?? 'unknown',
                                'expected' => $expectedSize,
                                'actual' => $actualSize,
                                'difference' => $sizeDifference
                            ]);
                        }
                    }
                    
                    // Validate minimum size (empty files are suspicious)
                    if ($actualSize === 0) {
                        Log::warning('Decoded attachment data is empty', [
                            'filename' => $attachmentData['filename'] ?? 'unknown'
                        ]);
                        $decodedData = null;
                    }
                }
            } else {
                Log::info('Attachment has no content data, creating record without file', [
                    'filename' => $attachmentData['filename'] ?? 'unknown'
                ]);
            }

            $storageType = is_array($storageConfig) ? ($storageConfig['storage_type'] ?? 'email') : 'email';
            if ($decodedData !== null && $storageType === 'documents' && $request && $clientId) {
                $docResult = $this->saveEmailAttachmentAsDocument(
                    $attachmentData,
                    $storageConfig,
                    $clientUniqueId,
                    (int) $clientId,
                    $request->type ?? 'client',
                    $decodedData,
                    $matterId ? (int) $matterId : null
                );
                if ($docResult) {
                    $s3Path = $docResult['file_path'];
                    $s3Key = $docResult['s3_key'];
                    $fileSize = $docResult['file_size'];
                    $displayName = $docResult['display_name'];
                }
            } elseif ($decodedData !== null) {
                        // Sanitize attachment filename for S3 path to prevent 403 errors
                        $attachmentFileName = $attachmentData['filename'] ?? 'attachment';
                        $sanitizedAttachmentFileName = $this->sanitizeFilename($attachmentFileName);
                        // Generate unique S3 key with sanitized filename
                        $s3Key = $clientUniqueId . '/attachments/' . time() . '_' . $sanitizedAttachmentFileName;
                        
                        try {
                            // Upload to S3
                            $uploadSuccess = Storage::disk('s3')->put($s3Key, $decodedData);
                            
                            if (!$uploadSuccess) {
                                throw new \Exception('S3 upload returned false');
                            }
                            
                            // Verify file exists in S3
                            if (!Storage::disk('s3')->exists($s3Key)) {
                                throw new \Exception('File not found in S3 after upload');
                            }
                            
                            $s3Path = Storage::disk('s3')->url($s3Key);
                            
                            // Update file size to actual decoded size
                            $fileSize = strlen($decodedData);
                            
                            Log::info('Attachment saved successfully to S3', [
                                'filename' => $attachmentData['filename'] ?? 'unknown',
                                'size' => $fileSize,
                                's3_key' => $s3Key,
                                's3_path' => $s3Path
                            ]);
                        } catch (\Exception $s3Exception) {
                            Log::error('S3 upload failed for attachment', [
                                'filename' => $attachmentData['filename'] ?? 'unknown',
                                's3_key' => $s3Key,
                                'error' => $s3Exception->getMessage(),
                                'trace' => $s3Exception->getTraceAsString()
                            ]);
                            // Reset s3_key and s3Path so we don't save invalid references
                            $s3Key = null;
                            $s3Path = null;
                        }
            }

            // Always create attachment record (even if file upload failed)
            \App\Models\EmailLogAttachment::create([
                'email_log_id' => $mailReportId,
                'filename' => $attachmentData['filename'] ?? 'unknown',
                'display_name' => $displayName,
                'content_type' => $attachmentData['content_type'] ?? 'application/octet-stream',
                'file_path' => $s3Path,
                's3_key' => $s3Key,
                'file_size' => $fileSize,
                'content_id' => $attachmentData['content_id'] ?? null,
                'is_inline' => $attachmentData['is_inline'] ?? false,
                'extension' => pathinfo($attachmentData['filename'] ?? 'unknown', PATHINFO_EXTENSION),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save attachment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attachment' => $attachmentData['filename'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Auto-assign labels based on sender domain
     * 
     * @param \App\Models\EmailLog $mailReport
     * @param string $mailType
     */
    protected function autoAssignLabels($mailReport, $mailType)
    {
        try {
            // Company domains that indicate emails WE sent
            $companyDomains = [
                '@bansalimmigration.com.au',
                '@bansaleducation.com.au',
                '@bansallawyers.com.au'
            ];
            
            // Check if email is from our company domains
            $isFromCompany = false;
            $senderEmail = strtolower($mailReport->from_mail);
            
            foreach ($companyDomains as $domain) {
                if (str_contains($senderEmail, $domain)) {
                    $isFromCompany = true;
                    break;
                }
            }
            
            // Assign "Sent" label if from company domain, otherwise "Inbox" label
            $labelName = $isFromCompany ? 'Sent' : 'Inbox';
            
            $label = \App\Models\EmailLabel::where('name', $labelName)
                ->where('type', 'system')
                ->first();
            
            if ($label) {
                $mailReport->labels()->attach($label->id);
                
                Log::info('Auto-assigned label', [
                    'email_id' => $mailReport->id,
                    'sender' => $mailReport->from_mail,
                    'label' => $labelName,
                    'is_from_company' => $isFromCompany
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to auto-assign label', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Import a single staged email into the normal upload pipeline.
     *
     * Called by SmartEmailImportController after the staff review stage.
     * Builds a synthetic Request so processEmailFile() receives all the fields it
     * expects, then delegates to the existing save logic (S3, documents, email_logs,
     * attachments, labels, activity log).
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  int     $clientId       admins.id
     * @param  string  $mailType       'inbox' | 'sent'
     * @param  int     $clientMatterId client_matters.id
     * @param  string  $recordType     'client' | 'lead'
     * @return array   Same shape as processEmailFile(): ['success' => bool, ...]
     */
    public function importEmailFromContext(
        \Illuminate\Http\UploadedFile $file,
        int    $clientId,
        string $mailType,
        int    $clientMatterId,
        string $recordType
    ): array {
        $clientUniqueId = Admin::select('client_id')
            ->where('id', $clientId)
            ->value('client_id') ?? '';

        $syntheticRequest = new Request();
        $syntheticRequest->merge([
            'client_id'                           => $clientId,
            'type'                                => $recordType,
            'upload_inbox_mail_client_matter_id'  => $mailType === 'inbox' ? $clientMatterId : null,
            'upload_sent_mail_client_matter_id'   => $mailType === 'sent'  ? $clientMatterId : null,
        ]);

        return $this->processEmailFile($file, $clientId, $clientUniqueId, $mailType, $syntheticRequest);
    }

    /**
     * Sanitize filename for use in S3 file paths
     * Prevents 403 errors caused by special characters in filenames
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename safe for S3 paths
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Get file extension first (before sanitization)
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        // Replace special characters with underscores, but keep alphanumeric, hyphens, underscores, and dots
        $sanitizedName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $nameWithoutExt);
        
        // Remove multiple consecutive underscores
        $sanitizedName = preg_replace('/_+/', '_', $sanitizedName);
        
        // Trim underscores from start and end
        $sanitizedName = trim($sanitizedName, '_');
        
        // Ensure filename is not empty
        if (empty($sanitizedName)) {
            $sanitizedName = 'email_' . time();
        }
        
        // Reconstruct filename with extension
        $sanitizedFilename = !empty($extension) ? $sanitizedName . '.' . $extension : $sanitizedName;
        
        // Limit total filename length (including extension) to 255 characters
        if (strlen($sanitizedFilename) > 255) {
            $maxNameLength = 255 - strlen($extension) - 1; // -1 for the dot
            if ($maxNameLength > 0) {
                $sanitizedName = substr($sanitizedName, 0, $maxNameLength);
                $sanitizedFilename = !empty($extension) ? $sanitizedName . '.' . $extension : $sanitizedName;
            } else {
                // If extension itself is too long, just use timestamp
                $sanitizedFilename = 'email_' . time() . (!empty($extension) ? '.' . $extension : '');
            }
        }
        
        return $sanitizedFilename;
    }
}

