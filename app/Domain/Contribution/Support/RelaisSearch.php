<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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

        return RelaisItem::query()
            ->where('state', '!=', Uploading::getMorphClass())
            ->when($filled('q'), fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'ilike', '%'.$term.'%')
                ->orWhere('description', 'ilike', '%'.$term.'%')
                ->orWhere('declared_place', 'ilike', '%'.$term.'%')))
            ->when($filled('commune'), fn (Builder $query, string $uuid) => $query->whereHas('commune', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($filled('region'), fn (Builder $query, string $uuid) => $query->whereHas('commune.region', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($filled('category'), fn (Builder $query, string $uuid) => $query->whereHas('category', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($filled('relais'), fn (Builder $query, string $term) => $query->whereHas('relaisProfile', fn (Builder $inner) => $inner
                ->where('number', 'ilike', '%'.$term.'%')->orWhere('display_name', 'ilike', '%'.$term.'%')))
            ->when($filled('coordinator'), fn (Builder $query, string $uuid) => $query->whereHas('statusHistory.actor', fn (Builder $inner) => $inner->where('uuid', $uuid)))
            ->when($filled('from'), fn (Builder $query, string $date) => $query->where('received_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filled('to'), fn (Builder $query, string $date) => $query->where('received_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->when($filled('media'), fn (Builder $query, string $kind) => $query->whereHas('medias', fn (Builder $inner) => $inner->where('kind', $kind)))
            ->when($filled('state'), fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filled('urgency'), fn (Builder $query, string $urgency) => $query->where('urgency', $urgency));
    }
}
