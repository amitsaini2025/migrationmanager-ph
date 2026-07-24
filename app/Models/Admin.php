<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Authenticatable
{
    use Notifiable, Sortable, HasFactory, HasApiTokens; // Add HasApiTokens

    /** Staff reminder on client/lead detail — never show modal again */
    public const GOOGLE_REVIEW_REMINDER_NOT_INTERESTED = 'not_interested';

    /** Client has left a review — never show modal again */
    public const GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED = 'review_received';

	// The authentication guard for admin
    protected $guard = 'admin';

	/**
      * The attributes that are mass assignable.
      *
      * @var array
	*/
	protected $fillable = [
        'id',
        // Core Identity
        'first_name', 'last_name', 'email', 'password',
        // Role: deprecated for clients/leads (use type). Column kept for backward compat.
        'role',
        // Type: 'client' or 'lead' for CRM (replaces role=7 for clients/leads)
        'type',
        // CRM reference (unique per client/lead — format: PREFIX+YEAR+COUNTER)
        'client_id', 'client_counter',
        // Contact Information
        'phone', 'country_code',
        // Address
        'country', 'state', 'city', 'address', 'zip',
        // Profile (profile_img removed - use avatar.png)
        'status', 'verified',
        // Lead pipeline (admins.type = lead); clients may ignore
        'lead_status', 'followup_date', 'user_id',
        // Company Lead/Client Flag (company data stored in companies table)
        'is_company',
        // API/Service Tokens
        'service_token', 'token_generated_at',
        // Client Portal (for staff access)
        'cp_status', 'cp_random_code', 'cp_code_verify', 'cp_token_generated_at',
        // EOI Qualification Fields (for immigration points calculation)
        'australian_study', 'australian_study_date', 'specialist_education', 'specialist_education_date', 'regional_study', 'regional_study_date',
        // Verification (staff can verify documents / form details)
        'visa_expiry_verified_at', 'visa_expiry_verified_by',
        'details_verified_at', 'details_verified_by',
        // Archive fields
        'is_archived', 'archived_by', 'archived_on',
        // Personal
        'marital_status',
        // Client/Lead Tags
        'tagname',
        // Google review staff reminder (client/lead detail)
        'google_review_reminder_status',
        'google_review_reminder_snooze_until',
        // Public inquiry form
        'manual_form_fill',
        'visa_type',
        'source',
        // Timestamps
        'created_at', 'updated_at'
    ];

	/**
      * The attributes that should be hidden for arrays.
      *
      * @var array
	*/
    protected $hidden = [
        'password', 'remember_token', 'cp_random_code'
    ];

    protected $casts = [
        'followup_date' => 'datetime',
        'google_review_reminder_snooze_until' => 'datetime',
        'archived_on' => 'datetime',
        'details_verified_at' => 'datetime',
        'visa_expiry_verified_at' => 'datetime',
    ];

	public $sortable = [
        'id',
        'client_id',
        'first_name',
        'last_name',
        'email',
        'status',
        'created_at',
        'updated_at'
    ];

    protected static function booted(): void
    {
        static::saving(function (Admin $admin) {
            if ($admin->email !== null && $admin->email !== '') {
                $admin->email = self::sanitizeEmailAddress($admin->email);
            }
        });
    }

	public function countryData()
    {
        return $this->belongsTo('App\\Models\\Country','country');
    }

	// REMOVED: State model has been deleted
	// public function stateData()
    // {
    //     return $this->belongsTo('App\\Models\\State','state');
    // }
	public function usertype()
    {
        return $this->belongsTo('App\\Models\\UserRole', 'role', 'id');
    }


	/**
     * Get full name attribute
    */
	public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get avatar URL (replaces profile_img - uses static avatar.png).
     */
    public function getProfileImgAttribute(): string
    {
        return asset('img/avatar.png');
    }

    /**
     * Get age attribute - calculates from DOB on-the-fly
     * Falls back to stored age if DOB is not available
     * Always returns accurate age when DOB exists
     * 
     * @return string|null
     */
    public function getAgeAttribute($value)
    {
        // If DOB exists, calculate age on-the-fly (always accurate)
        if ($this->dob && $this->dob !== null) {
            try {
                $dobDate = \Carbon\Carbon::parse($this->dob);
                $now = \Carbon\Carbon::now();
                
                // Don't calculate for future dates
                if ($dobDate->isFuture()) {
                    return $value; // Return stored value or null
                }
                
                $diff = $now->diff($dobDate);
                return $diff->y . ' years ' . $diff->m . ' months';
            } catch (\Exception $e) {
                // If calculation fails, return stored value
                return $value;
            }
        }
        
        // If no DOB, return stored age value (or null)
        return $value;
    }

    // ============================================================
    // STAFF RELATIONSHIPS (agent_id, created_by, verified_by reference admins or staff)
    // ============================================================

    /**
     * Staff member who archived this client/lead.
     */
    public function archivedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'archived_by');
    }

    /**
     * Get the clients assigned to this staff member (as agent)
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'agent_id');
    }

    /**
     * Get the documents created by this staff member
     */
    public function createdDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Document::class, 'created_by');
    }

    /**
     * Alias for createdDocuments() - for backward compatibility
     */
    public function documents(): HasMany
    {
        return $this->hasMany(\App\Models\Document::class, 'created_by');
    }

    /**
     * Get DOB verifications done by this staff member
     */
    public function dobVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'dob_verified_by');
    }

    /**
     * Get phone verifications done by this staff member
     */
    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'phone_verified_by');
    }

    /**
     * Get visa expiry verifications done by this staff member
     */
    public function visaExpiryVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'visa_expiry_verified_by');
    }

    /**
     * Staff member who last verified this client/company form details.
     */
    public function detailsVerifiedByStaff()
    {
        return $this->belongsTo(Staff::class, 'details_verified_by');
    }

    /**
     * Get the EOI/ROI references for this client
     * Note: Admins table also stores client records
     */
    public function eoiReferences(): HasMany
    {
        return $this->hasMany(\App\Models\ClientEoiReference::class, 'client_id');
    }

    // ============================================================
    // CLIENT RELATIONSHIPS (For Immigration/EOI Data)
    // ============================================================

    /**
     * Get the partner/spouse details for this client
     * Used for EOI points calculation
     */
    public function partner()
    {
        return $this->hasOne(\App\Models\ClientSpouseDetail::class, 'client_id');
    }

    /**
     * Get the test scores (IELTS, PTE, TOEFL, etc.) for this client
     * Used for English proficiency points calculation
     */
    public function testScores(): HasMany
    {
        return $this->hasMany(\App\Models\ClientTestScore::class, 'client_id');
    }

    /**
     * Get the occupations/skills assessments for this client
     * Used for occupation and work experience points calculation
     */
    public function occupations(): HasMany
    {
        return $this->hasMany(\App\Models\ClientOccupation::class, 'client_id');
    }

    /**
     * Get the qualifications for this client
     * Used for education points calculation
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(\App\Models\ClientQualification::class, 'client_id');
    }

    /**
     * Get the work experiences for this client
     * Used for employment points calculation
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(\App\Models\ClientExperience::class, 'client_id');
    }

    /**
     * Get the relationships (partner, children, parents, etc.) for this client
     * Used for family member information
     */
    public function relationships(): HasMany
    {
        return $this->hasMany(\App\Models\ClientRelationship::class, 'client_id');
    }

    // ============================================================
    // COMPANY LEAD/CLIENT RELATIONSHIPS
    // ============================================================

    /**
     * Get the client matters for this client.
     */
    public function clientMatters(): HasMany
    {
        return $this->hasMany(\App\Models\ClientMatter::class, 'client_id');
    }

    /**
     * Get the company data for this admin (if it's a company)
     */
    public function company()
    {
        return $this->hasOne(Company::class, 'admin_id', 'id');
    }

    /**
     * Get companies where this person is the contact person
     */
    public function companiesAsContactPerson()
    {
        return $this->hasMany(Company::class, 'contact_person_id', 'id');
    }

    /**
     * Employer nominations listing this client/lead as the nominated person.
     */
    public function companyNominationsAsNominee(): HasMany
    {
        return $this->hasMany(\App\Models\CompanyNomination::class, 'nominated_client_id', 'id')
            ->orderBy('sort_order');
    }

    /**
     * Check if this is a company
     */
    public function isCompany(): bool
    {
        return (bool) $this->is_company;
    }

    /**
     * Align admins.type with lifecycle: a lead with at least one active client_matter should be a client.
     * Used when matters are created/saved and for one-off backfills (visa sheets read type for is_lead).
     */
    public static function promoteLeadWithActiveMatterToClient(int $adminId): bool
    {
        $admin = static::query()->whereKey($adminId)->first();
        if (!$admin || $admin->type !== 'lead') {
            return false;
        }

        $hasActive = ClientMatter::query()
            ->where('client_id', $adminId)
            ->where('matter_status', 1)
            ->exists();
        if (!$hasActive) {
            return false;
        }

        $admin->type = 'client';
        $admin->lead_status = 'converted';
        if ((string) ($admin->status ?? '') !== '1') {
            $admin->status = '1';
        }

        return $admin->save();
    }

    /**
     * Get display name (company name or personal name)
     * For companies: "Company Name (Contact: Person Name)"
     * For personal: "First Name Last Name"
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_company && $this->company) {
            $companyName = $this->company->company_name ?? 'Unnamed Company';
            if ($this->company->contactPerson) {
                $contactName = trim($this->company->contactPerson->first_name . ' ' . $this->company->contactPerson->last_name);
                return "{$companyName} (Contact: {$contactName})";
            }
            return $companyName;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get company name or fallback to personal name
     */
    public function getCompanyNameOrPersonalNameAttribute(): string
    {
        if ($this->is_company && $this->company) {
            return $this->company->company_name ?? 'Unnamed Company';
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get company name from companies table (for clients with is_company=1).
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->company?->company_name;
    }

    /**
     * Get company website from companies table.
     */
    public function getCompanyWebsiteAttribute(): ?string
    {
        return $this->company?->company_website;
    }

    /**
     * Normalize an email for storage/outbound use.
     * Trims whitespace and trailing punctuation (e.g. "user@gmail.com.").
     */
    public static function sanitizeEmailAddress(?string $email): string
    {
        $email = trim((string) $email);
        // Paste / sentence-ending periods and similar punctuation after the address
        $email = rtrim($email, '.,;:');

        return trim($email);
    }

    /**
     * Placeholder emails stored on company admin rows when the real email
     * already exists (admins.email unique). Not valid for outbound mail.
     */
    public static function isInternalPlaceholderEmail(?string $email): bool
    {
        $email = strtolower(self::sanitizeEmailAddress($email));

        return $email === '' || str_ends_with($email, '@lead.internal');
    }

    /**
     * First deliverable (non-placeholder) email for this client/lead.
     * Order: admins.email → client_emails → company contact person.
     */
    public function resolveDeliverableEmail(): ?string
    {
        $candidates = [];

        $primary = self::sanitizeEmailAddress($this->email ?? '');
        if ($primary !== '') {
            $candidates[] = $primary;
        }

        $rows = ClientEmail::where('client_id', $this->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderByRaw("CASE WHEN email_type = 'Personal' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->pluck('email');

        foreach ($rows as $rowEmail) {
            $candidates[] = self::sanitizeEmailAddress($rowEmail);
        }

        if ($this->isCompany()) {
            $this->loadMissing('company.contactPerson');
            $contact = $this->company?->contactPerson;
            if ($contact) {
                $cpEmail = self::sanitizeEmailAddress($contact->email ?? '');
                if ($cpEmail !== '') {
                    $candidates[] = $cpEmail;
                }
                $cpRows = ClientEmail::where('client_id', $contact->id)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->orderBy('id')
                    ->pluck('email');
                foreach ($cpRows as $rowEmail) {
                    $candidates[] = self::sanitizeEmailAddress($rowEmail);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && ! self::isInternalPlaceholderEmail($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Email + display name for signature / outbound client mail.
     *
     * @return array{email: string, name: string}|null
     */
    public function resolveSigningRecipient(): ?array
    {
        $email = $this->resolveDeliverableEmail();
        if ($email === null) {
            return null;
        }

        $name = trim((string) (($this->first_name ?? '') . ' ' . ($this->last_name ?? '')));

        if ($this->isCompany()) {
            $this->loadMissing('company.contactPerson');
            $contact = $this->company?->contactPerson;
            if ($contact) {
                $cpName = trim((string) (($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')));
                if ($cpName !== '') {
                    $name = $cpName;
                }
            }
            if ($name === '') {
                $name = (string) ($this->company_name ?? '');
            }
        }

        if ($name === '') {
            $name = $email;
        }

        return ['email' => $email, 'name' => $name];
    }
}
