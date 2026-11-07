<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Enums\ReferenceList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajout, renommage et désactivation d'une entrée de référentiel (ADM-20, ADM-23, COO-15). Chaque changement est audité.
 */
class ManageReferenceList
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  colonnes propres à la liste (commune_id et type, applies_to, examples)
     */
    public function create(ReferenceList $list, string $label, array $attributes, User $actor): Model
    {
        return DB::transaction(function () use ($list, $label, $attributes, $actor): Model {
            $modelClass = $list->model();
            $values = [$list->labelColumn() => $label, ...$attributes];

            if ($list !== ReferenceList::Localites) {
                $values['slug'] = $this->uniqueSlug($list, $label, $attributes);
            }

            if ($list->canBeDisabled()) {
                $values['position'] = (int) $modelClass::query()
                    ->when($list === ReferenceList::RejectionReasons, fn ($query) => $query->where('applies_to', $attributes['applies_to']))
                    ->max('position') + 1;
            }

            $entry = $modelClass::query()->create($values);

            $this->audit->record("referentiel.{$list->value}.created", $entry, ['label' => $label], $actor, $attributes['commune_id'] ?? null);

            return $entry;
        });
    }

    public function rename(ReferenceList $list, Model $entry, string $label, User $actor): void
    {
        $column = $list->labelColumn();
        $previous = (string) $entry->getAttribute($column);

        if ($previous === $label) {
            return;
        }

        DB::transaction(function () use ($list, $entry, $column, $previous, $label, $actor): void {
            $entry->forceFill([$column => $label])->save();

            $this->audit->record("referentiel.{$list->value}.renamed", $entry, ['from' => $previous, 'to' => $label], $actor, $list === ReferenceList::Localites ? $entry->getAttribute('commune_id') : null);
        });
    }

    public function setActive(ReferenceList $list, Model $entry, bool $active, User $actor): void
    {
        if (! $list->canBeDisabled() || (bool) $entry->getAttribute('is_active') === $active) {
            return;
        }

        DB::transaction(function () use ($list, $entry, $active, $actor): void {
            $entry->forceFill(['is_active' => $active])->save();

            $this->audit->record("referentiel.{$list->value}.".($active ? 'enabled' : 'disabled'), $entry, ['label' => $entry->getAttribute($list->labelColumn())], $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function uniqueSlug(ReferenceList $list, string $label, array $attributes): string
    {
        $base = Str::slug($label) ?: 'entree';
        $slug = $base;
        $suffix = 2;

        while ($list->model()::query()
            ->where('slug', $slug)
            ->when($list === ReferenceList::RejectionReasons, fn ($query) => $query->where('applies_to', $attributes['applies_to']))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
