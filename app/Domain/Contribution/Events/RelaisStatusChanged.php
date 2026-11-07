<?php

namespace App\Domain\Contribution\Events;

use App\Domain\Contribution\Models\RelaisItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un relais a changé de statut : notification du Relais (MOB-21) et mise à jour en temps réel de la console.
 */
class RelaisStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly RelaisItem $relais,
        public readonly ?string $from,
        public readonly string $to,
    ) {}
}
