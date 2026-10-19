<?php

namespace App\Domain\Candidature\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Candidature\Models\Candidature;
use App\Models\User;

/**
 * Accès aux dossiers de candidature : le coordonnateur ne voit que sa ou ses communes (COO-01, §9.4).
 */
class CandidaturePolicy
{
    public function __construct(private readonly CommuneAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CandidaturesValidate->value);
    }

    public function view(User $user, Candidature $candidature): bool
    {
        return $this->viewAny($user) && $this->access->canAccessCommune($user, $candidature->commune_id);
    }

    public function decide(User $user, Candidature $candidature): bool
    {
        return $this->view($user, $candidature) && $candidature->status->isPending();
    }

    /**
     * Pièces d'identité et selfie : coordonnateur concerné et administrateurs habilités uniquement.
     */
    public function viewIdentity(User $user, Candidature $candidature): bool
    {
        return $user->hasPermissionTo(Permission::IdentityView->value)
            && $this->access->canAccessCommune($user, $candidature->commune_id);
    }
}
