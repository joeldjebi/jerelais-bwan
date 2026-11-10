<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Exceptions\IdentityIncomplete;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Identity\Support\IdentityChecklist;

/**
 * Envoie la vérification d'identité à l'administration (MOB-01). Idempotent une fois envoyée.
 */
class SubmitIdentityVerification
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws IdentityIncomplete éléments manquants
     * @throws IdentityLocked déjà vérifiée
     */
    public function handle(IdentityVerification $verification): IdentityVerification
    {
        if ($verification->status === IdentityStatus::Submitted) {
            return $verification;
        }

        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        $missing = IdentityChecklist::missing($verification);

        if ($missing !== []) {
            throw new IdentityIncomplete($missing);
        }

        $verification->forceFill([
            'status' => IdentityStatus::Submitted,
            'submitted_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'decision_comment' => null,
        ])->save();

        $this->audit->record('identity.submitted', $verification, ['document_type' => $verification->document_type?->value], actor: $verification->user);

        return $verification;
    }
}
