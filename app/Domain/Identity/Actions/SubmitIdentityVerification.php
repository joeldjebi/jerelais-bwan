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
 * Envoi automatique dès que le dossier est complet (dernier champ ou dernier fichier reçu) : l'application
 * n'a pas besoin d'appeler POST /identity/submit, qui reste disponible.
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

        $this->audit->record('identity.submitted', $verification, ['document_type' => $verification->document_type], actor: $verification->user);

        return $verification;
    }

    /**
     * Envoie le dossier s'il est modifiable et complet ; sinon ne fait rien.
     *
     * @return bool vrai si le dossier vient d'être envoyé
     */
    public function submitIfComplete(IdentityVerification $verification): bool
    {
        if (! $verification->status->isEditable() || IdentityChecklist::missing($verification) !== []) {
            return false;
        }

        $this->handle($verification);

        return true;
    }
}
