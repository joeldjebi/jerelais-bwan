<?php

use App\Domain\Access\Enums\Role;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Livewire\Console\Privacy\Index as PrivacyPage;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\PermissionRegistrar;

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
        'api.v1.settings.index', 'api.v1.communes.localites', 'api.v1.candidature.options', 'api.v1.legal.index', 'api.v1.formation.index',
        'api.v1.diffusions.index']; // même contenu que la vitrine publique (WEB-05)

    $routes = collect(Router::getRoutes()->getRoutes())->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1') && ! in_array($route->getName(), $public, true));

    foreach ($routes as $route) {
        expect(in_array('auth:sanctum', $route->gatherMiddleware(), true))->toBeTrue("La route {$route->uri()} doit être authentifiée.");
    }
});

it('refuse la vérification de production sur une configuration de développement', function () {
    $this->artisan('jerelais:check-production')->assertFailed();
});

it('revérifie la permission à chaque action, même sur un onglet resté ouvert (revue sécurité)', function () {
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $relais = User::factory()->withRole(Role::Relais)->create(['email' => 'cible@exemple.ci']);

    $page = Livewire::actingAs($admin)->test(PrivacyPage::class)->set('query', 'cible@exemple.ci')->call('search');

    $admin->syncRoles([Role::Journaliste->value]);
    $admin->unsetRelation('roles')->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $page->set('reason', 'Demande de suppression')->call('pseudonymize')->assertForbidden();

    expect($relais->fresh()->pseudonymized_at)->toBeNull();
});

it('rejoue les middlewares d’accès sur les requêtes Livewire', function () {
    $middleware = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($middleware)->toContain(EnsureUserIsStaff::class, EnsureTwoFactorIsEnabled::class, PermissionMiddleware::class);
});

it('réserve la supervision Horizon aux administrateurs', function () {
    expect(Gate::forUser(User::factory()->withRole(Role::Admin)->create())->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->withRole(Role::Journaliste)->create())->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::allows('viewHorizon'))->toBeFalse();
});
