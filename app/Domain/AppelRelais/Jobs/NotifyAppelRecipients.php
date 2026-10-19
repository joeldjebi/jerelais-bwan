<?php

namespace App\Domain\AppelRelais\Jobs;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Prévient les Relais de la zone d'un nouvel appel (MOB-21, MOB-23) : SMS court, le détail est dans l'application.
 */
class NotifyAppelRecipients implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $appelId) {}

    public function handle(SmsGateway $sms): void
    {
        $appel = AppelRelais::query()->find($this->appelId);

        if ($appel === null || ! $appel->isOpen()) {
            return;
        }

        $message = __('appels_relais.sms', ['title' => str($appel->title)->limit(50), 'deadline' => $appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('j M à H:i')]);

        AppelAudience::profiles($appel)->with('user')->chunkById(200, function ($profiles) use ($sms, $message): void {
            foreach ($profiles as $profile) {
                /** @var RelaisProfile $profile */
                $phone = $profile->user->phoneNumber();

                if ($phone !== null) {
                    $sms->send($phone, (string) $message);
                }
            }
        });
    }
}
