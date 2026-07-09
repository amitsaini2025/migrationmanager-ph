<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\EmailLogAttachment;
use App\Models\EmailLog;
use App\Services\CrmSentEmailS3Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use ZipArchive;

class EmailLogAttachmentController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Download individual attachment
     */
    public function download($id)
    {
        try {
            $attachment = EmailLogAttachment::findOrFail($id);

            $emailLog = $attachment->emailLog;
            if ($emailLog) {
                $this->ensureCrmRecordAccessForOptionalClientId(
                    $emailLog->client_id ? (int) $emailLog->client_id : null
                );
            }

            $localPath = CrmSentEmailS3Service::resolveLocalDiskAbsolutePath($attachment->file_path);
            if ($localPath) {
                $content = @file_get_contents($localPath);
                if ($content === false) {
                    Log::error('Attachment download failed: empty or unreadable local file', [
                        'id' => $id,
                        'path' => $localPath,
                    ]);
                    abort(404, 'Attachment file not found in storage');
                }

                return Response::make($content, 200, [
                    'Content-Type' => $attachment->content_type ?: 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . $attachment->filename . '"',
                    'Content-Length' => strlen($content),
                ]);
            }

            if (!$attachment->s3_key) {
                Log::error('Attachment download failed: No S3 key or local file', [
                    'id' => $id,
                    'filename' => $attachment->filename,
                    'file_path' => $attachment->file_path,
                    'email_log_id' => $attachment->email_log_id,
                ]);
                abort(404, 'Attachment file not found (no S3 key)');
            }

            if (!Storage::disk('s3')->exists($attachment->s3_key)) {
                Log::error('Attachment download failed: File not found in S3', [
                    'id' => $id,
                    's3_key' => $attachment->s3_key,
                    'filename' => $attachment->filename,
                ]);
                abort(404, 'Attachment file not found in storage');
            }

            $content = Storage::disk('s3')->get($attachment->s3_key);

            if (empty($content)) {
                Log::error('Attachment download failed: Empty content', [
                    'id' => $id,
                    's3_key' => $attachment->s3_key,
                    'filename' => $attachment->filename
                ]);
                abort(404, 'Attachment file is empty');
            }

            return Response::make($content, 200, [
                'Content-Type' => $attachment->content_type ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $attachment->filename . '"',
                'Content-Length' => strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error('Attachment download failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(404, 'Attachment file not found: ' . $e->getMessage());
        }
    }

    /**
     * Download all attachments for an email as ZIP
     */
    public function downloadAll($emailLogId)
    {
        try {
            $emailLog = EmailLog::findOrFail($emailLogId);
            $this->ensureCrmRecordAccessForOptionalClientId(
                $emailLog->client_id ? (int) $emailLog->client_id : null
            );
            $attachments = $emailLog->attachments()->regular()->get();

            if ($attachments->isEmpty()) {
                abort(404, 'No attachments found');
            }

            // Create temporary ZIP file
            $zipFileName = 'attachments_' . $emailLogId . '_' . time() . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                abort(500, 'Could not create ZIP file');
            }

            foreach ($attachments as $attachment) {
                try {
                    $local = CrmSentEmailS3Service::resolveLocalDiskAbsolutePath($attachment->file_path);
                    if ($local) {
                        $content = @file_get_contents($local);
                        if ($content !== false) {
                            $zip->addFromString($attachment->filename, $content);
                        }
                    } elseif ($attachment->s3_key) {
                        $content = Storage::disk('s3')->get($attachment->s3_key);
                        $zip->addFromString($attachment->filename, $content);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to add attachment to ZIP', [
                        'attachment_id' => $attachment->id,
                        'error' => $e->getMessage()
                    ]);
                    // Skip failed attachments
                    continue;
                }
            }

            $zip->close();

            // Download and delete temp file
            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Download all attachments failed', [
                'email_log_id' => $emailLogId,
                'error' => $e->getMessage()
            ]);
            abort(500, 'Failed to create ZIP file');
        }
    }

    /**
     * Preview attachment (for images/PDFs)
     */
    public function preview($id)
    {
        try {
            $attachment = EmailLogAttachment::findOrFail($id);

            $emailLog = $attachment->emailLog;
            if ($emailLog) {
                $this->ensureCrmRecordAccessForOptionalClientId(
                    $emailLog->client_id ? (int) $emailLog->client_id : null
                );
            }

            if (!$attachment->canPreview()) {
                abort(400, 'This file type cannot be previewed');
            }

            $mimeType = $attachment->getEffectiveMimeType();
            $displayName = $attachment->display_name ?? $attachment->filename;

            $localPath = CrmSentEmailS3Service::resolveLocalDiskAbsolutePath($attachment->file_path);
            if ($localPath) {
                $content = @file_get_contents($localPath);
                if ($content === false) {
                    abort(404, 'Attachment file not found');
                }
                return Response::make($content, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . $displayName . '"',
                ]);
            }

            if (!$attachment->s3_key) {
                abort(404, 'Attachment file not found');
            }

            $content = Storage::disk('s3')->get($attachment->s3_key);

            return Response::make($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $displayName . '"',
            ]);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Attachment preview failed', ['id' => $id, 'error' => $e->getMessage()]);
            abort(404, 'Attachment file not found');
        }
    }
}
