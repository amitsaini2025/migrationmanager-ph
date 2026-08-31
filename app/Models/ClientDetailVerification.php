<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClientDetailVerification extends Model
{
    protected $fillable = [
        'client_id',
        'token_hash',
        'sent_to_email',
        'sent_by',
        'snapshot',
        'used_at',
        'invalidated_at',
        'submitted_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'used_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sent_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ClientDetailVerificationField::class, 'verification_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->invalidated_at === null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable($query)
    {
        return $query->whereNull('used_at')->whereNull('invalidated_at');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
