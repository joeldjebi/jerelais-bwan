<?php

namespace App\Domain\Legal\Jobs;

use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nouvelle version d'un texte juridique (ADM-23) : chaque Relais actif est prévenu par notification push
 * (pas de SMS) pour le lire et l'accepter dans l'application.
 */
class NotifyLegalUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $documentId) {}

    public function handle(NotifyUser $notify): void
    {
        $document = LegalDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $push = new PushMessage(
            __('notifications.legal.title'),
            __('notifications.legal.body', ['document' => $document->type->label()]),
            ['type' => 'legal.updated', 'document' => $document->type->value, 'version' => $document->version],
        );

        RelaisProfile::withoutCommuneRestriction()
            ->where('status', RelaisStatus::Active->value)
            ->with('user')
            ->chunkById(200, function ($profiles) use ($notify, $push): void {
                foreach ($profiles as $profile) {
                    /** @var RelaisProfile $profile */
                    $notify->handle($profile->user, $push, null, 'legal');
                }
            });
    }
}
