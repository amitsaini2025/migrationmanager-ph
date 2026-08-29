<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AppointmentSyncLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'appointment_sync_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sync_type',
        'started_at',
        'completed_at',
        'status',
        'appointments_fetched',
        'appointments_new',
        'appointments_updated',
        'appointments_skipped',
        'appointments_failed',
        'error_message',
        'sync_details',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sync_details' => 'array',
        ];
    }

    /**
     * Scope: Recent logs
     */
    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderBy('started_at', 'desc')->limit($limit);
    }

    /**
     * Scope: Failed syncs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Successful syncs
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Running syncs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: By sync type
     */
    public function scopeBySyncType($query, string $type)
    {
        return $query->where('sync_type', $type);
    }

    /**
     * Scope: Today's syncs
     */
    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    /**
     * Get sync duration in seconds
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->started_at && $this->completed_at) {
                    return $this->started_at->diffInSeconds($this->completed_at);
                }
                return null;
            }
        );
    }

    /**
     * Get human-readable duration
     */
    protected function durationHuman(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Calculate duration directly to avoid recursion
                if (!$this->started_at || !$this->completed_at) {
                    return 'N/A';
                }

                $seconds = $this->started_at->diffInSeconds($this->completed_at);
                
                if ($seconds < 60) {
                    return "{$seconds}s";
                }
                
                $minutes = floor($seconds / 60);
                $remainingSeconds = $seconds % 60;
                
                return "{$minutes}m {$remainingSeconds}s";
            }
        );
    }

    /**
     * Get status badge color
     */
    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'success' => 'success',
                'failed' => 'danger',
                'running' => 'info',
                default => 'secondary'
            }
        );
    }

    /**
     * Get sync type display name
     */
    protected function syncTypeDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->sync_type) {
                'polling' => 'Automatic Polling',
                'manual' => 'Manual Sync',
                'backfill' => 'Historical Backfill',
                'webhook' => 'Website Webhook Push',
                default => ucfirst($this->sync_type)
            }
        );
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRateAttribute(): ?float
    {
        $total = $this->appointments_fetched;
        
        if ($total === 0) {
            return null;
        }

        $successful = $this->appointments_new + $this->appointments_updated;
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Check if sync is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if sync was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if sync failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if sync took too long (>5 minutes)
     */
    public function isSlow(): bool
    {
        $duration = $this->duration;
        return $duration !== null && $duration > 300; // 5 minutes
    }

    /**
     * Get summary text
     */
    public function getSummaryAttribute(): string
    {
        if ($this->status === 'running') {
            return 'Sync in progress...';
        }

        if ($this->appointments_fetched === 0) {
            return 'No appointments found';
        }

        $parts = [];
        
        if ($this->appointments_new > 0) {
            $parts[] = "{$this->appointments_new} new";
        }
        
        if ($this->appointments_updated > 0) {
            $parts[] = "{$this->appointments_updated} updated";
        }
        
        if ($this->appointments_skipped > 0) {
            $parts[] = "{$this->appointments_skipped} skipped";
        }
        
        if ($this->appointments_failed > 0) {
            $parts[] = "{$this->appointments_failed} failed";
        }

        return empty($parts) ? 'No changes' : implode(', ', $parts);
    }
}

