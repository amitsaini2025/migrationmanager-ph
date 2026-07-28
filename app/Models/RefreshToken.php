<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens';

    public const USER_TYPE_ADMIN = 'admin';
    public const USER_TYPE_STAFF = 'staff';

    protected $fillable = [
        'user_id',
        'user_type',
        'token',
        'device_name',
        'expires_at',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /**
     * Get the admin (client portal) user that owns the refresh token.
     * Prefer resolving via user_type + Staff/Admin lookup in controllers.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function scopeForUserType($query, string $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope to get active (non-revoked) refresh tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_revoked', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope to get refresh tokens for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Generate a new refresh token
     */
    public static function generateToken($userId, $deviceName = null, $expiryDays = 30, string $userType = self::USER_TYPE_ADMIN)
    {
        // Generate unique token with retry logic
        $maxAttempts = 5;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $token = Str::random(64);
            $expiresAt = now()->addDays($expiryDays);

            try {
                return self::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'token' => $token,
                    'device_name' => $deviceName,
                    'expires_at' => $expiresAt,
                    'is_revoked' => false,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // If it's a unique constraint violation, try again
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        throw new \Exception('Failed to generate unique refresh token after ' . $maxAttempts . ' attempts');
                    }
                    continue;
                }
                // For other database errors, rethrow
                throw $e;
            }
        }
        
        throw new \Exception('Failed to generate refresh token');
    }

    /**
     * Check if token is valid (not revoked and not expired)
     */
    public function isValid()
    {
        return !$this->is_revoked && $this->expires_at > now();
    }

    /**
     * Revoke the token
     */
    public function revoke()
    {
        $this->update(['is_revoked' => true]);
    }
}
