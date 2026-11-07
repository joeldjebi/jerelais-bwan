<?php

namespace App\Domain\Territoire\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Database\Factories\CommuneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $region_id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property bool $is_virtual
 * @property bool $is_active
 */
#[Fillable(['region_id', 'name', 'slug', 'code', 'country_code', 'is_virtual', 'is_active', 'geom'])]
#[Hidden(['geom'])]
#[UseFactory(CommuneFactory::class)]
class Commune extends Model
{
    /** @use HasFactory<CommuneFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_virtual' => 'boolean',
            'is_active' => 'boolean',
            'geom' => MultiPolygon::class,
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasMany<Localite, $this>
     */
    public function localites(): HasMany
    {
        return $this->hasMany(Localite::class);
    }

    /**
     * Coordonnateurs rattachés (titulaires et suppléants).
     *
     * @return BelongsToMany<User, $this>
     */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assignment')->withTimestamps();
    }

    /**
     * @param  Builder<Commune>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
