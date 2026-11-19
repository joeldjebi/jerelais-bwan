<?php

namespace App\Domain\Notification\Push;

use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;

/**
 * Passerelle de test : conserve les notifications en mémoire ; les jetons listés dans `invalidTokens` sont refusés.
 */
class FakePushGateway implements PushGateway
{
    /** @var list<array{token: string, message: PushMessage}> */
    public array $sent = [];

    /** @var list<string> */
    public array $invalidTokens = [];

    public function send(string $token, PushMessage $message): PushResult
    {
        if (in_array($token, $this->invalidTokens, true)) {
            return PushResult::InvalidToken;
        }

        $this->sent[] = ['token' => $token, 'message' => $message];

        return PushResult::Sent;
    }
}
