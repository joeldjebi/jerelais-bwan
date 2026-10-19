<?php

namespace App\Domain\Contribution\Notifications;

use App\Domain\Contribution\Models\RelaisItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Relais non traité dans le délai cible (COO-17) : alerte du niveau d'escalade suivant.
 * Le message ne contient ni média ni donnée personnelle, seulement le lien vers la console.
 */
class RelaisOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RelaisItem $relais, public readonly int $level) {}

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
            ->subject(__('JE RELAIS : relais en attente depuis trop longtemps'))
            ->greeting(__('Bonjour,'))
            ->line(__('Un relais :urgency de :commune n’a pas été traité dans le délai cible.', [
                'urgency' => mb_strtolower($this->relais->urgency->label()),
                'commune' => $this->relais->commune?->name,
            ]))
            ->line(__('Il vous est transmis au niveau d’escalade :level.', ['level' => $this->level]))
            ->action(__('Ouvrir le relais'), route('console.validation.show', $this->relais));
    }
}
