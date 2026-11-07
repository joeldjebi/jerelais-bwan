<?php

namespace App\Domain\Contribution\Exceptions;

use App\Domain\Contribution\Models\RelaisItem;
use RuntimeException;

/**
 * Un autre membre de l'équipe traite déjà ce relais (§8.2 : verrou de 10 minutes).
 */
class RelaisLocked extends RuntimeException
{
    public static function by(RelaisItem $relais): self
    {
        return new self(__('relais_items.errors.locked', [
            'name' => $relais->lockedBy->name ?? __('un autre membre'),
            'time' => $relais->locked_until?->format('H:i'),
        ]));
    }
}
