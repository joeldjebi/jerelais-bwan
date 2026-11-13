<?php

namespace App\Domain\Formation\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Support\Reorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fiches de formation gérées depuis la console (MOB-05) : création, modification, ordre, publication, suppression.
 * Chaque changement est audité.
 */
class ManageFormationModule
{
    /** Pictogrammes proposés pour une fiche (application et site) : nom technique => libellé. */
    public const array ICONS = [
        'book' => 'Livre',
        'video' => 'Caméra vidéo',
        'camera' => 'Appareil photo',
        'shield' => 'Bouclier (sécurité)',
        'users' => 'Personnes',
        'search' => 'Loupe (vérification)',
        'map-pin' => 'Lieu',
        'heart' => 'Cœur',
        'flag' => 'Drapeau',
        'sparkles' => 'Étoiles',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title: string, summary: string, body: string, icon: string, video_url: string|null}  $data
     */
    public function create(array $data, User $actor): FormationModule
    {
        return DB::transaction(function () use ($data, $actor): FormationModule {
            $module = FormationModule::query()->create([
                ...$data,
                'slug' => $this->uniqueSlug($data['title']),
                'position' => (int) FormationModule::query()->max('position') + 1,
                'is_active' => true,
            ]);

            $this->audit->record('formation.module.created', $module, ['title' => $module->title], $actor);

            return $module;
        });
    }

    /**
     * @param  array{title: string, summary: string, body: string, icon: string, video_url: string|null}  $data
     */
    public function update(FormationModule $module, array $data, User $actor): void
    {
        DB::transaction(function () use ($module, $data, $actor): void {
            $module->fill($data);
            $changed = array_keys($module->getDirty());

            if ($changed === []) {
                return;
            }

            $module->save();
            $this->audit->record('formation.module.updated', $module, ['title' => $module->title, 'fields' => $changed], $actor);
        });
    }

    public function setActive(FormationModule $module, bool $active, User $actor): void
    {
        if ($module->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($module, $active, $actor): void {
            $module->forceFill(['is_active' => $active])->save();
            $this->audit->record('formation.module.'.($active ? 'published' : 'unpublished'), $module, ['title' => $module->title], $actor);
        });
    }

    /**
     * Échange la fiche avec sa voisine (vers le haut : -1, vers le bas : +1).
     */
    public function move(FormationModule $module, int $direction, User $actor): void
    {
        DB::transaction(function () use ($module, $direction, $actor): void {
            $ordered = FormationModule::query()->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values()->all();

            if (! Reorder::swap($ordered, $module->id, $direction)) {
                return;
            }

            $this->audit->record('formation.module.moved', $module, ['title' => $module->title], $actor);
        });
    }

    public function delete(FormationModule $module, User $actor): void
    {
        DB::transaction(function () use ($module, $actor): void {
            $this->audit->record('formation.module.deleted', $module, ['title' => $module->title], $actor);
            $module->delete();
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 60, '') ?: 'fiche';
        $slug = $base;
        $suffix = 2;

        while (FormationModule::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
