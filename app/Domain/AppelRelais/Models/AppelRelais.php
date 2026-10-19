<?php

namespace App\Domain\AppelRelais\Models;

use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appel à relais lancé par la rédaction ou par un coordonnateur (appel local).
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $instructions
 * @property AppelTarget $target
 * @property Point|null $center
 * @property int|null $radius_m
 * @property int|null $lieu_type_id
 * @property AppelStatus $status
 * @property bool $is_local
 * @property CarbonImmutable $deadline_at
 * @property int $recipients_count
 * @property int $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, RelaisItem> $responses
 * @property-read LieuType|null $lieuType
 * @property-read User $creator
 */
#[Table('appels_relais')]
#[Fillable(['title', 'instructions', 'target', 'center', 'radius_m', 'lieu_type_id', 'status', 'is_local', 'deadline_at'])]
class AppelRelais extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => AppelTarget::class,
            'status' => AppelStatus::class,
            'center' => Point::class,
            'radius_m' => 'integer',
            'is_local' => 'boolean',
            'deadline_at' => 'immutable_datetime',
            'recipients_count' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsToMany<Commune, $this>
     */
    public function communes(): BelongsToMany
    {
        return $this->belongsToMany(Commune::class, 'appel_commune', 'appel_relais_id', 'commune_id');
    }

    /**
     * @return BelongsToMany<Region, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'appel_region', 'appel_relais_id', 'region_id');
    }

    /**
     * Envois rattachés à l'appel.
     *
     * @return HasMany<RelaisItem, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(RelaisItem::class, 'appel_relais_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === AppelStatus::Open && $this->deadline_at->isFuture();
    }
}
