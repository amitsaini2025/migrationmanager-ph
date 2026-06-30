<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailLogAttachment extends Model
{
    use HasFactory;

    protected $table = 'email_log_attachments';

    protected $fillable = [
        'email_log_id',
        'filename',
        'display_name',
        'content_type',
        'file_path',
        's3_key',
        'file_size',
        'content_id',
        'is_inline',
        'description',
        'headers',
        'extension',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'headers' => 'array',
    ];

    /**
     * Get the email log that owns the attachment.
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the display name or filename.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->filename;
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        $imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        return in_array($this->content_type, $imageTypes);
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->content_type === 'application/pdf';
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
            'text/html',
            'text/css',
            'application/json',
            'application/xml',
            'text/csv',
        ];
        return in_array($this->content_type, $documentTypes);
    }

    /**
     * Check if the attachment can be previewed.
     */
    public function canPreview(): bool
    {
        if ($this->isImage() || $this->isPdf()) {
            return true;
        }

        // Fallback for imported inline images stored with a generic content_type
        $ext = strtolower((string) ($this->extension ?: pathinfo($this->filename ?? '', PATHINFO_EXTENSION)));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf'], true);
    }

    /**
     * Legacy Font Awesome class string (kept for backward compatibility).
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isImage()) {
            return 'fas fa-image text-blue-500';
        }

        if ($this->isPdf()) {
            return 'fas fa-file-pdf text-red-500';
        }

        if ($this->isDocument()) {
            return 'fas fa-file-alt text-gray-500';
        }

        return 'fas fa-paperclip text-gray-400';
    }

    /**
     * Render attachment type icon as Lucide HTML.
     */
    public function iconHtml(): string
    {
        $legacy = $this->icon_class;
        $colorClass = '';

        if (preg_match('/\b(text-\S+)\b/', $legacy, $matches)) {
            $colorClass = $matches[1];
            $legacy = trim(preg_replace('/\b(text-\S+)\b/', '', $legacy) ?? $legacy);
        }

        return \App\Helpers\IconHelper::fromLegacy($legacy, ['class' => trim($colorClass)]);
    }

    /**
     * Scope to filter by content type.
     */
    public function scopeOfType($query, $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to filter inline attachments.
     */
    public function scopeInline($query)
    {
        return $query->where('is_inline', true);
    }

    /**
     * Scope to filter regular attachments.
     */
    public function scopeRegular($query)
    {
        return $query->where('is_inline', false);
    }
}
