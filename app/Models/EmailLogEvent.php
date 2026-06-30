<?php

namespace App\Models;

use App\Helpers\IconHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLogEvent extends Model
{
    protected $table = 'email_log_events';

    protected $fillable = [
        'email_log_id',
        'event_type',
        'occurred_at',
        'metadata',
        'sendgrid_event_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }

    public function label(): string
    {
        return match ($this->event_type) {
            'processed'   => 'Processing',
            'delivered'   => 'Delivered',
            'deferred'    => 'Delayed',
            'blocked'     => 'Blocked',
            'bounced'     => 'Undelivered',
            'dropped'     => 'Failed',
            'open'        => 'Opened',
            'click'       => 'Link clicked',
            'spamreport'  => 'Marked as spam',
            'unsubscribe' => 'Unsubscribed',
            'group_unsubscribe' => 'Unsubscribed from group',
            'group_resubscribe' => 'Resubscribed to group',
            default       => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    public function iconClass(): string
    {
        return match ($this->event_type) {
            'processed'   => 'fas fa-cog text-info',
            'delivered'   => 'fas fa-check-circle text-success',
            'deferred', 'blocked' => 'fas fa-clock text-warning',
            'bounced', 'dropped' => 'fas fa-times-circle text-danger',
            'open'        => 'fas fa-eye text-primary',
            'click'       => 'fas fa-mouse-pointer text-primary',
            'spamreport'  => 'fas fa-exclamation-triangle text-danger',
            'unsubscribe', 'group_unsubscribe' => 'fas fa-ban text-danger',
            'group_resubscribe' => 'fas fa-check text-success',
            default       => 'fas fa-circle text-muted',
        };
    }

    public function iconHtml(): string
    {
        $legacy = $this->iconClass();
        $colorClass = '';

        if (preg_match('/\b(text-\S+|text-muted)\b/', $legacy, $matches)) {
            $colorClass = $matches[1];
            $legacy = trim(preg_replace('/\b(text-\S+|text-muted)\b/', '', $legacy) ?? $legacy);
        }

        return IconHelper::fromLegacy($legacy, ['class' => trim($colorClass)]);
    }
}
