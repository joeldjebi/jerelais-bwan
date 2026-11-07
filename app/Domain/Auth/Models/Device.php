<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\DevicePlatform;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $name
 * @property DevicePlatform $platform
 * @property string|null $app_version
 * @property string|null $fcm_token
 * @property string|null $fcm_token_hash
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['user_id', 'personal_access_token_id', 'name', 'platform', 'app_version', 'fcm_token', 'last_seen_at', 'revoked_at'])]
#[Hidden(['fcm_token', 'fcm_token_hash', 'personal_access_token_id'])]
class Device extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::saving(function (Device $device): void {
            if ($device->isDirty('fcm_token')) {
                $device->fcm_token_hash = self::hashToken($device->fcm_token);
            }
        });
    }

    /**
     * Empreinte HMAC du jeton push : comparaison possible sans déchiffrer (COO-03).
     */
    public static function hashToken(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        return hash_hmac('sha256', $token, (string) config('jerelais.phone.hash_key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'fcm_token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
