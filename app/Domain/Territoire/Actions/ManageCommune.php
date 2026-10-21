<?php

namespace App\Domain\Territoire\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Création et renommage d'une commune depuis la console (ADM-20). Une nouvelle commune est fermée aux Relais
 * tant que l'administration ne l'ouvre pas ; son contour s'importe ensuite avec le fichier officiel.
 */
class ManageCommune
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(string $name, ?Region $region, ?string $code, User $actor): Commune
    {
        return DB::transaction(function () use ($name, $region, $code, $actor): Commune {
            $commune = Commune::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'region_id' => $region?->id,
                'code' => $code,
                'is_active' => false,
            ]);

            $this->audit->record('territoire.commune.created', $commune, ['name' => $name, 'region' => $region?->name, 'code' => $code], $actor, $commune->id);

            return $commune;
        });
    }

    public function update(Commune $commune, string $name, ?Region $region, User $actor): void
    {
        $before = ['name' => $commune->name, 'region_id' => $commune->region_id];

        if ($before === ['name' => $name, 'region_id' => $region?->id]) {
            return;
        }

        DB::transaction(function () use ($commune, $name, $region, $actor, $before): void {
            $commune->forceFill(['name' => $name, 'region_id' => $region?->id])->save();

            $this->audit->record('territoire.commune.updated', $commune, [
                'from' => $before,
                'to' => ['name' => $name, 'region_id' => $region?->id],
            ], $actor, $commune->id);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'commune';
        $slug = $base;
        $suffix = 2;

        while (Commune::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
