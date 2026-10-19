<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureDefaults();
        $this->configureLivewire();
    }

    /**
     * Les middlewares d'accès à la console sont réappliqués à chaque requête Livewire (/livewire/update) :
     * un droit retiré prend effet immédiatement, même sur un onglet resté ouvert.
     */
    private function configureLivewire(): void
    {
        Livewire::addPersistentMiddleware([
            EnsureUserIsStaff::class,
            EnsureTwoFactorIsEnabled::class,
            PermissionMiddleware::class,
            RoleMiddleware::class,
        ]);
    }

    /**
     * Eloquent strict hors production : pas de lazy loading silencieux (N+1),
     * pas d'attribut inconnu, pas d'affectation de masse ignorée.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Valeurs par défaut de la plateforme (sécurité et dates).
     */
    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale(config('app.locale'));

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(10)->uncompromised()
            : Password::min(10));
    }
}
