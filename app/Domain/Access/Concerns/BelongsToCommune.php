<?php

namespace App\Domain\Access\Concerns;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Territoire\Models\Commune;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À utiliser sur tout modèle portant une colonne `commune_id` (relais, candidatures, Relais…).
 * Ajoute le CommuneScope : un coordonnateur ne peut pas lire les données d'une autre commune.
 */
trait BelongsToCommune
{
    public static function bootBelongsToCommune(): void
    {
        static::addGlobalScope(new CommuneScope);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Lecture hors périmètre, réservée aux traitements système (escalade, purge, statistiques nationales).
     *
     * @return Builder<static>
     */
    public static function withoutCommuneRestriction(): Builder
    {
        return static::query()->withoutGlobalScope(CommuneScope::class);
    }
}
