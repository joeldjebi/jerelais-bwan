<?php

namespace App\Domain\Relais\Models;

use App\Domain\Access\Concerns\BelongsToCommune;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Models\Device;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Territoire\Models\Localite;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\RelaisProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil du Relais recruté. Son badge porte un QR code qui renvoie vers une page publique de vérification.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $candidature_id
 * @property int $commune_id
 * @property int|null $localite_id
 * @property string|null $number
 * @property string $display_name
 * @property RelaisStatus $status
 * @property string $badge_token
 * @property string|null $reliability
 * @property CarbonImmutable $activated_at
 * @property CarbonImmutable|null $suspended_at
 * @property-read User $user
 * @property-read Candidature $candidature
 * @property-read Localite|null $localite
 */
#[Fillable(['commune_id', 'localite_id', 'display_name', 'status', 'activated_at', 'suspended_at'])]
#[Hidden(['badge_token', 'reliability'])]
#[UseFactory(RelaisProfileFactory::class)]
class RelaisProfile extends Model
{
    /** @use HasFactory<RelaisProfileFactory> */
    use BelongsToCommune, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RelaisStatus::class,
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Candidature, $this>
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    /**
     * @return BelongsTo<Localite, $this>
     */
    public function localite(): BelongsTo
    {
        return $this->belongsTo(Localite::class);
    }

    /**
     * Appareils connectés du compte qui ont enregistré un jeton de notifications push (MOB-21).
     *
     * @return HasMany<Device, $this>
     */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'user_id', 'user_id')
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->whereIn('platform', [DevicePlatform::Android->value, DevicePlatform::Ios->value]);
    }

    /**
     * Relais envoyés par ce Relais.
     *
     * @return HasMany<RelaisItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RelaisItem::class)->withoutGlobalScopes();
    }

    /**
     * Adresse publique encodée dans le QR code du badge.
     */
    public function verificationUrl(): string
    {
        return route('badge.verify', $this->badge_token);
    }
}
