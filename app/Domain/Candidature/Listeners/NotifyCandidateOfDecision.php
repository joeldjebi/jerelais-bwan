<?php

namespace App\Domain\Candidature\Listeners;

use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Events\CandidatureDecided;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;

/**
 * Prévient le candidat de la décision (MOB-21) : notification push, ou SMS s'il n'a pas d'appareil joignable.
 * Le message ne contient aucune donnée sensible (ni motif, ni commentaire) : le détail se consulte une fois connecté.
 */
class NotifyCandidateOfDecision
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(CandidatureDecided $event): void
    {
        if ($event->decision === CandidatureDecision::Hold) {
            return;
        }

        $decision = $event->decision->value;

        $this->notify->handle(
            $event->candidature->user,
            new PushMessage(
                __("notifications.candidature.{$decision}.title"),
                __("notifications.candidature.{$decision}.body"),
                ['type' => 'candidature.decided', 'uuid' => $event->candidature->uuid, 'status' => $decision],
            ),
            __("candidature.sms.{$decision}"),
            'candidature',
        );
    }
}
