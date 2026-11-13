<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vérification d'identité d'un Relais inscrit depuis l'application (MOB-01) : un dossier par compte,
 * vérifié par l'administration. Une identité vérifiée rend le Relais éligible aux gains.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $commune_id
 * @property int|null $localite_id
 * @property string|null $document_type valeur (slug) de la pièce choisie
 * @property IdentityStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property int|null $decided_by
 * @property string|null $decision_comment
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 * @property-read IdentityDocumentType|null $documentType
 * @property-read Commune|null $commune
 * @property-read Localite|null $localite
 * @property-read Collection<int, IdentityFile> $files
 */
#[Fillable(['commune_id', 'localite_id', 'document_type', 'status', 'submitted_at', 'decided_at', 'decided_by', 'decision_comment'])]
class IdentityVerification extends Model
{
    use HasPublicUuid;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdentityStatus::class,
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
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
     * Pièce choisie (même désactivée depuis : le dossier garde son type).
     *
     * @return BelongsTo<IdentityDocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(IdentityDocumentType::class, 'document_type', 'slug');
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return BelongsTo<Localite, $this>
     */
    public function localite(): BelongsTo
    {
        return $this->belongsTo(Localite::class);
    }

    /**
     * @return HasMany<IdentityFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(IdentityFile::class);
    }

    public function isVerified(): bool
    {
        return $this->status === IdentityStatus::Verified;
    }
}
