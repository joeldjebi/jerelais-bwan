<?php

namespace App\Domain\Territoire\Models;

use App\Domain\Territoire\Enums\LocaliteType;
use App\Models\Concerns\HasPublicUuid;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quartier, village ou sous-préfecture d'une commune.
 *
 * @property int $id
 * @property string $uuid
 * @property int $commune_id
 * @property string $name
 * @property LocaliteType $type
 * @property Point|null $location
 */
#[Fillable(['commune_id', 'name', 'type', 'location'])]
class Localite extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocaliteType::class,
            'location' => Point::class,
        ];
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
