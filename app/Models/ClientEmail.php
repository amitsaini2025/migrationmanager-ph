<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEmail extends Model
{
    protected $table = 'client_emails';

    protected $fillable = [
        'admin_id',
		'client_id',
        'email_type',
        'email',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_token',
        'token_expires_at',
        'verification_sent_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'verification_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ClientEmail $model) {
            if ($model->email !== null && $model->email !== '') {
                $model->email = Admin::sanitizeEmailAddress($model->email);
            }
        });
    }

    // Relationships
    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    public function verifications()
    {
        return $this->hasMany(EmailVerification::class, 'client_email_id');
    }

    // Helper methods
    public function needsVerification()
    {
        return !$this->is_verified;
    }

    public function isTokenExpired()
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}


