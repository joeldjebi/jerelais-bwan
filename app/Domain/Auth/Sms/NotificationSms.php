<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\SmsNotSent;
use Illuminate\Support\Facades\Log;

/**
 * SMS d'information (décision, appel à relais, message du coordonnateur) : un échec du fournisseur ne doit pas
 * annuler l'action qui l'a déclenché. Il est journalisé (numéro masqué) et l'action continue.
 * Les codes de vérification, eux, passent directement par la passerelle : leur échec est signalé à l'utilisateur.
 */
class NotificationSms
{
    public static function send(SmsGateway $sms, PhoneNumber $to, string $message, string $context): bool
    {
        try {
            $sms->send($to, $message);

            return true;
        } catch (SmsNotSent $exception) {
            Log::warning('[SMS] notification non envoyée', ['context' => $context, 'to' => $to->masked(), 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
