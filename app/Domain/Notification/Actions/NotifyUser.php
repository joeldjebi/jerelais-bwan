<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Sms\NotificationSms;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Jobs\SendPushNotification;
use App\Models\User;

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
        return config('jerelais.push.driver') !== 'none' && $user->devices()
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->whereIn('platform', [DevicePlatform::Android->value, DevicePlatform::Ios->value])
            ->exists();
    }
}
