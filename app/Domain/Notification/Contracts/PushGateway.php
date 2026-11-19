<?php

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;

/**
 * Envoi d'une notification push vers un appareil (MOB-21). Ne lève pas d'exception : le résultat dit si le message
 * est parti, si le jeton n'est plus valable (à oublier) ou si l'envoi a échoué (SMS de secours).
 */
interface PushGateway
{
    public function send(string $token, PushMessage $message): PushResult;
}
