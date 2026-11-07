<?php

use App\Domain\Territoire\Actions\ResolveCommuneFromPoint;
use App\Domain\Territoire\Models\Commune;
use Clickbar\Magellan\Data\Geometries\Point;

beforeEach(function () {
    $this->west = Commune::factory()->withSquare([-4.10, 5.30, -4.00, 5.40])->create(['name' => 'Ouest', 'slug' => 'ouest']);
    $this->east = Commune::factory()->withSquare([-4.00, 5.30, -3.90, 5.40])->create(['name' => 'Est', 'slug' => 'est']);
});

it('déduit la commune qui contient la position GPS [MOB-13]', function () {
    $resolved = app(ResolveCommuneFromPoint::class)->handle(Point::makeGeodetic(5.35, -4.05));

    expect($resolved?->is($this->west))->toBeTrue();
});

it('route vers la commune du lieu de capture, pas celle de résidence [§8.2]', function () {
    $resolved = app(ResolveCommuneFromPoint::class)->handle(Point::makeGeodetic(5.35, -3.95));

    expect($resolved?->is($this->east))->toBeTrue();
});

it('ne trouve aucune commune hors de tout contour [§8.2 repli sur la commune déclarée]', function () {
    expect(app(ResolveCommuneFromPoint::class)->handle(Point::makeGeodetic(7.69, -5.03)))->toBeNull();
});

it('ignore les communes virtuelles de la diaspora', function () {
    Commune::query()->whereKey($this->west->id)->update(['is_virtual' => true]);

    expect(app(ResolveCommuneFromPoint::class)->handle(Point::makeGeodetic(5.35, -4.05)))->toBeNull();
});
