<?php

namespace App\Domain\Formation\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Déplace un élément d'une liste ordonnée (colonne `position`) d'un cran vers le haut ou le bas.
 */
final class Reorder
{
    /**
     * Renumérote la liste 1, 2, 3… puis échange l'élément avec son voisin. Faux s'il est déjà en bout de liste.
     *
     * @param  array<int, Model>  $ordered  liste triée par position
     */
    public static function swap(array $ordered, int $id, int $direction): bool
    {
        $ordered = array_values($ordered);
        $index = array_search($id, array_map(fn (Model $item): mixed => $item->getKey(), $ordered), true);
        $neighbor = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($neighbor === null || ! isset($ordered[$neighbor])) {
            return false;
        }

        [$ordered[$index], $ordered[$neighbor]] = [$ordered[$neighbor], $ordered[$index]];

        foreach ($ordered as $position => $item) {
            $item->setAttribute('position', $position + 1);

            if ($item->isDirty('position')) {
                $item->save();
            }
        }

        return true;
    }
}
