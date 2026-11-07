<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Passerelle de recette locale : chaque SMS devient un courriel dans Mailpit (http://localhost:8025),
 * adressé au numéro complet pour retrouver facilement le code d'un Relais. Refusée en production.
 */
class MailpitSmsGateway implements SmsGateway
{
    public function send(PhoneNumber $to, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('La passerelle SMS « mailpit » est interdite en production.');
        }

        $number = ltrim($to->e164, '+');

        Mail::raw($message, function (Message $mail) use ($number, $message): void {
            $mail->to("sms-{$number}@sms.jerelais.test")
                ->subject('SMS +'.$number.' — '.mb_strimwidth($message, 0, 60, '…'));
        });
    }
}
