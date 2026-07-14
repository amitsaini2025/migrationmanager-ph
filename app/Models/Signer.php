<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Signer extends Authenticatable
{
    use HasFactory;
    protected $fillable = [
        'document_id', 
        'email', 
        'name', 
        'token', 
        'status', 
        'signed_at',
        'opened_at',
        'cancelled_at',
        'last_reminder_sent_at',
        'reminder_count',
        'email_template',
        'email_subject',
        'email_message',
        'from_email'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'opened_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Signer $signer) {
            if ($signer->email !== null && $signer->email !== '') {
                $signer->email = Admin::sanitizeEmailAddress($signer->email);
            }
            if ($signer->from_email !== null && $signer->from_email !== '') {
                $signer->from_email = Admin::sanitizeEmailAddress($signer->from_email);
            }
        });
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getStatusDisplayAttribute()
    {
        if ($this->status === 'signed') {
            return 'Signed';
        } elseif ($this->status === 'cancelled') {
            return 'Cancelled';
        } elseif ($this->opened_at && $this->status === 'pending') {
            return 'Opened - Not Signed';
        } elseif ($this->status === 'pending') {
            return 'Pending';
        }
        return 'Unknown';
    }

    public function getStatusColorAttribute()
    {
        if ($this->status === 'signed') {
            return 'green';
        } elseif ($this->status === 'cancelled') {
            return 'red';
        } elseif ($this->opened_at && $this->status === 'pending') {
            return 'yellow';
        } elseif ($this->status === 'pending') {
            return 'gray';
        }
        return 'gray';
    }
}