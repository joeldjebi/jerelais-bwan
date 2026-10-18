<?php

use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use Illuminate\Support\Facades\DB;

$fixture = dirname(__DIR__, 2).'/Fixtures/communes-fictives.geojson';

it('importe les communes, leurs régions et leurs contours PostGIS [ADM-20]', function () use ($fixture) {
    $this->artisan('territoire:import-communes', [
        'path' => $fixture,
        '--name-field' => 'NOM',
        '--region-field' => 'REGION',
        '--code-field' => 'CODE',
    ])->expectsOutputToContain('Communes créées : 2 · mises à jour : 0 · ignorées : 1')
        ->assertSuccessful();

    expect(Region::query()->where('slug', 'region-test')->exists())->toBeTrue()
        ->and(Commune::query()->count())->toBe(2);

    $alpha = Commune::query()->where('slug', 'commune-alpha')->firstOrFail();

    expect($alpha->code)->toBe('T-001')
        ->and($alpha->is_active)->toBeFalse()
        ->and(DB::scalar('select GeometryType(geom) from communes where id = ?', [$alpha->id]))->toBe('MULTIPOLYGON');
});

it('est idempotent : un second import met à jour sans dupliquer [ADM-20]', function () use ($fixture) {
    $arguments = ['path' => $fixture, '--name-field' => 'NOM'];

    $this->artisan('territoire:import-communes', $arguments)->assertSuccessful();
    $this->artisan('territoire:import-communes', $arguments)
        ->expectsOutputToContain('Communes créées : 0 · mises à jour : 2')
        ->assertSuccessful();

    expect(Commune::query()->count())->toBe(2);
});

it('refuse un fichier qui n’est pas une FeatureCollection', function () {
    $path = tempnam(sys_get_temp_dir(), 'geo');
    file_put_contents($path, json_encode(['type' => 'Feature']));

    $this->artisan('territoire:import-communes', ['path' => $path])->assertFailed();

    expect(Commune::query()->count())->toBe(0);
});

it('signale un fichier introuvable', function () {
    $this->artisan('territoire:import-communes', ['path' => '/chemin/inexistant.geojson'])->assertFailed();
});
