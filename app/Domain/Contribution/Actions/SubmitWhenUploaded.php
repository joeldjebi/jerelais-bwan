<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Media\Enums\MediaStatus;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Soumission automatique d'un relais envoyé en un seul appel (MOB-19) : dès que plus aucun de ses fichiers
 * n'est en cours d'envoi (reçu, ou abandonné), le relais part en vérification. Sans effet sur les relais
 * créés autrement, ni sur un relais déjà envoyé.
 */
class SubmitWhenUploaded
{
    public function __construct(private readonly SubmitRelaisItem $submit) {}

    public function handle(RelaisItem $relais): void
    {
        if (! $relais->submit_on_upload || ! $relais->state instanceof Uploading) {
            return;
        }

        if ($relais->medias()->where('status', MediaStatus::Pending->value)->exists()) {
            return;
        }

        try {
            $this->submit->handle($relais);
        } catch (RelaisIncomplete|RelaisNotEditable|AuthorizationException) {
            // Aucun fichier reçu (tous abandonnés ou refusés) ou Relais suspendu entre-temps : le relais reste en cours d'envoi.
        }
    }
}
