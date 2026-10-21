<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Référentiels de la plateforme ; en local, le jeu de démonstration complet pour la recette (DemoSeeder).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TerritoireSeeder::class,
            ListsSeeder::class,
            LegalDocumentsSeeder::class,
            FormationSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
