<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use App\Support\Utf8Text;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EmailLogListService
{
    public const DEFAULT_PER_PAGE = 25;

    /** JSON flags for email list/detail API responses (invalid bytes become U+FFFD). */
    public const API_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    public function applySort(Builder $query, string $sort): Builder
    {
        switch ($sort) {
            case 'subject':
                return $query->orderBy('subject')->orderBy('created_at', 'DESC');
            case 'sender':
                return $query->orderBy('from_mail')->orderBy('created_at', 'DESC');
            case 'date':
            default:
                return $query->orderBy('created_at', 'DESC');
        }
    }

    public function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', self::DEFAULT_PER_PAGE)));

        return $query->paginate($perPage, ['*'], 'page', max(1, (int) $request->input('page', 1)));
    }

    /**
     * @param  Collection<int, EmailLog>|LengthAwarePaginator  $emails
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function mapForList(Collection $emails, array $options = []): array
    {
        if ($emails->isEmpty()) {
            return [];
        }

        $url = $this->s3BaseUrl();
        $prefetch = $this->prefetchRelatedData($emails, $options);

        return $emails->map(function (EmailLog $email) use ($url, $options, $prefetch) {
            return $this->mapSingleForList($email, $url, $options, $prefetch);
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function mapForDetail(EmailLog $email, array $options = []): array
    {
        $url = $this->s3BaseUrl();
        $prefetch = $this->prefetchRelatedData(collect([$email]), $options);

        return $this->mapSingleForDetail($email, $url, $options, $prefetch);
    }

    /**
     * @param  LengthAwarePaginator  $paginator
     * @param  array<string, mixed>  $options
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function buildPaginatedResponse(LengthAwarePaginator $paginator, array $options = []): array
    {
        return [
            'data' => $this->mapForList($paginator->getCollection(), $options),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  Collection<int, EmailLog>  $emails
     * @param  array<string, mixed>  $options
     * @return array{
     *     documents: Collection<int|string, Document>,
     *     admins: Collection<int|string, Admin>,
     *     fallback_attachments: Collection<int|string, Collection<int, EmailLogAttachment>>
     * }
     */
    private function prefetchRelatedData(Collection $emails, array $options): array
    {
        $docIds = $emails->pluck('uploaded_doc_id')->filter()->unique()->values();
        $clientIds = $emails->pluck('client_id')->filter()->unique()->values();

        $documents = $docIds->isNotEmpty()
            ? Document::select('id', 'doc_type', 'myfile', 'myfile_key', 'mail_type')->whereIn('id', $docIds)->get()->keyBy('id')
            : collect();

        $adminQuery = Admin::select('id', 'client_id');
        if (!empty($options['admin_without_global_scopes'])) {
            $adminQuery = Admin::withoutGlobalScopes()->select('id', 'client_id');
        }
        $admins = $clientIds->isNotEmpty()
            ? $adminQuery->whereIn('id', $clientIds)->get()->keyBy('id')
            : collect();

        $emailIdsNeedingFallback = $emails->filter(function (EmailLog $email) {
            return $email->getFileAttachmentCollection()->isEmpty();
        })->pluck('id')->values();

        $fallbackAttachments = $emailIdsNeedingFallback->isNotEmpty()
            ? EmailLogAttachment::whereIn('email_log_id', $emailIdsNeedingFallback)->get()->groupBy('email_log_id')
            : collect();

        return [
            'documents' => $documents,
            'admins' => $admins,
            'fallback_attachments' => $fallbackAttachments,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{
     *     documents: Collection<int|string, Document>,
     *     admins: Collection<int|string, Admin>,
     *     fallback_attachments: Collection<int|string, Collection<int, EmailLogAttachment>>
     * }  $prefetch
     * @return array<string, mixed>
     */
    private function mapSingleForList(EmailLog $email, string $url, array $options, array $prefetch): array
    {
        $emailArray = $this->buildBaseArray($email, $url, $options, $prefetch);
        $emailArray['text_preview'] = $this->resolveTextPreview($email);
        unset($emailArray['message'], $emailArray['body_s3_key'], $emailArray['enhanced_html'], $emailArray['rendered_html']);

        $emailArray = app(MatterEmailBodyCleanupService::class)->appendListArchivedBodyMeta($emailArray, $email);

        return Utf8Text::cleanDeep($emailArray);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{
     *     documents: Collection<int|string, Document>,
     *     admins: Collection<int|string, Admin>,
     *     fallback_attachments: Collection<int|string, Collection<int, EmailLogAttachment>>
     * }  $prefetch
     * @return array<string, mixed>
     */
    private function mapSingleForDetail(EmailLog $email, string $url, array $options, array $prefetch): array
    {
        $emailArray = $this->buildBaseArray($email, $url, $options, $prefetch);
        $emailArray['message'] = $emailArray['message'] ?? '';
        $emailArray['text_preview'] = $this->resolveTextPreview($email);

        $emailArray = app(MatterEmailBodyCleanupService::class)->appendArchivedBodyMeta($emailArray, $email);

        return Utf8Text::cleanDeep($emailArray);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{
     *     documents: Collection<int|string, Document>,
     *     admins: Collection<int|string, Admin>,
     *     fallback_attachments: Collection<int|string, Collection<int, EmailLogAttachment>>
     * }  $prefetch
     * @return array<string, mixed>
     */
    private function buildBaseArray(EmailLog $email, string $url, array $options, array $prefetch): array
    {
        $previewUrl = $this->resolvePreviewUrl($email, $url, $options, $prefetch);
        $attachments = $this->resolveAttachments($email, $prefetch['fallback_attachments']);

        $emailArray = $email->toArray();
        $emailArray['attachments'] = $this->formatAttachments($attachments);
        $emailArray['preview_url'] = $previewUrl;

        $recipientType = $options['recipient_type'] ?? ($email->type ?? 'client');
        $emailArray['cc'] = EmailLog::resolveRecipientDisplay($emailArray['cc'] ?? '', $recipientType);
        $emailArray['to_mail'] = EmailLog::resolveRecipientDisplay($emailArray['to_mail'] ?? '', $recipientType);
        $emailArray['from_mail'] = $emailArray['from_mail'] ?? '';
        $emailArray['subject'] = $emailArray['subject'] ?? '';

        return $emailArray;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{
     *     documents: Collection<int|string, Document>,
     *     admins: Collection<int|string, Admin>,
     *     fallback_attachments: Collection<int|string, Collection<int, EmailLogAttachment>>
     * }  $prefetch
     */
    private function resolvePreviewUrl(EmailLog $email, string $url, array $options, array $prefetch): string
    {
        if (empty($email->uploaded_doc_id)) {
            return '';
        }

        $docInfo = $prefetch['documents']->get($email->uploaded_doc_id);
        if (!$docInfo) {
            return '';
        }

        if (!empty($docInfo->myfile_key)) {
            return (string) $docInfo->myfile;
        }

        $adminInfo = $prefetch['admins']->get($email->client_id);
        $fallbackClientId = $options['client_id'] ?? $email->client_id ?? 0;
        $clientRef = ($adminInfo && $adminInfo->client_id)
            ? $adminInfo->client_id
            : ('client_' . $fallbackClientId);

        $defaultMailType = $options['default_mail_type'] ?? 'inbox';

        return $url . $clientRef . '/'
            . ($docInfo->doc_type ?? 'mail') . '/'
            . ($docInfo->mail_type ?? $defaultMailType) . '/'
            . ($docInfo->myfile ?? '');
    }

    /**
     * @param  Collection<int|string, Collection<int, EmailLogAttachment>>  $fallbackAttachments
     */
    private function resolveAttachments(EmailLog $email, Collection $fallbackAttachments): Collection
    {
        $attachments = $email->getFileAttachmentCollection();
        if ($attachments->isEmpty()) {
            $attachments = $fallbackAttachments->get($email->id, collect());
        }

        return $attachments;
    }

    /**
     * @param  Collection<int, EmailLogAttachment>  $attachments
     * @return array<int, array<string, mixed>>
     */
    private function formatAttachments(Collection $attachments): array
    {
        if ($attachments->isEmpty()) {
            return [];
        }

        return $attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'mail_report_id' => $attachment->email_log_id,
                'filename' => $attachment->filename,
                'display_name' => $attachment->display_name ?? $attachment->filename,
                'content_type' => $attachment->content_type,
                'file_path' => $attachment->file_path,
                's3_key' => $attachment->s3_key,
                'file_size' => (int) $attachment->file_size,
                'content_id' => $attachment->content_id,
                'is_inline' => (bool) $attachment->is_inline,
                'description' => $attachment->description,
                'extension' => $attachment->extension,
            ];
        })->values()->all();
    }

    private function resolveTextPreview(EmailLog $email): string
    {
        $storedPreview = trim((string) Utf8Text::clean($email->text_preview ?? ''));
        if ($storedPreview !== '') {
            return $storedPreview;
        }

        $message = (string) Utf8Text::clean($email->message ?? '');
        if ($message === '') {
            return '';
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($message)) ?? '');

        if (strlen($plain) > 200) {
            return substr($plain, 0, 200) . '…';
        }

        return $plain;
    }

    private function s3BaseUrl(): string
    {
        return 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
    }
}
