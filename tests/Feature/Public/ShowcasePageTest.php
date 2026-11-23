<?php

use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Territoire\Models\Commune;
use Database\Seeders\ListsSeeder;

/*
 * Vitrine publique « Vu sur REFLET TV » (WEB-05) : filtres et pagination, mêmes filtres dans GET /diffusions.
 */

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->abobo = Commune::factory()->create(['name' => 'Abobo', 'slug' => 'abobo']);
    $this->cocody = Commune::factory()->create(['name' => 'Cocody', 'slug' => 'cocody']);
    $this->sport = Category::query()->orderBy('id')->firstOrFail();
    $this->meteo = Category::query()->orderBy('id')->skip(1)->firstOrFail();
});

function publishedRelais(string $title, Commune $commune, Category $category, int $daysAgo = 1): RelaisItem
{
    return RelaisItem::factory()->create([
        'state' => Broadcast::class,
        'commune_id' => $commune->id,
        'category_id' => $category->id,
        'showcased_at' => now()->subDays($daysAgo),
        'broadcast_at' => now()->subDays($daysAgo),
        'broadcast_programme' => 'Journal de 20 h',
        'showcase_title' => $title,
        'showcase_with_image' => false,
        'showcase_visual' => 'none',
    ]);
}

it('filtre les diffusions par recherche, commune, catégorie et période, et propose de retirer chaque filtre [WEB-05]', function () {
    publishedRelais('Match de quartier à Abobo', $this->abobo, $this->sport);
    publishedRelais('Pluies diluviennes à Cocody', $this->cocody, $this->meteo);
    publishedRelais('Ancienne inondation à Cocody', $this->cocody, $this->meteo, 200);

    $this->get(route('showcase'))->assertOk()->assertSee('3 diffusions')
        ->assertSee('Match de quartier à Abobo')->assertSee('Pluies diluviennes à Cocody');

    $this->get(route('showcase', ['commune' => 'cocody']))->assertOk()
        ->assertSee('2 diffusions')->assertDontSee('Match de quartier à Abobo')
        ->assertSee('Retirer le filtre Cocody', false);

    $this->get(route('showcase', ['categorie' => $this->sport->slug]))->assertSee('1 diffusion')->assertSee('Match de quartier');
    $this->get(route('showcase', ['q' => 'pluies']))->assertSee('Pluies diluviennes à Cocody')->assertDontSee('Ancienne inondation');
    $this->get(route('showcase', ['commune' => 'cocody', 'periode' => 'mois']))->assertSee('1 diffusion')->assertDontSee('Ancienne inondation');
    $this->get(route('showcase', ['q' => 'introuvable']))->assertOk()->assertSee('Aucune diffusion ne correspond.');
    $this->get(route('showcase', ['periode' => 'nimporte', 'tri' => 'pirate']))->assertOk()->assertSee('3 diffusions');
});

it('pagine par 12 en conservant les filtres dans les liens, du plus récent au plus ancien ou l’inverse [WEB-05]', function () {
    foreach (range(1, 14) as $day) {
        publishedRelais("Diffusion numéro {$day}", $this->abobo, $this->sport, $day);
    }

    $first = $this->get(route('showcase', ['commune' => 'abobo']))->assertOk()
        ->assertSee('page 1 sur 2')
        ->assertSee('Diffusion numéro 1')->assertDontSee('Diffusion numéro 13')
        ->assertSee(route('showcase', ['commune' => 'abobo', 'page' => 2]));

    $this->get(route('showcase', ['commune' => 'abobo', 'page' => 2]))->assertOk()
        ->assertSee('Diffusion numéro 13')->assertSee('Diffusion numéro 14')->assertSee('aria-current="page"', false);

    $this->get(route('showcase', ['tri' => 'anciens']))->assertSeeInOrder(['Diffusion numéro 14', 'Diffusion numéro 13']);
});

it('applique les mêmes filtres au fil GET /diffusions de l’application [MOB-25]', function () {
    publishedRelais('Match de quartier à Abobo', $this->abobo, $this->sport);
    publishedRelais('Pluies diluviennes à Cocody', $this->cocody, $this->meteo);

    $this->getJson(route('api.v1.diffusions.index', ['commune' => 'cocody']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pluies diluviennes à Cocody');
    $this->getJson(route('api.v1.diffusions.index', ['q' => 'match']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.commune', 'Abobo');
    $this->getJson(route('api.v1.diffusions.index'))->assertJsonCount(2, 'data');
});
