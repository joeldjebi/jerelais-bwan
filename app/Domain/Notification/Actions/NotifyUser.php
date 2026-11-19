<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Models\Device;
use App\Domain\Auth\Sms\NotificationSms;
use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use App\Domain\Notification\Jobs\SendPushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * Prévient un utilisateur de l'application (MOB-21) : notification push sur ses appareils connectés, envoyée en
 * arrière-plan ; SMS de secours s'il n'a aucun appareil joignable ou si aucun push n'est parti.
 */
class NotifyUser
{
    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @param  string|null  $sms  texte du SMS de secours ; null : pas de SMS
     * @return bool prévenu (push programmé ou SMS accepté)
     */
    public function handle(User $user, PushMessage $push, ?string $sms, string $context): bool
    {
        if (self::canReceivePush($user)) {
            SendPushNotification::dispatch($user->id, $push, $sms, $context);

            return true;
        }

        return $this->sendSms($user, $sms, $context);
    }

    /**
     * Envoi immédiat (dans un job) : push sur chaque appareil, jetons refusés oubliés, SMS si aucun push n'est parti.
     *
     * @return 'push'|'sms'|null canal par lequel l'utilisateur a été joint
     */
    public function deliver(User $user, PushMessage $push, ?string $sms, string $context): ?string
    {
        $delivered = 0;

        if (config('jerelais.push.driver') !== 'none') {
            $gateway = app(PushGateway::class);
            $devices = self::pushDevices($user)->get();

            foreach ($devices as $device) {
                /** @var Device $device */
                $result = $gateway->send((string) $device->fcm_token, $push);

                if ($result === PushResult::Sent) {
                    $delivered++;
                } elseif ($result === PushResult::InvalidToken) {
                    $device->forceFill(['fcm_token' => null])->save();
                }
            }

            Log::info('[Push] '.$context, ['user' => $user->uuid, 'devices' => $devices->count(), 'delivered' => $delivered]);
        }

        if ($delivered > 0) {
            return 'push';
        }

        return $this->sendSms($user, $sms, $context) ? 'sms' : null;
    }

    public function sendSms(User $user, ?string $sms, string $context): bool
    {
        $phone = $user->phoneNumber();

        return $sms !== null && $phone !== null && NotificationSms::send($this->sms, $phone, $sms, $context);
    }

    /**
     * Au moins un appareil mobile connecté a donné son jeton de notifications.
     */
    public static function canReceivePush(User $user): bool
    {
        return config('jerelais.push.driver') !== 'none' && self::pushDevices($user)->exists();
    }

    /**
     * @return HasMany<Device, User>
     */
    private static function pushDevices(User $user): HasMany
    {
        return $user->devices()
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->whereIn('platform', [DevicePlatform::Android->value, DevicePlatform::Ios->value]);
    }
}
