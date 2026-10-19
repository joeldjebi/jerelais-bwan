<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;

/**
 * Passerelle de test : conserve les SMS envoyés en mémoire.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(PhoneNumber $to, string $message): void
    {
        $this->sent[] = ['to' => $to->e164, 'message' => $message];
    }

    public function lastCodeFor(string $e164): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['to'] === $e164 && preg_match('/\b(\d{6})\b/', $sms['message'], $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
