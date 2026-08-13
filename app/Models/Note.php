<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

/**
 * Note Model
 * 
 * Represents both regular notes and actions (formerly called tasks/followups) in the system.
 * 
 * Database field clarifications for the Action feature:
 * - is_action: When set to 1, this note is an Action item. 0 = regular note
 * - task_group (field name preserved): The action category (Call, Checklist, Review, Query, Urgent, Personal Action, Follow Up)
 * - Follow Up: is_action = 1 AND task_group = 'Follow Up' (Action page Follow up tab)
 * - action_date: The scheduled date for the action
 * - task_status (in ActivitiesLog): Action completion status (0 = incomplete, 1 = completed)
 * - assigned_to: The staff member assigned to complete this action
 * - status: '0' = active/incomplete, '1' = completed
 * 
 * Note: Field names contain "task" and "followup" for database compatibility but refer to Actions in the UI
 */
class Note extends Model
{
    use Notifiable;

    protected $fillable = [
        'id','user_id','client_id','lead_id','unique_group_id','title','description','note_deadline','mail_id','type','pin','action_date','is_action','assigned_to','status','task_group','matter_id','mobile_number','created_at', 'updated_at'
    ];

	public $sortable = ['id', 'created_at', 'updated_at','task_group','action_date'];


    /**
     * Get the client that owns the note.
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the staff member who created the note.
     */
    public function user()
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Alias for user() - Staff/Client/Lead terminology.
     */
    public function createdByStaff()
    {
        return $this->user();
    }

    /**
     * Get the staff member assigned to the note/action.
     */
    public function assignedUser()
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    /**
     * Alias for assignedUser() - Staff/Client/Lead terminology.
     */
    public function assignedStaff()
    {
        return $this->assignedUser();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteClient()
    {
        return $this->client();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteUser()
    {
        return $this->user();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteStaff()
    {
        return $this->user();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function assigned_user()
    {
        return $this->assignedUser();
    }

    /**
     * Legacy alias - Staff terminology
     */
    public function assigned_staff()
    {
        return $this->assignedUser();
    }

    /**
     * Legacy relationship - Appointment model has been removed
     * This relationship is kept for backward compatibility but will return null
     * 
     * @deprecated Appointment system has been removed
     */
    public function lead()
    {
        // Appointment model no longer exists - old appointment system removed
        // Returning null to prevent errors
        return null;
    }

    /**
     * Whether the staff member may update or delete this client action.
     * Allowed: super admin, assignee, or creator (Assigned by Me).
     */
    public function canBeModifiedBy(?Authenticatable $staff): bool
    {
        if (! $staff) {
            return false;
        }

        if ((int) ($staff->role ?? 0) === 1) {
            return true;
        }

        $staffId = (int) $staff->id;

        return (int) $this->assigned_to === $staffId || (int) $this->user_id === $staffId;
    }

}
