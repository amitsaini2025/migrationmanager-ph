<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MatterEmailBodyCleanupService
{
    /**
     * Clear stored email body content for all emails linked to a matter.
     * S3 files, attachment rows, and email metadata (subject, from, etc.) are left intact.
     */
    public function clearBodiesForMatter(int $matterId): int
    {
        if ($matterId <= 0) {
            return 0;
        }

        $updates = ['message' => null];

        foreach ($this->bodyContentColumns() as $column) {
            if (Schema::hasColumn('email_logs', $column)) {
                $updates[$column] = null;
            }
        }

        $affected = EmailLog::where('client_matter_id', $matterId)->update($updates);

        if ($affected > 0) {
            Log::info('Cleared email body content for matter', [
                'client_matter_id' => $matterId,
                'email_logs_updated' => $affected,
            ]);
        }

        return $affected;
    }

    /**
     * Archive email bodies from the database to S3, then clear body fields for the matter.
     * Existing S3 objects are never deleted. body_s3_key is preserved for archived-body viewing.
     *
     * @return array{archived: int, cleared: int, skipped: int}
     */
    public function sendAllBodiesToS3AndClearForMatter(int $matterId): array
    {
        if ($matterId <= 0) {
            throw new \InvalidArgumentException('Invalid matter ID.');
        }

        if (!$this->isS3Configured()) {
            throw new \RuntimeException('S3 storage is not configured.');
        }

        $matter = ClientMatter::find($matterId);
        if (!$matter) {
            throw new \RuntimeException('Client matter not found.');
        }

        $sanitizedClientId = $this->sanitizeClientIdForPath((int) $matter->client_id);
        $emails = EmailLog::where('client_matter_id', $matterId)->get();

        $archived = 0;
        $skipped = 0;
        $cleared = 0;

        DB::transaction(function () use ($emails, $sanitizedClientId, $matterId, &$archived, &$skipped, &$cleared) {
            foreach ($emails as $email) {
                $s3Path = $this->buildArchiveS3Path($sanitizedClientId, $matterId, (int) $email->id);
                $body = $this->extractBodyContent($email);

                if ($body !== '' && !Storage::disk('s3')->exists($s3Path)) {
                    $html = $this->wrapBodyHtml($email, $body);
                    if (!Storage::disk('s3')->put($s3Path, $html)) {
                        throw new \RuntimeException('Failed to upload email body to S3 for email #' . $email->id);
                    }
                    $archived++;
                }

                if (Storage::disk('s3')->exists($s3Path)) {
                    if (Schema::hasColumn('email_logs', 'body_s3_key')) {
                        $email->body_s3_key = $s3Path;
                        $email->save();
                    }
                } else {
                    $skipped++;
                }
            }

            $cleared = $this->clearBodiesForMatter($matterId);
        });

        Log::info('Archived matter email bodies to S3 and cleared database content', [
            'client_matter_id' => $matterId,
            'archived' => $archived,
            'skipped' => $skipped,
            'cleared' => $cleared,
        ]);

        return [
            'archived' => $archived,
            'skipped' => $skipped,
            'cleared' => $cleared,
        ];
    }

    /**
     * Whether any email for the matter still has body content stored in the database.
     */
    public function matterHasBodyContentInDatabase(int $matterId): bool
    {
        if ($matterId <= 0) {
            return false;
        }

        return EmailLog::where('client_matter_id', $matterId)
            ->get()
            ->contains(fn (EmailLog $email) => $this->extractBodyContent($email) !== '');
    }

    /**
     * Resolve the S3 key for an archived email body, if one exists.
     */
    public function resolveArchivedBodyS3Key(EmailLog $email): ?string
    {
        if (!$this->isS3Configured()) {
            return null;
        }

        if (Schema::hasColumn('email_logs', 'body_s3_key')) {
            $storedKey = trim((string) ($email->body_s3_key ?? ''));
            if ($storedKey !== '' && Storage::disk('s3')->exists($storedKey)) {
                return $storedKey;
            }
        }

        $conventionKey = $this->buildArchiveS3PathForEmail($email);
        if ($conventionKey !== null && Storage::disk('s3')->exists($conventionKey)) {
            return $conventionKey;
        }

        return null;
    }

    public function hasArchivedBodyOnS3(EmailLog $email): bool
    {
        return $this->resolveArchivedBodyS3Key($email) !== null;
    }

    /**
     * @param  array<string, mixed>  $emailArray
     * @return array<string, mixed>
     */
    public function appendArchivedBodyMeta(array $emailArray, EmailLog $email): array
    {
        $s3Key = $this->resolveArchivedBodyS3Key($email);

        $emailArray['has_archived_body'] = $s3Key !== null;
        $emailArray['archived_body_view_url'] = $s3Key !== null
            ? route('clients.email.view-archived-body', ['id' => $email->id])
            : null;

        return $emailArray;
    }

    /**
     * List-view metadata only — avoids per-row S3 HEAD requests.
     *
     * @param  array<string, mixed>  $emailArray
     * @return array<string, mixed>
     */
    public function appendListArchivedBodyMeta(array $emailArray, EmailLog $email): array
    {
        $hasArchivedBody = Schema::hasColumn('email_logs', 'body_s3_key')
            && trim((string) ($email->body_s3_key ?? '')) !== '';

        $emailArray['has_archived_body'] = $hasArchivedBody;
        $emailArray['archived_body_view_url'] = $hasArchivedBody
            ? route('clients.email.view-archived-body', ['id' => $email->id])
            : null;

        return $emailArray;
    }

    public function buildArchiveS3PathForEmail(EmailLog $email): ?string
    {
        if (!$email->client_matter_id || !$email->id) {
            return null;
        }

        $sanitizedClientId = $this->sanitizeClientIdForPath((int) $email->client_id);

        return $this->buildArchiveS3Path(
            $sanitizedClientId,
            (int) $email->client_matter_id,
            (int) $email->id
        );
    }

    public function buildArchiveS3Path(string $sanitizedClientId, int $matterId, int $emailLogId): string
    {
        return $sanitizedClientId
            . '/email_body_archive/matter_'
            . $matterId
            . '/email_'
            . $emailLogId
            . '.html';
    }

    protected function isS3Configured(): bool
    {
        return (bool) (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.bucket'));
    }

    protected function sanitizeClientIdForPath(int $clientId): string
    {
        $admin = Admin::find($clientId);
        $clientUniqueId = ($admin && !empty($admin->client_id)) ? $admin->client_id : 'client_' . $clientId;

        return preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);
    }

    protected function extractBodyContent(EmailLog $email): string
    {
        foreach (['message', 'enhanced_html', 'rendered_html', 'text_preview'] as $column) {
            if (!Schema::hasColumn('email_logs', $column)) {
                continue;
            }

            $value = trim((string) ($email->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function wrapBodyHtml(EmailLog $email, string $body): string
    {
        $subject = htmlspecialchars((string) ($email->subject ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $from = htmlspecialchars((string) ($email->from_mail ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $to = htmlspecialchars(EmailLog::resolveRecipientDisplay($email->to_mail, $email->type), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ccResolved = EmailLog::resolveRecipientDisplay($email->cc, $email->type);
        $ccLine = $ccResolved !== ''
            ? '<p><strong>Cc:</strong> ' . htmlspecialchars($ccResolved, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            . $subject
            . '</title></head><body>'
            . '<p><strong>Subject:</strong> ' . $subject . '</p>'
            . '<p><strong>From:</strong> ' . $from . '</p>'
            . '<p><strong>To:</strong> ' . $to . '</p>'
            . $ccLine
            . '<hr>'
            . $body
            . '</body></html>';
    }

    /**
     * @return list<string>
     */
    private function bodyContentColumns(): array
    {
        return [
            'enhanced_html',
            'rendered_html',
            'text_preview',
            'python_analysis',
            'python_rendering',
        ];
    }
}
