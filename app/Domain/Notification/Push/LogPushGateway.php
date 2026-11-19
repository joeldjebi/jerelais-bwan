<?php

namespace App\Domain\Notification\Push;

use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle de développement : écrit la notification dans le journal local, sans le jeton.
 */
class LogPushGateway implements PushGateway
{
    public function send(string $token, PushMessage $message): PushResult
    {
        Log::channel('single')->info('[Push de développement] '.$message->title.' : '.$message->body, $message->data);

        return PushResult::Sent;
    }
}
