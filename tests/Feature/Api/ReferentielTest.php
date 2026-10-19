<?php

use App\Domain\Territoire\Models\Commune;
use Database\Seeders\ListsSeeder;

it('liste uniquement les communes ouvertes aux Relais', function () {
    Commune::factory()->create(['name' => 'Yopougon', 'slug' => 'yopougon']);
    Commune::factory()->inactive()->create(['name' => 'Korhogo', 'slug' => 'korhogo']);

    $this->getJson(route('api.v1.communes.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Yopougon')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.geom');
});

it('liste les catégories de l’Annexe B dans l’ordre [MOB-14]', function () {
    $this->seed(ListsSeeder::class);

    $this->getJson(route('api.v1.categories.index'))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.name', 'Faits divers et accidents')
        ->assertJsonPath('data.9.name', 'Autre');
});

it('expose les paramètres publics à l’application [MOB-11]', function () {
    $this->getJson(route('api.v1.settings.index'))
        ->assertOk()
        ->assertJsonPath('data', fn (array $data) => $data['media.video_max_seconds'] === 180 && ! array_key_exists('validation.lock_minutes', $data));
});

it('seed les motifs de rejet des relais (COO-15) et des candidatures', function () {
    $this->seed(ListsSeeder::class);

    $this->assertDatabaseCount('rejection_reasons', 16);
    $this->assertDatabaseHas('rejection_reasons', ['applies_to' => 'relais', 'label' => 'Mineurs identifiables']);
});
