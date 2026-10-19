<?php

namespace App\Domain\AppelRelais\Support;

use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relais concernés par un appel : commune ciblée, région ciblée, ou commune dont le contour est à moins
 * du rayon du point choisi (ST_DWithin en géographie) ; filtre éventuel sur le lieu type déclaré à la candidature.
 */
class AppelAudience
{
    /**
     * @return list<int>|null identifiants des communes visées (null = tout le pays)
     */
    public static function communeIds(AppelRelais $appel): ?array
    {
        $ids = match ($appel->target) {
            AppelTarget::National => null,
            AppelTarget::Communes => array_map('intval', $appel->communes()->pluck('communes.id')->all()),
            AppelTarget::Regions => array_map('intval', DB::table('communes')->whereIn('region_id', $appel->regions()->pluck('regions.id'))->pluck('id')->all()),
            AppelTarget::Radius => $appel->center === null ? [] : array_map('intval', DB::table('communes')
                ->whereNotNull('geom')
                ->whereRaw('ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [
                    $appel->center->getLongitude(), $appel->center->getLatitude(), (int) $appel->radius_m,
                ])
                ->pluck('id')->all()),
        };

        return $ids === null ? null : array_values($ids);
    }

    /**
     * @return Builder<RelaisProfile>
     */
    public static function profiles(AppelRelais $appel): Builder
    {
        $communes = self::communeIds($appel);

        return RelaisProfile::withoutCommuneRestriction()
            ->where('status', RelaisStatus::Active->value)
            ->when($communes !== null, fn (Builder $query) => $query->whereIn('commune_id', $communes))
            ->when($appel->lieu_type_id !== null, fn (Builder $query) => $query->whereHas('candidature', fn (Builder $inner) => $inner
                ->withoutGlobalScopes()->where('lieu_type_id', $appel->lieu_type_id)));
    }

    public static function targets(AppelRelais $appel, RelaisProfile $profile): bool
    {
        return self::profiles($appel)->whereKey($profile->id)->exists();
    }

    /**
     * Appels ouverts qui concernent ce Relais.
     *
     * @return Collection<int, AppelRelais>
     */
    public static function openFor(RelaisProfile $profile): Collection
    {
        return AppelRelais::query()
            ->with(['communes:id,name', 'regions:id,name', 'lieuType:id,name'])
            ->where('status', AppelStatus::Open->value)
            ->where('deadline_at', '>', now())
            ->latest('published_at')
            ->get()
            ->filter(fn (AppelRelais $appel): bool => self::targets($appel, $profile))
            ->values();
    }
}
