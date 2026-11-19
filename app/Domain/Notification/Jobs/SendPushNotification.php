<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoie la notification sur chaque appareil connecté de l'utilisateur (MOB-21). Un jeton refusé par Firebase est
 * oublié ; si aucun appareil n'a reçu la notification, le SMS de secours part. Pas de nouvel essai automatique :
 * il enverrait deux fois la notification aux appareils déjà servis.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
        public readonly PushMessage $message,
        public readonly ?string $sms,
        public readonly string $context,
    ) {}

    public function handle(NotifyUser $notify): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $notify->deliver($user, $this->message, $this->sms, $this->context);
        }
    }
}
