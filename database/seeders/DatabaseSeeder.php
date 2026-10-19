<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\Role;
use App\Domain\Territoire\Enums\LocaliteType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
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
     * Comptes de démonstration locaux (mot de passe « 12345678 »), jamais créés hors environnement local.
     * Code de double authentification : DEMO_TWO_FACTOR_CODE (123456) ou un code TOTP réel.
     */
    private function seedDemoAccounts(): void
    {
        $admin = $this->demoAccount('admin@jerelais.com', 'Admin Démo');
        $admin->syncRoles([Role::Admin->value]);

        $coordinateur = $this->demoAccount('coordinateur@jerelais.com', 'Coordinateur Yopougon');
        $coordinateur->syncRoles([Role::Coordonnateur->value]);

        $yopougon = Commune::query()->where('slug', 'yopougon')->first();

        if ($yopougon !== null) {
            $coordinateur->communes()->syncWithoutDetaching([$yopougon->id => ['assignment' => 'titulaire']]);

            // Commune pilote ouverte, avec quelques quartiers, pour tester la candidature.
            $yopougon->update(['is_active' => true]);

            foreach (['Niangon', 'Selmer', 'Toits Rouges', 'Siporex', 'Andokoi', 'Maroc', 'Wassakara', 'Sideci'] as $quartier) {
                Localite::query()->firstOrCreate(
                    ['commune_id' => $yopougon->id, 'name' => $quartier],
                    ['type' => LocaliteType::Quartier],
                );
            }
        }
    }

    private function demoAccount(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => '12345678']);
        $user->forceFill([
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['demo-recovery-1', 'demo-recovery-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }
}
