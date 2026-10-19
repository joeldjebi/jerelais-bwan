<?php

namespace App\Domain\Territoire\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;

/**
 * Ouvre ou ferme une commune aux Relais (activation progressive : pilote puis lancement national — §14).
 */
class SetCommuneAvailability
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Commune $commune, bool $open, User $actor): void
    {
        if ($commune->is_active === $open) {
            return;
        }

        $commune->forceFill(['is_active' => $open])->save();

        $this->audit->record(
            $open ? 'territoire.commune.opened' : 'territoire.commune.closed',
            $commune,
            ['name' => $commune->name],
            $actor,
            $commune->id,
        );
    }
}
