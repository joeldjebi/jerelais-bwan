<?php

namespace App\Domain\Territoire\Models;

use App\Models\Concerns\HasPublicUuid;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commune_id', 'localite_id', 'lieu_type_id', 'name', 'location'])]
#[Table('lieux')]
class Lieu extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    /**
     * @return BelongsTo<LieuType, $this>
     */
    public function lieuType(): BelongsTo
    {
        return $this->belongsTo(LieuType::class);
    }
}
