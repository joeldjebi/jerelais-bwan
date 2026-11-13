<?php

namespace App\Domain\AppelRelais\Support;

use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relais concernés par un appel (MOB-23, ADM-14).
 *
 * Commune d'un Relais : celle de son profil ; à défaut (Relais inscrit par l'application, identité pas encore vérifiée),
 * la commune déclarée dans sa vérification d'identité ou celle d'un relais envoyé ces 90 derniers jours.
 * Rayon : communes dont le contour est à moins du rayon du point (ST_DWithin), et Relais ayant envoyé un relais
 * géolocalisé dans ce rayon ces 90 derniers jours. Le type de lieu décrit ce qu'il faut couvrir : il ne filtre pas.
 */
class AppelAudience
{
    /** Ancienneté maximale d'un relais pour situer un Relais (commune ou position). */
    public const int RECENT_DAYS = 90;

    /**
     * @param  list<int>|null  $communeIds  communes choisies (null : celles enregistrées sur l'appel)
     * @param  list<int>|null  $regionIds  régions choisies (null : celles enregistrées sur l'appel)
     * @return list<int>|null identifiants des communes visées (null = tout le pays)
     */
    public static function communeIds(AppelRelais $appel, ?array $communeIds = null, ?array $regionIds = null): ?array
    {
        $ids = match ($appel->target) {
            AppelTarget::National => null,
            AppelTarget::Communes => array_map('intval', $communeIds ?? $appel->communes()->pluck('communes.id')->all()),
            AppelTarget::Regions => array_map('intval', DB::table('communes')->whereIn('region_id', $regionIds ?? $appel->regions()->pluck('regions.id')->all())->pluck('id')->all()),
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
        return self::profilesFor($appel);
    }

    /**
     * Même ciblage, pour une zone pas encore enregistrée (aperçu dans le formulaire de la console).
     *
     * @param  list<int>|null  $communeIds
     * @param  list<int>|null  $regionIds
     * @return Builder<RelaisProfile>
     */
    public static function profilesFor(AppelRelais $appel, ?array $communeIds = null, ?array $regionIds = null): Builder
    {
        $communes = self::communeIds($appel, $communeIds, $regionIds);

        if ($communes === null) {
            return self::active();
        }

        $center = $appel->target === AppelTarget::Radius ? $appel->center : null;
        $radiusM = (int) $appel->radius_m;

        return self::active()->where(function (Builder $zone) use ($communes, $center, $radiusM): void {
            $zone->whereRaw('false');

            if ($communes !== []) {
                $zone->orWhere(fn (Builder $inner) => self::livesIn($inner, $communes));
            }

            if ($center !== null) {
                $zone->orWhereExists(fn (QueryBuilder $sent) => $sent->selectRaw('1')->from('relais_items')
                    ->whereColumn('relais_items.user_id', 'relais_profiles.user_id')
                    ->whereNotNull('relais_items.location')
                    ->where('relais_items.created_at', '>=', now()->subDays(self::RECENT_DAYS))
                    ->whereRaw('ST_DWithin(relais_items.location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [
                        $center->getLongitude(), $center->getLatitude(), $radiusM,
                    ]));
            }
        });
    }

    /**
     * @return Builder<RelaisProfile>
     */
    private static function active(): Builder
    {
        return RelaisProfile::withoutCommuneRestriction()->where('relais_profiles.status', RelaisStatus::Active->value);
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

    /**
     * Relais situé dans l'une des communes : commune du profil, sinon commune de l'identité ou d'un relais récent.
     *
     * @param  Builder<RelaisProfile>  $query
     * @param  list<int>  $communes
     */
    private static function livesIn(Builder $query, array $communes): void
    {
        $query->whereIn('relais_profiles.commune_id', $communes)
            ->orWhere(fn (Builder $unplaced) => $unplaced->whereNull('relais_profiles.commune_id')->where(fn (Builder $fallback) => $fallback
                ->whereExists(fn (QueryBuilder $identity) => $identity->selectRaw('1')->from('identity_verifications')
                    ->whereColumn('identity_verifications.user_id', 'relais_profiles.user_id')
                    ->whereIn('identity_verifications.commune_id', $communes))
                ->orWhereExists(fn (QueryBuilder $sent) => $sent->selectRaw('1')->from('relais_items')
                    ->whereColumn('relais_items.user_id', 'relais_profiles.user_id')
                    ->whereIn('relais_items.commune_id', $communes)
                    ->where('relais_items.created_at', '>=', now()->subDays(self::RECENT_DAYS)))));
    }
}
