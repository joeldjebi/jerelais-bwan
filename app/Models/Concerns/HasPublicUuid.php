<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Clé primaire numérique interne + colonne `uuid` exposée dans les URL et l'API
 * (jamais d'identifiant séquentiel visible : protection contre l'IDOR).
 */
trait HasPublicUuid
{
    use HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
