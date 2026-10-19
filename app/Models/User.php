<?php

namespace App\Models;

use App\Domain\Access\Enums\Permission;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\Device;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Formation\Models\QuizAttempt;
use App\Domain\Legal\Models\Consent;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_hash
 * @property string|null $password
 * @property UserStatus $status
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'phone_hash', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'phone', 'phone_hash', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'encrypted',
            'status' => UserStatus::class,
        ];
    }

    public static function findByPhone(PhoneNumber $phone): ?self
    {
        return static::query()->where('phone_hash', $phone->hash())->first();
    }

    public function setPhoneNumber(PhoneNumber $phone): static
    {
        $this->phone = $phone->e164;
        $this->phone_hash = $phone->hash();

        return $this;
    }

    public function phoneNumber(): ?PhoneNumber
    {
        return PhoneNumber::tryParse($this->phone);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Dossier de candidature (candidat ou Relais recruté).
     *
     * @return HasOne<Candidature, $this>
     */
    public function candidature(): HasOne
    {
        return $this->hasOne(Candidature::class);
    }

    /**
     * Acceptations de la charte et des conditions (MOB-04).
     *
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Tentatives au quiz de formation (MOB-05).
     *
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Communes coordonnées (titulaire ou suppléant).
     *
     * @return BelongsToMany<Commune, $this>
     */
    public function communes(): BelongsToMany
    {
        return $this->belongsToMany(Commune::class)->withPivot('assignment')->withTimestamps();
    }

    /**
     * Régions supervisées (superviseur régional).
     *
     * @return BelongsToMany<Region, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class)->withTimestamps();
    }

    /**
     * Membre de l'équipe (console ou back-office) : double authentification obligatoire.
     */
    public function isStaff(): bool
    {
        return $this->hasPermissionTo(Permission::ConsoleAccess->value);
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return ($this->attributes['two_factor_secret'] ?? null) !== null
            && ($this->attributes['two_factor_confirmed_at'] ?? null) !== null;
    }

    public function hasPendingTwoFactorConfirmation(): bool
    {
        return ($this->attributes['two_factor_secret'] ?? null) !== null
            && ($this->attributes['two_factor_confirmed_at'] ?? null) === null;
    }
}
