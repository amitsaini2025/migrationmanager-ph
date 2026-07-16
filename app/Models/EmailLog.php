<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ClientMatter;

class EmailLog extends Authenticatable
{
    use Notifiable;
	use Sortable;

	protected $table = 'email_logs';

	protected $fillable = [
        'id',
        'user_id',
        'from_mail',
        'to_mail',
        'cc',
        'template_id',
        'subject',
        'message',
        'body_s3_key',
        'type',
        'reciept_id',
        'attachments',
        'mail_type',
        'client_id',
        'client_matter_id',
        'conversion_type',
        'system_email_category',
        'mail_body_type',
        'fetch_mail_sent_time',
        'uploaded_doc_id',
        'mail_is_read',
        // Python analysis fields
        'python_analysis',
        'python_rendering',
        'sentiment',
        'language',
        'enhanced_html',
        'rendered_html',
        'text_preview',
        'security_issues',
        'thread_info',
        'processed_at',
        // Additional metadata
        'message_id',
        'sendgrid_message_id',
        'delivery_status',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'spam_reported_at',
        'status_reason',
        'thread_id',
        'received_date',
        'last_accessed_at',
        'file_hash',
        'created_at',
        'updated_at'
    ];

	public $sortable = ['id', 'created_at', 'updated_at', 'subject', 'from_mail'];

    protected $casts = [
        'python_analysis' => 'array',
        'python_rendering' => 'array',
        'security_issues' => 'array',
        'thread_info' => 'array',
        'processed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'spam_reported_at' => 'datetime',
        'received_date' => 'datetime',
        'last_accessed_at' => 'datetime',
        'mail_is_read' => 'boolean',
    ];

    /**
     * Get the attachments for the email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailLogAttachment::class, 'email_log_id');
    }

    /**
     * Rows in email_log_attachments. Use this instead of $this->attachments (property):
     * the `attachments` column on email_logs (legacy JSON) has the same name and can
     * be returned as a string, shadowing the relationship.
     */
    public function getFileAttachmentCollection(): Collection
    {
        if ($this->relationLoaded('attachments')) {
            $rel = $this->getRelation('attachments');
            if ($rel instanceof Collection) {
                return $rel;
            }
        }

        return $this->attachments()->get();
    }

    /**
     * Get the labels for the email.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(EmailLabel::class, 'email_label_email_log', 'email_log_id', 'email_label_id')
                    ->withTimestamps();
    }

    /**
     * Get the client that owns the email.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the client matter this email belongs to.
     */
    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    /**
     * Emails that should appear in CRM Emails → Sent (compose, fetched-sent, and Account receipt/invoice sends).
     * Keeps other system_generated categories (portal, appointments, etc.) out of the Sent tab.
     */
    public function scopeForCrmSentMailbox($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('conversion_type')
                ->orWhere(function ($subQuery) {
                    $subQuery->where('conversion_type', 'conversion_email_fetch')
                        ->where('mail_body_type', 'sent');
                })
                ->orWhere(function ($subQuery) {
                    $subQuery->where('conversion_type', 'system_generated')
                        ->whereIn('system_email_category', ['receipt', 'invoice']);
                });
        });
    }

    /**
     * Get the user who uploaded the email.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * SendGrid webhook events (delivery + engagement timeline).
     */
    public function sendgridEvents(): HasMany
    {
        return $this->hasMany(EmailLogEvent::class, 'email_log_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * Check if the email has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->count() > 0;
    }

    /**
     * Get the number of attachments.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments()->count();
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!isset($this->attributes['file_size'])) {
            return '0 B';
        }
        
        $bytes = $this->attributes['file_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if email has Python analysis.
     */
    public function hasPythonAnalysis(): bool
    {
        return !empty($this->python_analysis);
    }

    /**
     * Check if email has security issues.
     */
    public function hasSecurityIssues(): bool
    {
        return !empty($this->security_issues);
    }

    /**
     * Check if email is a reply.
     */
    public function isReply(): bool
    {
        return isset($this->thread_info['is_reply']) && $this->thread_info['is_reply'];
    }

    /**
     * Scope to filter by sentiment.
     */
    public function scopeBySentiment($query, $sentiment)
    {
        return $query->where('sentiment', $sentiment);
    }

    /**
     * Scope to search emails.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
              ->orWhere('from_mail', 'like', "%{$search}%")
              ->orWhere('to_mail', 'like', "%{$search}%")
              ->orWhere('message', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to filter by label.
     */
    public function scopeWithLabel($query, $labelId)
    {
        return $query->whereHas('labels', function ($q) use ($labelId) {
            $q->where('email_labels.id', $labelId);
        });
    }

    /**
     * Scope to filter emails with attachments.
     */
    public function scopeWithAttachments($query)
    {
        return $query->has('attachments');
    }

    /**
     * Scope to filter emails without attachments.
     */
    public function scopeWithoutAttachments($query)
    {
        return $query->doesntHave('attachments');
    }

    /**
     * CRM compose stores recipient Admin/Agent row IDs in to_mail; Sent tab should show addresses.
     * Resolves comma-separated numeric IDs to emails. Leaves real addresses unchanged.
     */
    public static function resolveRecipientDisplay(?string $toMail, ?string $logType = null): string
    {
        if ($toMail === null || ($toMail = trim($toMail)) === '') {
            return '';
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $toMail)), static fn ($p) => $p !== ''));
        if ($parts === []) {
            return $toMail;
        }
        $allEmails = true;
        foreach ($parts as $p) {
            if (! filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $allEmails = false;
                break;
            }
        }
        if ($allEmails) {
            return $toMail;
        }
        foreach ($parts as $p) {
            if (! ctype_digit((string) $p)) {
                return $toMail;
            }
        }
        $emails = [];
        $isAgent = ($logType === 'agent');
        foreach ($parts as $idStr) {
            $idInt = (int) $idStr;
            if ($isAgent) {
                $agent = AgentDetails::find($idInt);
                $em = $agent ? ($agent->email ?: $agent->business_email) : null;
                if ($em) {
                    $emails[] = $em;
                }
            } else {
                $admin = Admin::find($idInt);
                if ($admin && ! empty($admin->email)) {
                    $emails[] = $admin->email;
                }
            }
        }

        return $emails !== [] ? implode(', ', array_unique($emails)) : $toMail;
    }
}
