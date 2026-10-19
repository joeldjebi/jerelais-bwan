<?php

namespace App\Domain\Contribution\Events;

use App\Domain\Contribution\Models\RelaisItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Temps réel (§8.2) : la console et la file du coordonnateur de la commune sont prévenues de chaque changement.
 * Diffusé par la file d'attente : une indisponibilité de Reverb ne bloque jamais l'envoi d'un relais.
 * Aucune donnée personnelle dans le message, seulement l'identifiant, le statut et l'urgence.
 */
class RelaisQueueChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $uuid;

    public string $state;

    public string $urgency;

    private ?int $communeId;

    public function __construct(RelaisItem $relais)
    {
        $this->uuid = $relais->uuid;
        $this->state = $relais->state->getValue();
        $this->urgency = $relais->urgency->value;
        $this->communeId = $relais->commune_id;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('console.relais')];

        if ($this->communeId !== null) {
            $channels[] = new PrivateChannel("communes.{$this->communeId}.relais");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'relais.changed';
    }
}
