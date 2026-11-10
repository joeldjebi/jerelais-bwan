<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Domain\Relais\Actions\CreateRelaisProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décision sur une inscription faite depuis l'application (MOB-01) : le compte « en attente » devient Relais
 * (connexion et envoi de relais possibles, numéro et badge attribués, sans commune tant que l'identité n'est pas
 * vérifiée) ou est refusé (données effacées, numéro de nouveau libre).
 */
class ReviewRegistration
{
    public function __construct(
        private readonly PseudonymizeUser $pseudonymize,
        private readonly StartCandidature $startCandidature,
        private readonly CreateRelaisProfile $createProfile,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(User $user, User $actor): void
    {
        $this->ensurePending($user);

        DB::transaction(function () use ($user, $actor): void {
            $user->forceFill(['status' => UserStatus::Active])->save();

            // Relais dès la validation : il peut envoyer des relais ; sa commune sera fixée à la vérification d'identité.
            $user->syncRoles([Role::Relais->value]);
            $this->createProfile->handle($this->startCandidature->handle($user));

            $this->audit->record('users.registration.approved', $user, actor: $actor);
        });
    }

    public function reject(User $user, User $actor, ?string $reason = null): void
    {
        $this->ensurePending($user);

        $this->audit->record('users.registration.rejected', $user, array_filter(['reason' => $reason]), $actor);
        $this->pseudonymize->handle($user, $actor, __('Inscription refusée'));
    }

    private function ensurePending(User $user): void
    {
        if ($user->status !== UserStatus::Pending) {
            throw new InvalidArgumentException('Cette inscription a déjà été traitée.');
        }
    }
}
