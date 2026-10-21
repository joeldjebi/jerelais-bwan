<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
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
        $this->configureDevCommands();
    }

    /**
     * « php artisan dev » lance toute la plateforme pour la recette locale, avec le même binaire PHP
     * (8.4) que la commande : serveur, files (médias puis défaut), temps réel, tâches planifiées, journaux.
     */
    private function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole() || ! $this->app->isLocal()) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY).' artisan';

        DevCommands::withoutDefaultCommands();
        DevCommands::register("PHP_CLI_SERVER_WORKERS=4 {$php} serve --port=8000 --no-reload", 'server');
        // Horizon (files « default » et « medias ») comme en production ; remplace sa commande par défaut lancée avec « php ».
        DevCommands::register("{$php} horizon", 'horizon');
        DevCommands::register("{$php} reverb:start", 'reverb');
        DevCommands::register("{$php} schedule:work", 'scheduler');
        DevCommands::register("{$php} pail --timeout=0", 'logs');
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
