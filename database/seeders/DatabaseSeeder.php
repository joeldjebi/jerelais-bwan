<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\Role;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TerritoireSeeder::class,
            ListsSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->seedDemoAccounts();
        }
    }

    /**
     * Comptes de démonstration locaux (mot de passe « password »), jamais créés hors environnement local.
     */
    private function seedDemoAccounts(): void
    {
        User::factory()->withTwoFactor()->withRole(Role::Admin)->create([
            'name' => 'Admin Démo',
            'email' => 'admin@jerelais.test',
        ]);

        $yopougon = Commune::query()->where('slug', 'yopougon')->first();

        if ($yopougon !== null) {
            User::factory()->withTwoFactor()->coordonnateurOf($yopougon)->create([
                'name' => 'Coordonnateur Yopougon',
                'email' => 'coordo.yopougon@jerelais.test',
            ]);
        }
    }
}
