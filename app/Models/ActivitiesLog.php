<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * ActivitiesLog Model
 * 
 * Represents activity logs and action-related activities in the system.
 * 
 * Database field clarifications for the Action feature:
 * - task_status (field name preserved): Action completion status (0 = incomplete action, 1 = completed action)
 * - task_group (field name preserved): The action category (Call, Checklist, Review, Query, Urgent, Personal Action)
 * - followup_date (field name preserved): The scheduled date for the action
 * - activity_type: Can include 'followup_scheduled', 'followup_completed', etc. (these refer to Actions in the UI)
 * - Action assign/complete log subjects (e.g. "Set action for …") should not embed the client display name; the row is already tied to client_id and the UI prepends the actor (staff) name.
 * 
 * Note: Field names contain "task" and "followup" for database compatibility but refer to Actions in the UI
 */
class ActivitiesLog extends Authenticatable
{
    use Notifiable;
	use Sortable;
	
	protected $fillable = [
		'client_id',
		'created_by',
		'subject',
		'description',
		'sms_log_id',
		'activity_type',
		'source',
		'use_for',
		'followup_date',
		'task_group',
		'task_status',
		'pin',
	];

	protected $casts = [
		'followup_date' => 'datetime',
		'pin' => 'boolean',
	];

	public $sortable = [
		'id',
		'client_id',
		'created_by',
		'activity_type',
		'created_at',
	];

	/**
	 * Get the client this activity belongs to
	 */
	public function client()
	{
		return $this->belongsTo(Admin::class, 'client_id');
	}

	/**
	 * Get the staff member who created this activity
	 */
	public function creator()
	{
		return $this->belongsTo(Staff::class, 'created_by');
	}

	/**
	 * Alias for creator() — activity feed and filters use this name.
	 */
	public function staff()
	{
		return $this->belongsTo(Staff::class, 'created_by');
	}

	/**
	 * Get the SMS log if this is an SMS activity
	 */
	public function smsLog()
	{
		return $this->belongsTo(SmsLog::class, 'sms_log_id');
	}

	/**
	 * Scope: Filter by client
	 */
	public function scopeForClient($query, $clientId)
	{
		return $query->where('client_id', $clientId);
	}

	/**
	 * Scope: Filter by activity type
	 */
	public function scopeByType($query, $type)
	{
		return $query->where('activity_type', $type);
	}

	/**
	 * Scope: SMS activities only
	 */
	public function scopeSmsActivities($query)
	{
		return $query->where('activity_type', 'sms');
	}

	/**
	 * Scope: Pinned activities
	 */
	public function scopePinned($query)
	{
		return $query->where('pin', 1);
	}

	/**
	 * Check if this is an SMS activity
	 */
	public function isSmsActivity()
	{
		return $this->activity_type === 'sms';
	}

	/**
	 * Check if activity is pinned
	 */
	public function isPinned()
	{
		return (bool) $this->pin;
	}

	/**
	 * Activity icon token (fa-* legacy name or Lucide kebab name).
	 */
	public function getIconAttribute()
	{
		return match($this->activity_type) {
			'sms' => 'fa-sms',
			'activity' => 'fa-bolt',
			'stage' => 'fa-route',
			'email' => 'fa-envelope',
			'document' => 'fa-file-alt',
			'signature' => 'fa-file-signature',
			'note' => 'fa-sticky-note',
			'financial' => 'fa-dollar-sign',
			'lead_converted' => 'fa-user-check',
			'followup_scheduled' => 'fa-calendar-plus',
			'followup_completed' => 'fa-calendar-check',
			'followup_rescheduled' => 'fa-calendar-alt',
			'followup_cancelled' => 'fa-calendar-times',
			default => 'fa-sticky-note',
		};
	}

	/**
	 * Render activity type icon as Lucide HTML.
	 */
	public function iconHtml(array $attributes = []): string
	{
		$classes = trim(($this->icon_color ?? '') . ' ' . ($attributes['class'] ?? ''));

		return \App\Helpers\IconHelper::renderStored(
			$this->icon,
			array_merge($attributes, ['class' => $classes ?: null])
		);
	}

	/**
	 * Get activity icon color based on type
	 */
	public function getIconColorAttribute()
	{
		return match($this->activity_type) {
			'sms' => 'text-info',
			'activity' => 'text-primary',
			'stage' => 'text-primary',
			'email' => 'text-primary',
			'document' => 'text-info',
			'signature' => 'text-info',
			'note' => 'text-warning',
			'financial' => 'text-success',
			'lead_converted' => 'text-success',
			'followup_scheduled' => 'text-info',
			'followup_completed' => 'text-success',
			'followup_rescheduled' => 'text-warning',
			'followup_cancelled' => 'text-danger',
			default => 'text-secondary',
		};
	}

	/**
	 * Client portal signature activities are stored with created_by = responsible staff (for notifications),
	 * but the subject already starts with the client/signer name. Activity feed should not prepend staff name.
	 */
	public static function displaySubjectWithoutStaffPrefix(?string $activityType, ?string $subject): bool
	{
		if (($activityType ?? '') !== 'document' || $subject === null || $subject === '') {
			return false;
		}
		$lower = strtolower($subject);

		return strpos($lower, 'signed document') !== false
			|| strpos($lower, 'signed cost agreement') !== false;
	}

	/**
	 * Format followup_date for activity feed display (handles raw DB values and ISO strings).
	 */
	public static function formatFollowupDateForDisplay(mixed $value): string
	{
		if ($value === null || $value === '') {
			return '';
		}

		try {
			$dt = \Carbon\Carbon::parse($value);
			if ($dt->format('H:i:s') === '00:00:00') {
				return $dt->format('d M Y');
			}

			return $dt->format('d M Y, H:i A');
		} catch (\Exception $e) {
			return is_string($value) ? $value : '';
		}
	}
}
