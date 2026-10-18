<?php

namespace App\Domain\Territoire\Actions;

use App\Domain\Territoire\Models\Commune;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;

/**
 * Déduit la commune qui contient une position GPS (MOB-13, règle de routage §8.2).
 * Retourne null hors de tout contour connu : l'appelant se rabat alors sur la commune déclarée.
 */
class ResolveCommuneFromPoint
{
    public function handle(Point $point): ?Commune
    {
        return Commune::query()
            ->where('is_virtual', false)
            ->whereNotNull('geom')
            ->where(ST::covers('geom', $point), true)
            ->first();
    }
}
