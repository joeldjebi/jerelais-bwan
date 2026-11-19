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
    private const NOTIFIED = ['received', 'in_review', 'validated', 'rejected', 'complement_requested', 'selected', 'broadcast'];

    /** Étapes signalées par notification push seulement : pas de SMS de secours (coût). */
    private const PUSH_ONLY = ['received', 'in_review'];

    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(RelaisStatusChanged $event): void
    {
        RelaisQueueChanged::dispatch($event->relais);

        if (! in_array($event->to, self::NOTIFIED, true)) {
            return;
        }

        // Sélection suivie de la diffusion dans le même geste (« Diffuser et publier ») : une seule notification, « diffusé ».
        if ($event->to === 'selected' && $event->relais->broadcast_at !== null) {
            return;
        }

        $relais = $event->relais;
        $title = (string) str($relais->title ?? '')->limit(40);

        $this->notify->handle(
            $relais->user,
            new PushMessage(
                __("notifications.relais.{$event->to}.title"),
                __("notifications.relais.{$event->to}.body", ['title' => $title]),
                ['type' => 'relais.status_changed', 'uuid' => $relais->uuid, 'status' => $event->to],
            ),
            $this->smsFor($event->to, $title),
            'relais_item',
        );
    }

    private function smsFor(string $status, string $title): ?string
    {
        if (in_array($status, self::PUSH_ONLY, true)) {
            return null;
        }

        $text = __("relais_items.sms.{$status}", ['title' => $title]);

        return is_string($text) ? $text : null;
    }
}
