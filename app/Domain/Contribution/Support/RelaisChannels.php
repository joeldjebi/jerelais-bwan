<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Models\User;

/**
 * Autorisation des canaux temps réel des relais (§8.2), utilisée par routes/channels.php.
 */
class RelaisChannels
{
    /**
     * Canal national : équipe sans restriction territoriale (administration, rédaction).
     */
    public static function console(User $user): bool
    {
        return $user->hasPermissionTo(Permission::RelaisViewPending->value)
            && ! app(CommuneAccess::class)->isRestricted($user);
    }

    /**
     * Canal d'une commune : ses coordonnateurs et superviseurs, et l'équipe nationale.
     */
    public static function commune(User $user, int $communeId): bool
    {
        return $user->hasPermissionTo(Permission::RelaisViewPending->value)
            && app(CommuneAccess::class)->canAccessCommune($user, $communeId);
    }
}
