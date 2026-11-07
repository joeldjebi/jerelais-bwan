<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Passerelle de développement : écrit le SMS dans le journal local.
 * Refusée en production, où le message contient un code de vérification.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(PhoneNumber $to, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('La passerelle SMS « log » est interdite en production.');
        }

        Log::channel('single')->info('[SMS de développement] '.$to->masked().' : '.$message);
    }
}
