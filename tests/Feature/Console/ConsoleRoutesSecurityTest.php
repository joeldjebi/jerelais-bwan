<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Garde-fou : toute page de la console exige un membre de l'équipe et sa double authentification (ADM-24).
 */
it('protège chaque route de la console par l’équipe et la double authentification', function () {
    $console = collect(Router::getRoutes()->getRoutes())->filter(fn (Route $route) => str_starts_with($route->uri(), 'console'));

    expect($console)->not->toBeEmpty();

    foreach ($console as $route) {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('auth')->toContain('staff');

        if ($route->getName() !== 'console.security') {
            expect(in_array('two-factor', $middleware, true))->toBeTrue("La route {$route->uri()} doit exiger la double authentification.");
        }
    }
});

it('exige une session ou un jeton sur toutes les routes de l’API hors référentiels publics', function () {
    $public = ['api.v1.auth.otp.request', 'api.v1.auth.otp.verify', 'api.v1.auth.login', 'api.v1.communes.index', 'api.v1.categories.index',
        'api.v1.settings.index', 'api.v1.communes.localites', 'api.v1.candidature.options', 'api.v1.legal.index', 'api.v1.formation.index'];

    $routes = collect(Router::getRoutes()->getRoutes())->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1') && ! in_array($route->getName(), $public, true));

    foreach ($routes as $route) {
        expect(in_array('auth:sanctum', $route->gatherMiddleware(), true))->toBeTrue("La route {$route->uri()} doit être authentifiée.");
    }
});

it('refuse la vérification de production sur une configuration de développement', function () {
    $this->artisan('jerelais:check-production')->assertFailed();
});
