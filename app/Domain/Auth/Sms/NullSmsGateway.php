<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Aucune passerelle SMS (production, en attendant le fournisseur) : le message n'est pas envoyé.
 * Le journal ne garde que le numéro masqué, jamais le contenu (qui peut contenir un code).
 */
class NullSmsGateway implements SmsGateway
{
    public function send(PhoneNumber $to, string $message): void
    {
        Log::info('[SMS non envoyé : aucune passerelle configurée] '.$to->masked());
    }
}
