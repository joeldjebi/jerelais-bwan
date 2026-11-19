<?php

namespace App\Domain\Notification\Support;

use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Notification\Enums\AnnouncementAudience;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Relais visés par une annonce : Relais actifs seulement, situés comme pour les appels à relais (commune du profil,
 * sinon de l'identité ou d'un relais récent).
 */
final class AnnouncementTargets
{
    /**
     * @param  list<string>  $ids  uuid des régions, des communes ou du compte du Relais, selon le ciblage
     * @return Builder<RelaisProfile>
     */
    public static function profiles(AnnouncementAudience $audience, array $ids = []): Builder
    {
        $query = RelaisProfile::withoutCommuneRestriction()->where('relais_profiles.status', RelaisStatus::Active->value);

        return match ($audience) {
            AnnouncementAudience::All => $query,
            AnnouncementAudience::Communes => $query->where(fn (Builder $zone) => AppelAudience::livesIn($zone, self::communeIds(DB::table('communes')->whereIn('uuid', $ids)))),
            AnnouncementAudience::Regions => $query->where(fn (Builder $zone) => AppelAudience::livesIn($zone, self::communeIds(DB::table('communes')->whereIn('region_id', DB::table('regions')->whereIn('uuid', $ids)->select('id'))))),
            AnnouncementAudience::Relais => $query->whereIn('relais_profiles.user_id', DB::table('users')->whereIn('uuid', $ids)->select('id')),
            AnnouncementAudience::IdentityPending => $query->whereNotExists(fn (QueryBuilder $identity) => $identity->selectRaw('1')->from('identity_verifications')
                ->whereColumn('identity_verifications.user_id', 'relais_profiles.user_id')
                ->where('identity_verifications.status', IdentityStatus::Verified->value)),
            AnnouncementAudience::PayoutMissing => $query->whereNotExists(fn (QueryBuilder $account) => $account->selectRaw('1')->from('payout_accounts')
                ->whereColumn('payout_accounts.user_id', 'relais_profiles.user_id')),
        };
    }

    /**
     * Destinataires et, parmi eux, ceux qu'une notification push peut joindre (aperçu avant l'envoi).
     *
     * @param  list<string>  $ids
     * @return array{total: int, push: int}
     */
    public static function estimate(AnnouncementAudience $audience, array $ids = []): array
    {
        $profiles = self::profiles($audience, $ids);

        return [
            'total' => (clone $profiles)->count(),
            'push' => (clone $profiles)->whereExists(fn (QueryBuilder $device) => $device->selectRaw('1')->from('devices')
                ->whereColumn('devices.user_id', 'relais_profiles.user_id')
                ->whereNull('devices.revoked_at')
                ->whereNotNull('devices.fcm_token')
                ->whereIn('devices.platform', [DevicePlatform::Android->value, DevicePlatform::Ios->value]))->count(),
        ];
    }

    /**
     * @return list<int>
     */
    private static function communeIds(QueryBuilder $communes): array
    {
        return array_values(array_map('intval', $communes->pluck('id')->all()));
    }
}
