<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Access\Enums\Permission;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Qui peut envoyer un relais : un Relais actif, ou un coordonnateur (Annexe A).
 */
class RelaisSender
{
    public static function profileOf(User $user): ?RelaisProfile
    {
        return RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)->first();
    }

    public static function canSend(User $user): bool
    {
        if (! $user->hasPermissionTo(Permission::RelaisSubmit->value)) {
            return false;
        }

        $profile = self::profileOf($user);

        return $profile === null ? $user->isStaff() : $profile->status->canSubmit();
    }

    /**
     * Revérifié à chaque étape de l'envoi (médias, envoi, complément) : un Relais suspendu ou radié
     * après la création de son brouillon ne peut plus rien transmettre.
     *
     * @throws AuthorizationException
     */
    public static function ensureCanSend(User $user): void
    {
        if (! self::canSend($user)) {
            throw new AuthorizationException(__('relais_items.errors.not_relais'));
        }
    }
}
