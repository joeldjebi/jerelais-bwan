<?php

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\Role;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;

/**
 * Périmètre territorial d'un utilisateur.
 *  - null  : aucune restriction (administration, rédaction) ;
 *  - liste : identifiants des communes autorisées (coordonnateur : ses communes ;
 *            superviseur : les communes de ses régions et les siennes).
 * Les autres rôles (Relais, candidat) ne voient que leurs propres données, filtrées par les Policies.
 */
class CommuneAccess
{
    /** @var array<int, list<int>|null> */
    private array $cache = [];

    /**
     * @return list<int>|null
     */
    public function communeIdsFor(User $user): ?array
    {
        if (array_key_exists($user->id, $this->cache)) {
            return $this->cache[$user->id];
        }

        if (! $user->hasAnyRole(array_map(fn (Role $role) => $role->value, Role::territorial()))) {
            return $this->cache[$user->id] = null;
        }

        $ids = $user->communes()->pluck('communes.id');

        if ($user->hasRole(Role::Superviseur->value)) {
            $regionIds = $user->regions()->pluck('regions.id');
            $ids = $ids->merge(Commune::query()->whereIn('region_id', $regionIds)->pluck('id'));
        }

        /** @var list<int> $list */
        $list = $ids->map(fn ($id): int => (int) $id)->unique()->values()->all();

        return $this->cache[$user->id] = $list;
    }

    public function isRestricted(User $user): bool
    {
        return $this->communeIdsFor($user) !== null;
    }

    public function canAccessCommune(User $user, Commune|int|null $commune): bool
    {
        $ids = $this->communeIdsFor($user);

        if ($ids === null) {
            return true;
        }

        $communeId = $commune instanceof Commune ? $commune->id : $commune;

        return $communeId !== null && in_array($communeId, $ids, true);
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }
}
