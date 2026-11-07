<?php

namespace App\Domain\Contribution\Listeners;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Contribution\Events\RelaisQueueChanged;
use App\Domain\Contribution\Events\RelaisStatusChanged;

/**
 * Chaque changement de statut est diffusé en temps réel à l'équipe ; le Relais est prévenu par SMS
 * des décisions qui le concernent (MOB-21, SMS de secours en attendant les notifications push).
 */
class AnnounceRelaisStatusChange
{
    private const NOTIFIED = ['validated', 'rejected', 'complement_requested', 'selected', 'broadcast'];

    public function __construct(private readonly SmsGateway $sms) {}

    public function handle(RelaisStatusChanged $event): void
    {
        RelaisQueueChanged::dispatch($event->relais);

        if (! in_array($event->to, self::NOTIFIED, true)) {
            return;
        }

        $phone = $event->relais->user->phoneNumber();

        if ($phone !== null) {
            $this->sms->send($phone, __("relais_items.sms.{$event->to}", ['title' => (string) str($event->relais->title ?? '')->limit(40)]));
        }
    }
}
