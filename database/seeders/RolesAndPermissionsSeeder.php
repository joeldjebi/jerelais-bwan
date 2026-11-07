<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions de la matrice des droits (Annexe A). Idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // Les événements de modèle peuvent être coupés pendant le seed : on vide le cache explicitement.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $role->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
