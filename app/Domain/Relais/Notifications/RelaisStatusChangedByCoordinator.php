<?php

namespace App\Domain\Relais\Notifications;

use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Information de l'administrateur (COO-04) : un coordonnateur a suspendu, radié ou réintégré un Relais.
 */
class RelaisStatusChangedByCoordinator extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RelaisProfile $profile,
        public readonly RelaisStatus $status,
        public readonly User $coordinator,
        public readonly string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('JE RELAIS : Relais :status', ['status' => mb_strtolower($this->status->label())]))
            ->line(__(':coordinator a changé le statut du Relais :number (:name) : :status.', [
                'coordinator' => $this->coordinator->name,
                'number' => $this->profile->number,
                'name' => $this->profile->display_name,
                'status' => mb_strtolower($this->status->label()),
            ]))
            ->line(__('Motif : :reason', ['reason' => $this->reason]))
            ->action(__('Voir la fiche'), route('console.relais.show', $this->profile));
    }
}
