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

    public function handle(User $user): RelaisProfile
    {
        $user->syncRoles([Role::Relais->value]);

        return $this->createProfile->handle($this->startCandidature->handle($user));
    }
}
