<?php

namespace App\Models;

use App\Support\BookingAppointmentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAppointment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'booking_appointments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bansal_appointment_id',
        'order_hash',
        'payment_token',
        'payment_token_expires_at',
        'client_id',
        'consultant_id',
        'assigned_by_admin_id',
        'client_name',
        'client_email',
        'client_phone',
        'client_timezone',
        'appointment_datetime',
        'timeslot_full',
        'duration_minutes',
        'location',
        'inperson_address',
        'meeting_type',
        'preferred_language',
        'service_id',
        'noe_id',
        'enquiry_type',
        'service_type',
        'enquiry_details',
        'status',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'is_paid',
        'amount',
        'discount_amount',
        'final_amount',
        'promo_code',
        'payment_status',
        'payment_method',
        'paid_at',
        'admin_notes',
        'confirmation_email_sent',
        'confirmation_email_sent_at',
        'reminder_sms_sent',
        'reminder_sms_sent_at',
        'synced_from_bansal_at',
        'last_synced_at',
        'sync_status',
        'sync_error',
        'slot_overwrite_hidden',
        'user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Laravel 12: Use casts() method instead of $casts property
     */
    protected function casts(): array
    {
        return [
            'appointment_datetime' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'reminder_sms_sent_at' => 'datetime',
            'synced_from_bansal_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'payment_token_expires_at' => 'datetime',
            'is_paid' => 'boolean',
            'confirmation_email_sent' => 'boolean',
            'reminder_sms_sent' => 'boolean',
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the client that owns the appointment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the consultant assigned to the appointment.
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(AppointmentConsultant::class, 'consultant_id');
    }

    /**
     * Get the staff member who assigned the consultant.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_by_admin_id');
    }

    /**
     * Scope: Active appointments (not cancelled or no-show)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show']);
    }

    /**
     * Scope: Upcoming appointments
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_datetime', '>=', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show']);
    }

    /**
     * Scope: Past appointments
     */
    public function scopePast($query)
    {
        return $query->where('appointment_datetime', '<', now());
    }

    /**
     * Scope: Today's appointments
     */
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_datetime', today());
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending appointments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Confirmed appointments
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope: By location
     */
    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Scope: By calendar type
     */
    public function scopeByCalendarType($query, string $calendarType)
    {
        return $query->whereHas('consultant', function ($q) use ($calendarType) {
            $q->where('calendar_type', $calendarType);
        });
    }

    /**
     * Scope: Needs reminder (24h ahead, not sent yet)
     */
    public function scopeNeedsReminder($query)
    {
        $tomorrow = now()->addDay()->startOfDay();
        $endOfTomorrow = now()->addDay()->endOfDay();

        return $query->where('reminder_sms_sent', false)
            ->where('status', 'confirmed')
            ->whereBetween('appointment_datetime', [$tomorrow, $endOfTomorrow]);
    }

    /**
     * Laravel 12: Use Attribute class for accessors/mutators
     */

    /**
     * Get the formatted appointment date.
     */
    protected function formattedDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->appointment_datetime?->format('d/m/Y'),
        );
    }

    /**
     * Get the formatted appointment time.
     */
    protected function formattedTime(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->appointment_datetime?->format('h:i A'),
        );
    }

    /**
     * Get the status badge color.
     */
    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => BookingAppointmentStatus::badgeClass((string) $this->status),
        );
    }

    /**
     * Get the location display name.
     */
    protected function locationDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->location) {
                'melbourne' => 'Melbourne Office',
                'adelaide' => 'Adelaide Office',
                default => ucfirst($this->location)
            }
        );
    }

    /**
     * Check if appointment is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->appointment_datetime?->isFuture() &&
               ! in_array($this->status, ['completed', 'cancelled', 'no_show']);
    }

    /**
     * Check if appointment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->appointment_datetime?->isPast() &&
               in_array($this->status, [
                   BookingAppointmentStatus::PENDING,
                   BookingAppointmentStatus::AWAITING_CONFIRMATION,
                   BookingAppointmentStatus::CONFIRMED,
               ], true);
    }

    /**
     * Check if should send reminder
     */
    public function shouldSendReminder(): bool
    {
        if ($this->reminder_sms_sent || $this->status !== 'confirmed') {
            return false;
        }

        $tomorrow = now()->addDay();

        return $this->appointment_datetime?->isSameDay($tomorrow);
    }

    /**
     * Get time until appointment in human readable format
     */
    public function getTimeUntilAttribute(): ?string
    {
        return $this->appointment_datetime?->diffForHumans();
    }

    /**
     * Check if payment is completed
     */
    public function isPaymentCompleted(): bool
    {
        return $this->is_paid && $this->payment_status === 'completed';
    }

    /**
     * Get full address for location
     */
    public function getFullAddressAttribute(): string
    {
        return match ($this->location) {
            'melbourne' => 'Level 8/278 Collins St, Melbourne VIC 3000',
            'adelaide' => 'Unit 5, 55 Gawler Pl, Adelaide SA 5000, Australia',
            default => 'Office Address'
        };
    }

    /**
     * Get the status badge color
     * Laravel 12: Accessor for status badge class
     */
    public function getStatusBadgeAttribute(): string
    {
        return BookingAppointmentStatus::badgeClass((string) $this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return BookingAppointmentStatus::label((string) $this->status);
    }
}
