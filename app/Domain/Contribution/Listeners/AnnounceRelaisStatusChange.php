<?php

namespace App\Domain\Contribution\Listeners;

use App\Domain\Contribution\Events\RelaisQueueChanged;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;

/**
 * Chaque changement de statut est diffusé en temps réel à l'équipe ; le Relais est prévenu des décisions qui le
 * concernent (MOB-21) : notification push, ou SMS s'il n'a pas d'appareil joignable.
 */
class AnnounceRelaisStatusChange
{
    private const NOTIFIED = ['validated', 'rejected', 'complement_requested', 'selected', 'broadcast'];

    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(RelaisStatusChanged $event): void
    {
        RelaisQueueChanged::dispatch($event->relais);

        if (! in_array($event->to, self::NOTIFIED, true)) {
            return;
        }

        $relais = $event->relais;
        $title = (string) str($relais->title ?? '')->limit(40);

        $this->notify->handle(
            $relais->user,
            new PushMessage(
                __("notifications.relais.{$event->to}.title"),
                __("notifications.relais.{$event->to}.body", ['title' => $title]),
                ['type' => "relais.{$event->to}", 'relais' => $relais->uuid],
            ),
            __("relais_items.sms.{$event->to}", ['title' => $title]),
            'relais_item',
        );
    }
}
