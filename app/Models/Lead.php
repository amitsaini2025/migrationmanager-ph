<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Staff;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Kyslik\ColumnSortable\Sortable;

class Lead extends Admin
{
    use Notifiable, Sortable;
    
    // Use the same table as Admin
    protected $table = 'admins';
    
    // Lead-specific sortable columns
    public $sortable = [
        'id', 
        'first_name', 
        'last_name', 
        'email', 
        'phone',
        'status',
        'lead_status',
        'created_at', 
        'updated_at'
    ];
    
    /**
     * Boot method to add global scopes
     */
    protected static function booted(): void
    {
        // Inherit Admin email sanitization on save
        parent::booted();

        // Automatically filter all queries to leads only
        static::addGlobalScope('lead', function (Builder $builder) {
            $builder->where('type', 'lead')
                    ->whereNull('is_deleted');
        });
        
        // Automatically set type when creating a new lead
        static::creating(function ($lead) {
            $lead->type = 'lead';
            if (!isset($lead->is_archived)) {
                $lead->is_archived = 0;
            }
        });
    }
    
    /**
     * Include archived leads in query
     * Usage: Lead::withArchived()->get()
     */
    public function scopeWithArchived(Builder $query)
    {
        return $query->withoutGlobalScope('lead')
                    ->where('type', 'lead')
                    ->whereNull('is_deleted');
    }
    
    /**
     * Get only archived leads
     * Usage: Lead::onlyArchived()->get()
     */
    public function scopeOnlyArchived(Builder $query)
    {
        return $query->withoutGlobalScope('lead')
                    ->where('type', 'lead')
                    ->where('is_archived', 1)
                    ->whereNull('is_deleted');
    }
    
    /**
     * Filter by lead status
     * Usage: Lead::status('active')->get()
     */
    public function scopeStatus(Builder $query, $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Filter by lead source
     * Usage: Lead::fromSource('website')->get()
     */
    public function scopeFromSource(Builder $query, $source)
    {
        return $query->where('source', $source);
    }
    
    /**
     * Get the staff member assigned to this lead
     */
    public function assignedTo()
    {
        return $this->belongsTo(Staff::class, 'user_id', 'id');
    }

    /**
     * @deprecated Use assignedTo() - kept for backward compatibility
     */
    public function createdBy()
    {
        return $this->assignedTo();
    }
    
    /**
     * Convert lead to client
     */
    public function convertToClient()
    {
        app(\App\Services\LeadFollowUpNoteService::class)->completeOpenFollowUpNotes((int) $this->id);

        // Mark lead as converted
        $this->type = 'client';
        $this->lead_status = 'converted';
        $this->status = 1;
        $this->save();
        
        // Log the conversion in activities
        \App\Models\ActivitiesLog::create([
            'client_id' => $this->id,
            'created_by' => \Auth::id(),
            'subject' => 'Lead converted to Client',
            'description' => "Lead successfully converted to client.",
            'activity_type' => 'lead_converted',
            'task_status' => 0,
            'pin' => 0,
        ]);
        
        // Return as Admin model instance with client type
        return Admin::find($this->id);
    }
    
    /**
     * Archive this lead
     */
    public function archive()
    {
        $this->is_archived = 1;
        $this->archived_by = Auth::guard('admin')->id();
        $this->archived_on = now();

        return $this->save();
    }
    
    /**
     * Unarchive this lead
     */
    public function unarchive()
    {
        $this->is_archived = 0;
        $this->archived_by = null;
        $this->archived_on = null;

        return $this->save();
    }
    
    /**
     * Soft delete (set is_deleted timestamp)
     */
    public function softDelete()
    {
        $this->is_deleted = now();
        return $this->save();
    }
    
    /**
     * Check if lead is archived
     */
    public function isArchived()
    {
        return $this->is_archived == 1;
    }

    /**
     * send_to_legal_crm values on admins.
     * 0 = not requested, 2 = queued for cron, 1 = successfully sent to Legal CRM.
     */
    public const LEGAL_CRM_NOT_SENT = 0;

    public const LEGAL_CRM_SENT = 1;

    public const LEGAL_CRM_PENDING = 2;

    /**
     * Mark lead as sent to Legal CRM (send_to_legal_crm = 1).
     */
    public function markSentToLegalCrm(): bool
    {
        $this->send_to_legal_crm = self::LEGAL_CRM_SENT;

        return $this->save();
    }

    /**
     * Queue lead for Legal CRM sync via cron (send_to_legal_crm = 2).
     */
    public function markPendingForLegalCrm(): bool
    {
        $this->send_to_legal_crm = self::LEGAL_CRM_PENDING;

        return $this->save();
    }

    /**
     * Whether this lead has been sent to Legal CRM.
     */
    public function isSentToLegalCrm(): bool
    {
        return (int) ($this->send_to_legal_crm ?? 0) === self::LEGAL_CRM_SENT;
    }

    /**
     * Whether this lead is queued waiting for Legal CRM cron sync.
     */
    public function isPendingLegalCrm(): bool
    {
        return (int) ($this->send_to_legal_crm ?? 0) === self::LEGAL_CRM_PENDING;
    }
    
    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
}
