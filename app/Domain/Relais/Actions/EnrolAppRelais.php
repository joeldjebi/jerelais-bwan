<?php

namespace App\Domain\Relais\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;

/**
 * Fait d'un compte inscrit depuis l'application un Relais actif (MOB-01) : rôle, numéro JR-… et badge,
 * sans commune. Il peut envoyer des relais tout de suite ; son identité (nom, pièce, commune, selfie…)
 * se complète plus tard dans son profil, et n'est exigée que pour recevoir ses commissions.
 */
class EnrolAppRelais
{
    public function __construct(
        private readonly StartCandidature $startCandidature,
        private readonly CreateRelaisProfile $createProfile,
    ) {}

    /**
     * Fait d'un ancien compte « candidat » (ou sans rôle) un Relais à sa connexion ; sans effet sur l'équipe
     * ou sur un compte qui est déjà Relais.
     */
    public function ensure(User $user): void
    {
        if ($user->isStaff() || RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)->exists()) {
            return;
        }

        $this->handle($user);
    }

    public function handle(User $user): RelaisProfile
    {
        $user->syncRoles([Role::Relais->value]);

        return $this->createProfile->handle($this->startCandidature->handle($user));
    }
}
