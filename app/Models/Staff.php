<?php

namespace App\Models;

use App\Models\Document;
use App\Support\CrmSheets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use Notifiable, Sortable, HasApiTokens;

    /**
     * The authentication guard for staff (CRM login uses 'admin' guard).
     */
    protected $guard = 'admin';

    /**
     * The table associated with the model.
     */
    protected $table = 'staff';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'country_code',
        'phone',
        'status',
        'role',
        'position',
        'team',
        'permission',
        'sheet_access',
        'office_id',
        'show_dashboard_per',
        'time_zone',
        'is_migration_agent',
        'marn_number',
        'legal_practitioner_number',
        'company_name',
        'company_website',
        'business_address',
        'business_phone',
        'business_mobile',
        'business_email',
        'tax_number',
        'service_token',
        'token_generated_at',
        'quick_access_enabled',
        'grant_super_admin_access',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'integer',
        'show_dashboard_per' => 'integer',
        'is_migration_agent' => 'integer',
        'quick_access_enabled' => 'boolean',
        'grant_super_admin_access' => 'boolean',
        'token_generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Sortable columns for listings.
     */
    public $sortable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'status',
        'created_at',
        'updated_at',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    /**
     * Get the office/branch this staff member belongs to.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'office_id');
    }

    /**
     * Get the role/user type for this staff member.
     */
    public function usertype(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role', 'id');
    }

    /**
     * Get the clients assigned to this staff member (as migration agent).
     * Clients are in admins table with agent_id = this staff's id.
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(Admin::class, 'agent_id');
    }

    /**
     * Get documents created by this staff member.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    // ============================================================
    // ATTRIBUTES
    // ============================================================

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->email;
    }

    /**
     * Get avatar URL (replaces profile_img - uses static avatar.png).
     */
    public function getProfileImgAttribute(): string
    {
        return asset('img/avatar.png');
    }

    /**
     * Alias for business_address (used by views expecting address).
     */
    public function getAddressAttribute(): ?string
    {
        return $this->business_address;
    }

    /**
     * Set address (maps to business_address).
     */
    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['business_address'] = $value;
    }

    /**
     * State from office/branch if available.
     */
    public function getStateAttribute(): ?string
    {
        return $this->office?->state ?? null;
    }

    /**
     * City from office/branch if available.
     */
    public function getCityAttribute(): ?string
    {
        return $this->office?->city ?? null;
    }

    /**
     * Zip from office/branch if available.
     */
    public function getZipAttribute(): ?string
    {
        return $this->office?->zip ?? null;
    }

    /**
     * Scope for active staff (status = 1).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Exclude staff explicitly marked inactive (status = 0); include active (1) and legacy NULL rows.
     */
    public function scopeAssignableForMatterDropdowns($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '<>', 0);
        });
    }

    /**
     * Migration Agent choices for checklist create + cost assignment modals.
     */
    public static function assignmentDropdownMigrationAgentsQuery(): Builder
    {
        $roleIds = config('crm.assignment_dropdown_migration_agent_role_ids', [16]);
        if (! is_array($roleIds) || $roleIds === []) {
            $roleIds = [16];
        }

        return static::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->assignableForMatterDropdowns()
            ->where(function ($q) use ($roleIds) {
                $q->whereIn('role', $roleIds)->orWhere('is_migration_agent', 1);
            })
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    /**
     * Person Responsible choices (same modals).
     */
    public static function assignmentDropdownPersonResponsibleQuery(): Builder
    {
        $roleIds = config('crm.assignment_dropdown_person_responsible_role_ids', [12]);
        if (! is_array($roleIds) || $roleIds === []) {
            $roleIds = [12];
        }

        return static::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->assignableForMatterDropdowns()
            ->whereIn('role', $roleIds)
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    /**
     * Person Assisting choices (same modals). Role IDs align with CRM person_assisting_role_ids.
     */
    public static function assignmentDropdownPersonAssistingQuery(): Builder
    {
        $roleIds = config('crm.person_assisting_role_ids', [13]);
        if (! is_array($roleIds) || $roleIds === []) {
            $roleIds = [13];
        }

        return static::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->assignableForMatterDropdowns()
            ->whereIn('role', $roleIds)
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    /**
     * Whether the staff role grants a CRM module key (e.g. "20" = clients / sheets base access).
     */
    public function hasCrmModule(string $moduleId = '20'): bool
    {
        $roleModel = UserRole::find($this->role);
        if (! $roleModel || $roleModel->module_access === null || $roleModel->module_access === '') {
            return false;
        }
        $decoded = json_decode($roleModel->module_access);
        $moduleAccess = is_array($decoded) ? $decoded : (array) $decoded;

        return array_key_exists($moduleId, $moduleAccess);
    }

    /**
     * Sheet menu entries this user may see (module 20 + per-staff sheet whitelist).
     *
     * @return array<string, string> sheet_key => label
     */
    public function visibleCrmSheetMenuItems(): array
    {
        if (! $this->hasCrmModule('20')) {
            return [];
        }
        $out = [];
        foreach (CrmSheets::definitions() as $key => $label) {
            if ($this->allowsCrmSheet($key)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    /**
     * Whether this staff member may open a CRM sheet (whitelist in sheet_access JSON).
     * Null or empty column means unrestricted (all sheets), for backward compatibility.
     * Super admin (role id 1) is never restricted by sheet_access.
     * Malformed JSON denies access (fail closed).
     */
    public function allowsCrmSheet(string $sheetKey): bool
    {
        if (in_array((int) ($this->role ?? 0), [1, 12], true)) {
            return true;
        }

        $raw = $this->attributes['sheet_access'] ?? null;
        if ($raw === null || $raw === '') {
            return true;
        }
        $list = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($list)) {
            return false;
        }

        return in_array($sheetKey, $list, true);
    }

    /**
     * Role IDs allowed to open the front-desk check-in wizard (aligned with FrontDeskCheckInController).
     *
     * @see config('crm.front_desk_checkin_role_ids') — default includes role 14 (Calling / Reception).
     */
    public static function frontDeskCheckInRoleIds(): array
    {
        return array_values(array_unique(array_merge(
            config('crm.front_desk_checkin_role_ids', [1, 12, 14, 17]),
            config('crm_access.exempt_role_ids', [])
        )));
    }

    /**
     * Whether this staff member may use the front-desk check-in feature.
     */
    public function canAccessFrontDeskCheckIn(): bool
    {
        $sid = (int) ($this->id ?? 0);
        if ($sid > 0 && in_array($sid, config('crm_access.exempt_staff_ids', []), true)) {
            return true;
        }

        return in_array((int) ($this->role ?? 0), self::frontDeskCheckInRoleIds(), true);
    }

}
