<?php

namespace App\Domain\AppelRelais\Jobs;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Prévient les Relais de la zone d'un nouvel appel (MOB-21, MOB-23) : notification push, ou SMS court sans appareil
 * joignable ; le détail est dans l'application.
 */
class NotifyAppelRecipients implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>|null  $profileIds  seulement ces Relais (zone élargie après modification) ; null : toute la zone
     */
    public function __construct(public readonly int $appelId, public readonly ?array $profileIds = null) {}

    public function handle(NotifyUser $notify): void
    {
        $appel = AppelRelais::query()->find($this->appelId);

        if ($appel === null || ! $appel->isOpen()) {
            return;
        }

        $replace = ['title' => str($appel->title)->limit(50), 'deadline' => $appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('j M à H:i')];
        $sms = (string) __('appels_relais.sms', $replace);
        $push = new PushMessage(__('notifications.appel.title'), __('notifications.appel.body', $replace), ['type' => 'appel.created', 'uuid' => $appel->uuid]);

        AppelAudience::profiles($appel)->when($this->profileIds !== null, fn ($query) => $query->whereIn('relais_profiles.id', $this->profileIds))->with('user')->chunkById(200, function ($profiles) use ($notify, $sms, $push): void {
            foreach ($profiles as $profile) {
                /** @var RelaisProfile $profile */
                $notify->handle($profile->user, $push, $sms, 'appel');
            }
        });
    }
}
