<?php

namespace App\Domain\Contribution\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Uploading;
use App\Models\User;

/**
 * Un Relais ne voit que ses relais ; l'équipe voit ceux de son périmètre (Annexe A, CommuneScope en plus).
 */
class RelaisItemPolicy
{
    public function __construct(private readonly CommuneAccess $access) {}

    public function view(User $user, RelaisItem $relais): bool
    {
        if ($relais->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo(Permission::RelaisViewPending->value)
            && $this->access->canAccessCommune($user, $relais->commune_id);
    }

    public function update(User $user, RelaisItem $relais): bool
    {
        return $relais->user_id === $user->id && $relais->state instanceof Uploading;
    }

    public function validate(User $user, RelaisItem $relais): bool
    {
        return $user->hasPermissionTo(Permission::RelaisValidate->value)
            && $this->access->canAccessCommune($user, $relais->commune_id);
    }
}
