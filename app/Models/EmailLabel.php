<?php

namespace App\Models;

use App\Helpers\IconHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailLabel extends Model
{
    use HasFactory;

    /** Lucide icon names for system labels (stored in DB and used as fallbacks). */
    public const SYSTEM_DEFAULT_ICONS = [
        'inbox' => 'inbox',
        'sent' => 'send',
        'draft' => 'pencil',
        'trash' => 'trash-2',
        'spam' => 'ban',
        'archive' => 'archive',
        'work' => 'briefcase',
        'personal' => 'user',
        'important' => 'star',
        'urgent' => 'triangle-alert',
        'follow up' => 'flag',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'type',
        'icon',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the label.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Get the email logs that have this label.
     */
    public function emailLogs(): BelongsToMany
    {
        return $this->belongsToMany(EmailLog::class, 'email_label_email_log', 'email_label_id', 'email_log_id')
                    ->withTimestamps();
    }

    /**
     * Check if this is a system label.
     */
    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    /**
     * Check if this is a custom label.
     */
    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    /**
     * Stored or default icon identifier (Lucide name or legacy FA class string).
     */
    public function getDisplayIconAttribute(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        $labelName = strtolower(trim($this->name));

        return self::SYSTEM_DEFAULT_ICONS[$labelName] ?? 'tag';
    }

    /**
     * Render the label icon as a Lucide placeholder (hydrated client-side).
     */
    public function iconHtml(array $attributes = []): string
    {
        return IconHelper::renderStored($this->display_icon, $attributes);
    }

    /**
     * Get the formatted color with fallback.
     */
    public function getFormattedColorAttribute(): string
    {
        return $this->color ?: '#3B82F6';
    }

    /**
     * Scope to filter active labels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter system labels.
     */
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    /**
     * Scope to filter custom labels.
     */
    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id'); // Include system labels
        });
    }
}
