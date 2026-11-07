<?php

namespace Database\Factories;

use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commune>
 */
class CommuneFactory extends Factory
{
    protected $model = Commune::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'country_code' => 'CI',
            'is_virtual' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Contour carré fictif (pour les tests) : [minLng, minLat, maxLng, maxLat] en WGS84.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    public function withSquare(array $bbox): static
    {
        return $this->afterCreating(function (Commune $commune) use ($bbox): void {
            DB::update(
                'update communes set geom = ST_Multi(ST_MakeEnvelope(?, ?, ?, ?, 4326)) where id = ?',
                [...$bbox, $commune->id],
            );
        });
    }
}
