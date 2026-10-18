<?php

namespace Database\Seeders;

use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Référentiel de départ : lieux types du cahier des charges (§2.2) et communes du
 * District autonome d'Abidjan, sans contour. Les contours officiels sont importés
 * ensuite avec `php artisan territoire:import-communes` (fichier fourni par REFLET TV).
 */
class TerritoireSeeder extends Seeder
{
    /** @var list<string> */
    private const LIEU_TYPES = ['École', 'Lycée', 'Université', 'Marché', 'Gare', 'Hôpital', 'Mairie'];

    /** @var list<string> */
    private const COMMUNES_ABIDJAN = [
        'Abobo', 'Adjamé', 'Anyama', 'Attécoubé', 'Bingerville', 'Cocody', 'Koumassi',
        'Marcory', 'Plateau', 'Port-Bouët', 'Songon', 'Treichville', 'Yopougon',
    ];

    public function run(): void
    {
        foreach (self::LIEU_TYPES as $name) {
            LieuType::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }

        $abidjan = Region::query()->firstOrCreate(
            ['slug' => 'district-autonome-d-abidjan'],
            ['name' => "District autonome d'Abidjan", 'district' => "District autonome d'Abidjan"],
        );

        foreach (self::COMMUNES_ABIDJAN as $name) {
            Commune::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'region_id' => $abidjan->id, 'is_active' => false],
            );
        }
    }
}
