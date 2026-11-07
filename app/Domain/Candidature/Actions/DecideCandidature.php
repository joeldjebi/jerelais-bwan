<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Events\CandidatureDecided;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Relais\Actions\CreateRelaisProfile;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décision du coordonnateur (COO-02) : accepter, refuser (motif de la liste obligatoire),
 * mettre en attente, demander un complément (commentaire obligatoire). Chaque décision est auditée et notifiée.
 */
class DecideCandidature
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CreateRelaisProfile $createProfile,
    ) {}

    public function handle(
        Candidature $candidature,
        CandidatureDecision $decision,
        User $decider,
        ?RejectionReason $reason = null,
        ?string $comment = null,
    ): Candidature {
        if (! $candidature->status->isPending()) {
            throw CandidatureLocked::make();
        }

        $comment = $comment === null ? null : trim($comment);

        if ($decision === CandidatureDecision::Reject && ($reason === null || $reason->applies_to !== RejectionTarget::Candidature || ! $reason->is_active)) {
            throw new InvalidArgumentException(__('candidature.decision.reason_required'));
        }

        if ($decision === CandidatureDecision::RequestComplement && ($comment === null || $comment === '')) {
            throw new InvalidArgumentException(__('candidature.decision.comment_required'));
        }

        DB::transaction(function () use ($candidature, $decision, $decider, $reason, $comment): void {
            $candidature->forceFill([
                'status' => $decision->resultingStatus(),
                'decided_at' => now(),
                'decided_by' => $decider->id,
                'rejection_reason_id' => $decision === CandidatureDecision::Reject ? $reason->id : null,
                'decision_comment' => $comment === '' ? null : $comment,
            ])->save();

            if ($decision === CandidatureDecision::Accept) {
                $candidature->user->syncRoles([Role::Relais->value]);
                $this->createProfile->handle($candidature);
            }

            $this->audit->record($decision->auditAction(), $candidature, array_filter([
                'reason' => $reason?->label,
                'comment' => $comment,
            ]), actor: $decider, communeId: $candidature->commune_id);
        });

        CandidatureDecided::dispatch($candidature, $decision);

        return $candidature;
    }
}
