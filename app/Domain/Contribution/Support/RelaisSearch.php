<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Filtres et recherche de la console (ADM-04) : texte, commune, région, catégorie, Relais,
 * coordonnateur, période, type de média, statut, urgence. Le CommuneScope s'applique en plus.
 */
class RelaisSearch
{
    /**
     * @param  array{q?: string, commune?: string, region?: string, category?: string, relais?: string, coordinator?: string, from?: string, to?: string, media?: string, state?: string, urgency?: string, appel?: string}  $filters
     * @return Builder<RelaisItem>
     */
    public static function query(array $filters): Builder
    {
        $filled = fn (string $key): ?string => isset($filters[$key]) && trim((string) $filters[$key]) !== '' ? trim((string) $filters[$key]) : null;
        // Saisie libre (URL) : un identifiant invalide ne correspond à rien, une date invalide est ignorée — jamais d'erreur SQL.
        $uuid = fn (string $key): ?string => ($value = $filled($key)) === null ? null : (Str::isUuid($value) ? $value : Str::uuid()->toString());
        $date = function (string $key) use ($filled): ?CarbonImmutable {
            $value = $filled($key);

            return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null : null;
        };

        return RelaisItem::query()
            ->where('state', '!=', Uploading::getMorphClass())
            ->when($filled('q'), fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'ilike', '%'.$term.'%')
                ->orWhere('description', 'ilike', '%'.$term.'%')
                ->orWhere('declared_place', 'ilike', '%'.$term.'%')))
            ->when($uuid('commune'), fn (Builder $query, string $uuid) => $query->whereHas('commune', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($uuid('region'), fn (Builder $query, string $uuid) => $query->whereHas('commune.region', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($uuid('category'), fn (Builder $query, string $uuid) => $query->whereHas('category', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($filled('relais'), fn (Builder $query, string $term) => $query->whereHas('relaisProfile', fn (Builder $inner) => $inner
                ->where('number', 'ilike', '%'.$term.'%')->orWhere('display_name', 'ilike', '%'.$term.'%')))
            ->when($uuid('coordinator'), fn (Builder $query, string $uuid) => $query->whereHas('statusHistory.actor', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($date('from'), fn (Builder $query, CarbonImmutable $from) => $query->where('received_at', '>=', $from->startOfDay()))
            ->when($date('to'), fn (Builder $query, CarbonImmutable $to) => $query->where('received_at', '<=', $to->endOfDay()))
            ->when($filled('media'), fn (Builder $query, string $kind) => $query->whereHas('medias', fn (Builder $inner) => $inner->where('kind', $kind)))
            ->when($filled('state'), fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filled('urgency'), fn (Builder $query, string $urgency) => $query->where('urgency', $urgency));
    }
}
