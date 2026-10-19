<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Data\PhoneNumber;

/**
 * Passerelle d'envoi de SMS (codes de vérification, notifications de secours).
 */
interface SmsGateway
{
    public function send(PhoneNumber $to, string $message): void;
}
