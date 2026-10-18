<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(10)->uncompromised()
            : Password::min(10));
    }
}
