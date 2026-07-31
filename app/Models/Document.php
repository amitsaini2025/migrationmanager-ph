<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin;
use App\Models\Lead;
use App\Models\Staff;
use App\Support\StaffClientVisibility;
use Symfony\Component\Mime\MimeTypes;

class Document extends Model
{
    use Sortable, HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'file_name',
        'filetype', 
        'myfile',
        'myfile_key',
        'user_id',
        'client_id',
        'file_size',
        'type',
        'doc_type',
        'folder_name',
        'mail_type',
        'client_matter_id',
        'cp_list_id',
        'form956_id',
        'office_id',
        'checklist',
        'cp_rejection_reason',
        'cp_doc_status',
        'not_used_doc',
        'status',
        'signature_doc_link',
        'signed_doc_link',
        'is_client_portal_verify',
        'created_by',
        'lead_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $sortable = [
        'id',
        'file_name',
        'status',
        'created_at',
        'updated_at',
    ];

    // Relationships
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class);
    }

    public function signatureFields(): HasMany
    {
        return $this->hasMany(SignatureField::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Alias for user() - Staff/Client/Lead terminology (user_id stores Staff ID).
     */
    public function staff(): BelongsTo
    {
        return $this->user();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function signatureActivities(): HasMany
    {
        return $this->hasMany(SignatureActivity::class)->orderBy('created_at', 'desc');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'office_id');
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAssociated($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('client_id')->orWhereNotNull('lead_id');
        });
    }

    public function scopeAdhoc($query)
    {
        return $query->whereNull('client_id')->whereNull('lead_id');
    }

    public function scopeNotArchived($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'archived');
        });
    }

    /**
     * Scope to filter documents based on user visibility permissions
     * Implements the same logic as DocumentPolicy::view()
     */
    public function scopeVisible($query, $user)
    {
        StaffClientVisibility::restrictDocumentEloquentQuery($query, $user);

        return $query;
    }

    /**
     * Scope to show only signature workflow documents
     * Excludes client file uploads which don't have created_by set
     */
    public function scopeForSignatureWorkflow($query)
    {
        return $query->whereNotNull('created_by');
    }

    /**
     * Scope to filter documents by office (includes matter-derived)
     */
    public function scopeByOffice($query, $officeId)
    {
        return $query->where(function($q) use ($officeId) {
            // Direct office assignment (ad-hoc docs)
            $q->where('documents.office_id', $officeId)
              // Or via client matter
              ->orWhereHas('clientMatter', function($mq) use ($officeId) {
                  $mq->where('office_id', $officeId);
              });
        });
    }

    /**
     * Scope for workflow checklist documents (from client portal application checklists)
     */
    public function scopeWorkflowChecklist($query)
    {
        return $query->whereNotNull('cp_list_id');
    }

    // Accessors
    
    /**
     * Get resolved office (from matter or direct assignment)
     */
    public function getResolvedOfficeAttribute()
    {
        // Priority 1: From client matter
        if ($this->client_matter_id && $this->clientMatter) {
            return $this->clientMatter->office;
        }
        
        // Priority 2: Direct assignment (ad-hoc docs)
        if ($this->office_id && $this->office) {
            return $this->office;
        }
        
        return null;
    }

    /**
     * Get resolved office ID
     */
    public function getResolvedOfficeIdAttribute()
    {
        return $this->resolved_office?->id;
    }

    /**
     * Get resolved office name
     */
    public function getResolvedOfficeNameAttribute()
    {
        return $this->resolved_office?->office_name ?? 'No Office';
    }

    // Existing Accessors
    public function getDisplayTitleAttribute()
    {
        return $this->file_name ?? 'Document';
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'draft' => 'secondary',
            'signature_placed' => 'info',
            'sent' => 'warning', 
            'signed' => 'success',
            default => 'secondary'
        };
    }

    public function getIsOverdueAttribute()
    {
        return false; // due_at column removed
    }

    /**
     * Get documentable entity (client or lead – replaces polymorphic documentable_type/id)
     */
    public function getDocumentableAttribute()
    {
        return $this->client_id ? $this->client : $this->lead;
    }

    /**
     * Get primary signer email (computed from signers – column removed)
     */
    public function getPrimarySignerEmailAttribute(): ?string
    {
        $signer = $this->relationLoaded('signers')
            ? $this->signers->first()
            : $this->signers()->first();

        return $signer?->email;
    }

    /**
     * Get signer count (computed from signers – column removed)
     */
    public function getSignerCountAttribute(): int
    {
        return $this->relationLoaded('signers')
            ? $this->signers->count()
            : $this->signers()->count();
    }

    /**
     * Get visibility type for current authenticated user
     * Returns: 'owner', 'signer', 'associated', or null
     * 
     * NOTE: Use eager loading for signers relationship to avoid N+1 queries
     * Example: Document::with('signers')->get()
     */
    public function getVisibilityTypeAttribute()
    {
        $user = auth('admin')->user();
        if (!$user) {
            return null;
        }

        // Check if user is the creator (highest priority)
        if ($this->created_by === $user->id) {
            return 'owner';
        }

        // Check if user is a signer (use collection if loaded, query if not)
        if ($this->relationLoaded('signers')) {
            $isSigner = $this->signers->contains('email', $user->email);
        } else {
            $isSigner = $this->signers()->where('email', $user->email)->exists();
        }
        
        if ($isSigner) {
            return 'signer';
        }

        // Check if document is associated with user's entity
        if ($this->client_id === $user->id) {
            return 'associated';
        }
        if ($this->lead_id) {
            $lead = $this->relationLoaded('lead') ? $this->lead : $this->lead()->first();
            if ($lead && isset($lead->user_id) && $lead->user_id === $user->id) {
                return 'associated';
            }
        }

        // Admin viewing all
        if ($user->role === 1) {
            return 'admin';
        }

        return null;
    }

    /**
     * Get visibility icon and label
     */
    public function getVisibilityBadgeAttribute()
    {
        return match($this->visibility_type) {
            'owner' => ['icon' => '🔒', 'label' => 'My Document', 'class' => 'badge-owner'],
            'signer' => ['icon' => '✍️', 'label' => 'Need to Sign', 'class' => 'badge-signer'],
            'associated' => ['icon' => '🔗', 'label' => 'Associated', 'class' => 'badge-associated'],
            'admin' => ['icon' => '🌐', 'label' => 'Organization', 'class' => 'badge-admin'],
            default => ['icon' => '👁️', 'label' => 'Visible', 'class' => 'badge-visible']
        };
    }

    /**
     * Get comprehensive status information for display
     */
    public function getStatusInfo()
    {
        $pendingSigners = $this->signers()->where('status', 'pending')->get();
        $openedSigners = $this->signers()->where('status', 'pending')->whereNotNull('opened_at')->get();
        $signedSigners = $this->signers()->where('status', 'signed')->get();
        $reminderCount = $this->signers()->max('reminder_count') ?? 0;

        // If document is signed by all signers
        if ($signedSigners->count() > 0 && $pendingSigners->count() === 0) {
            return [
                'text' => 'Signed',
                'class' => 'signed'
            ];
        }

        // If document has been sent and signers have opened but not signed
        if ($this->status === 'sent' && $openedSigners->count() > 0) {
            return [
                'text' => 'Opened - Not Signed',
                'class' => 'opened-not-signed'
            ];
        }

        // If document has been sent and reminders have been sent
        if ($this->status === 'sent' && $reminderCount > 0) {
            if ($reminderCount === 1) {
                return [
                    'text' => 'First Reminder Sent',
                    'class' => 'first-reminder'
                ];
            } elseif ($reminderCount === 2) {
                return [
                    'text' => 'Second Reminder Sent',
                    'class' => 'second-reminder'
                ];
            } elseif ($reminderCount >= 3) {
                return [
                    'text' => 'Final Reminder Sent',
                    'class' => 'final-reminder'
                ];
            }
        }

        // If document has been sent but not opened yet
        if ($this->status === 'sent' && $openedSigners->count() === 0) {
            return [
                'text' => 'Sent for Signature',
                'class' => 'sent'
            ];
        }

        // If document has signature fields placed but not sent
        if ($this->status === 'signature_placed') {
            return [
                'text' => 'Ready to Send',
                'class' => 'ready-to-send'
            ];
        }

        // If document is in draft state
        if ($this->status === 'draft' || !$this->status) {
            return [
                'text' => 'Draft',
                'class' => 'draft'
            ];
        }

        // If document is void
        if ($this->status === 'void') {
            return [
                'text' => 'Void',
                'class' => 'void'
            ];
        }

        // If document is archived
        if ($this->status === 'archived') {
            return [
                'text' => 'Archived',
                'class' => 'archived'
            ];
        }

        // Default fallback
        return [
            'text' => ucfirst($this->status ?? 'Draft'),
            'class' => $this->status ?? 'draft'
        ];
    }

    /**
     * Filename for signed PDF downloads (Content-Disposition).
     * Signed artifacts are always PDF; use .pdf even when filetype stores a MIME type (e.g. application/pdf).
     */
    public function getSignedDownloadFilename(): string
    {
        $ext = 'pdf';

        $base = trim((string) ($this->file_name ?? ''));
        if ($base !== '') {
            if (!preg_match('/_signed$/i', $base)) {
                $base .= '_signed';
            }

            return $this->sanitizeSignedDownloadFilename($base . '.' . $ext);
        }

        $mk = (string) ($this->myfile_key ?? '');
        if ($mk !== '' && !preg_match('/^\d+_signed\.pdf$/i', $mk)) {
            $stem = pathinfo($mk, PATHINFO_FILENAME);
            if ($stem !== '') {
                return $this->sanitizeSignedDownloadFilename($stem . '_signed.' . $ext);
            }
        }

        return (string) $this->id . '_signed.pdf';
    }

    /**
     * Human-readable filename with one extension. filetype may be a MIME string or a real extension.
     */
    public function getFilenameWithExtensionForDisplay(): string
    {
        $name = trim((string) ($this->file_name ?? ''));
        if ($name === '') {
            return 'document.' . $this->resolveStoredFileExtension();
        }

        $existing = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($existing !== '' && preg_match('/^[a-z0-9]{1,10}$/', $existing)) {
            return $name;
        }

        return $name . '.' . $this->resolveStoredFileExtension();
    }

    /**
     * Bare extension for UI preview handlers, which only understand real extensions.
     */
    public function getPreviewFileExtension(): string
    {
        return $this->resolveStoredFileExtension();
    }

    /**
     * @internal Prefer getFilenameWithExtensionForDisplay() for UI strings.
     */
    private function resolveStoredFileExtension(): string
    {
        $raw = strtolower(trim((string) ($this->filetype ?? '')));
        if ($raw === '') {
            return 'pdf';
        }

        if (strpos($raw, '/') !== false) {
            try {
                $exts = MimeTypes::getDefault()->getExtensions($raw);
                if (isset($exts[0]) && $exts[0] !== '') {
                    return $exts[0];
                }
            } catch (\Throwable) {
                // fall through
            }

            return 'pdf';
        }

        if (preg_match('/^[a-z0-9]{1,10}$/', $raw)) {
            return $raw;
        }

        return 'pdf';
    }

    private function sanitizeSignedDownloadFilename(string $name): string
    {
        $name = str_replace(['"', "\r", "\n", '\\', '/'], '', $name);
        $name = trim($name);

        return $name !== '' ? mb_substr($name, 0, 220) : ((string) $this->id . '_signed.pdf');
    }
}
