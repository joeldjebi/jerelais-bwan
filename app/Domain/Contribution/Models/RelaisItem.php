<?php

namespace App\Domain\Contribution\Models;

use App\Domain\Access\Concerns\BelongsToCommune;
use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\States\RelaisState;
use App\Domain\Media\Models\Media;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * Un relais : contenu envoyé par un Relais, routé vers la commune du lieu de capture (§8.2).
 *
 * @property int $id
 * @property string $uuid
 * @property string $client_uuid
 * @property int $user_id
 * @property int|null $relais_profile_id
 * @property int|null $commune_id
 * @property int|null $declared_commune_id
 * @property string|null $routed_by
 * @property int|null $category_id
 * @property RelaisState $state
 * @property Urgency $urgency
 * @property string|null $title
 * @property string|null $description
 * @property Point|null $location
 * @property int|null $location_accuracy_m
 * @property string|null $declared_place
 * @property CarbonImmutable|null $device_captured_at
 * @property string|null $device_model
 * @property string|null $app_version
 * @property bool|null $has_minors
 * @property PeopleConsent|null $people_consent
 * @property CreditMode|null $credit_mode
 * @property string|null $credit_name
 * @property bool|null $blur_required
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $sla_due_at
 * @property int $escalation_level
 * @property CarbonImmutable|null $escalated_at
 * @property int|null $locked_by
 * @property CarbonImmutable|null $locked_until
 * @property int|null $rejection_reason_id
 * @property string|null $decision_comment
 * @property string|null $internal_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read RelaisProfile|null $relaisProfile
 * @property-read Commune|null $commune
 * @property-read Commune|null $declaredCommune
 * @property-read Category|null $category
 * @property-read RejectionReason|null $rejectionReason
 * @property-read Collection<int, Media> $medias
 */
#[Fillable([
    'category_id', 'declared_commune_id', 'urgency', 'title', 'description',
    'location', 'location_accuracy_m', 'declared_place', 'device_captured_at', 'device_model', 'app_version',
    'has_minors', 'people_consent', 'credit_mode', 'credit_name',
])]
#[Hidden(['internal_note', 'locked_by', 'locked_until'])]
class RelaisItem extends Model
{
    use BelongsToCommune, HasPublicUuid, HasStates;

    protected $attributes = [
        'urgency' => 'normal',
        'escalation_level' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => RelaisState::class,
            'urgency' => Urgency::class,
            'location' => Point::class,
            'location_accuracy_m' => 'integer',
            'device_captured_at' => 'immutable_datetime',
            'has_minors' => 'boolean',
            'people_consent' => PeopleConsent::class,
            'credit_mode' => CreditMode::class,
            'blur_required' => 'boolean',
            'submitted_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'sla_due_at' => 'immutable_datetime',
            'escalation_level' => 'integer',
            'escalated_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
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
     * @return BelongsTo<RelaisProfile, $this>
     */
    public function relaisProfile(): BelongsTo
    {
        return $this->belongsTo(RelaisProfile::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function declaredCommune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'declared_commune_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<RejectionReason, $this>
     */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function medias(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('id');
    }

    /**
     * @return HasMany<RelaisStatusChange, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(RelaisStatusChange::class)->orderBy('id');
    }

    public function isLockedFor(User $user): bool
    {
        return $this->locked_by !== null
            && $this->locked_by !== $user->id
            && $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    public function isOverdue(): bool
    {
        return $this->sla_due_at !== null && $this->sla_due_at->isPast() && $this->state->isAwaitingValidation();
    }
}
