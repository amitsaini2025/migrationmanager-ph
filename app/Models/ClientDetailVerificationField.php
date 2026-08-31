<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDetailVerificationField extends Model
{
    protected $fillable = [
        'verification_id',
        'client_id',
        'field_key',
        'original_value',
        'requested_value',
        'status',
        'note',
        'accepted_at',
        'accepted_by',
    ];

    protected $attributes = [
        'status' => 'confirmed',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(ClientDetailVerification::class, 'verification_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'accepted_by');
    }

    public function isPendingChange(): bool
    {
        return $this->status === 'change_requested';
    }
}
