<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    public const USER_TYPE_ADMIN = 'admin';
    public const USER_TYPE_STAFF = 'staff';

    protected $fillable = [
        'user_id',
        'user_type',
        'device_token',
        'device_name',
        'device_type',
        'app_version',
        'os_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the admin (client portal) user that owns the device token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    /**
     * Scope to get active device tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get device tokens for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForUserType($query, string $userType)
    {
        return $query->where('user_type', $userType);
    }
}
