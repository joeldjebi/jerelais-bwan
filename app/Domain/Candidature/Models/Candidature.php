<?php

namespace App\Domain\Candidature\Models;

use App\Domain\Access\Concerns\BelongsToCommune;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CandidatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier de candidature d'un futur Relais (MOB-02 à MOB-06).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $commune_id
 * @property int|null $localite_id
 * @property int|null $lieu_type_id
 * @property CandidatureStatus $status
 * @property string|null $last_name
 * @property string|null $first_names
 * @property CarbonImmutable|null $birth_date
 * @property Gender|null $gender
 * @property string|null $email
 * @property bool $is_minor
 * @property Occupation|null $occupation
 * @property list<string>|null $languages
 * @property list<string>|null $interests
 * @property string|null $motivation
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property int|null $decided_by
 * @property int|null $rejection_reason_id
 * @property string|null $decision_comment
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Commune|null $commune
 * @property-read Localite|null $localite
 * @property-read LieuType|null $lieuType
 * @property-read User $user
 * @property-read Collection<int, CandidatureDocument> $documents
 */
#[Fillable([
    'commune_id', 'localite_id', 'lieu_type_id', 'status',
    'last_name', 'first_names', 'birth_date', 'gender', 'email', 'is_minor',
    'occupation', 'languages', 'interests', 'motivation',
])]
#[UseFactory(CandidatureFactory::class)]
class Candidature extends Model
{
    /** @use HasFactory<CandidatureFactory> */
    use BelongsToCommune, HasFactory, HasPublicUuid;

    protected $attributes = [
        'status' => 'draft',
        'is_minor' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidatureStatus::class,
            'birth_date' => 'immutable_date',
            'gender' => Gender::class,
            'occupation' => Occupation::class,
            'is_minor' => 'boolean',
            'languages' => 'array',
            'interests' => 'array',
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
     * @return BelongsTo<Localite, $this>
     */
    public function localite(): BelongsTo
    {
        return $this->belongsTo(Localite::class);
    }

    /**
     * @return BelongsTo<LieuType, $this>
     */
    public function lieuType(): BelongsTo
    {
        return $this->belongsTo(LieuType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<RejectionReason, $this>
     */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    /**
     * @return HasMany<CandidatureDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CandidatureDocument::class);
    }

    public function fullName(): string
    {
        return trim(($this->first_names ?? '').' '.($this->last_name ?? ''));
    }

    public function isStepComplete(CandidatureStep $step): bool
    {
        foreach ($step->requiredColumns() as $column) {
            $value = $this->getAttribute($column);

            if ($value === null || $value === '' || $value === []) {
                return false;
            }
        }

        return true;
    }
}
