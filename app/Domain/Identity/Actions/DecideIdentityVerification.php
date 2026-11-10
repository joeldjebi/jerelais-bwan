<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décision de l'administration sur une vérification d'identité (MOB-01).
 * Vérifiée : le Relais devient éligible aux gains et son profil est rattaché à la commune déclarée.
 * Refusée : le Relais corrige ce qui est indiqué dans le commentaire, puis renvoie.
 */
class DecideIdentityVerification
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function verify(IdentityVerification $verification, User $decider): void
    {
        $this->ensureSubmitted($verification);

        DB::transaction(function () use ($verification, $decider): void {
            $verification->forceFill([
                'status' => IdentityStatus::Verified,
                'decided_at' => now(),
                'decided_by' => $decider->id,
                'decision_comment' => null,
            ])->save();

            RelaisProfile::withoutCommuneRestriction()->where('user_id', $verification->user_id)->update([
                'commune_id' => $verification->commune_id,
                'localite_id' => $verification->localite_id,
            ]);

            $this->audit->record('identity.verified', $verification, actor: $decider, communeId: $verification->commune_id);
        });
    }

    public function reject(IdentityVerification $verification, User $decider, string $comment): void
    {
        $this->ensureSubmitted($verification);

        $comment = trim($comment);

        if ($comment === '') {
            throw new InvalidArgumentException(__('identity.errors.comment_required'));
        }

        $verification->forceFill([
            'status' => IdentityStatus::Rejected,
            'decided_at' => now(),
            'decided_by' => $decider->id,
            'decision_comment' => $comment,
        ])->save();

        $this->audit->record('identity.rejected', $verification, ['comment' => $comment], actor: $decider, communeId: $verification->commune_id);
    }

    private function ensureSubmitted(IdentityVerification $verification): void
    {
        if ($verification->status !== IdentityStatus::Submitted) {
            throw new InvalidArgumentException(__('Cette vérification a déjà été traitée.'));
        }
    }
}
