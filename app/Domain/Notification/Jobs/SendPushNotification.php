<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Models\Device;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

    public function handle(PushGateway $gateway, NotifyUser $notify): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $devices = $user->devices()
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->whereIn('platform', [DevicePlatform::Android->value, DevicePlatform::Ios->value])
            ->get();

        $delivered = 0;

        foreach ($devices as $device) {
            /** @var Device $device */
            $result = $gateway->send((string) $device->fcm_token, $this->message);

            if ($result === PushResult::Sent) {
                $delivered++;
            } elseif ($result === PushResult::InvalidToken) {
                $device->forceFill(['fcm_token' => null])->save();
            }
        }

        Log::info('[Push] '.$this->context, ['user' => $user->uuid, 'devices' => $devices->count(), 'delivered' => $delivered]);

        if ($delivered === 0) {
            $notify->sendSms($user, $this->sms, $this->context);
        }
    }
}
