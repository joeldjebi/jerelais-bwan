<?php

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Sms\FakeSmsGateway;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tests fonctionnels : base PostgreSQL de test réinitialisée, rôles et permissions de l'Annexe A en place.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Remplace la passerelle SMS par une passerelle en mémoire et la retourne.
 */
function fakeSms(): FakeSmsGateway
{
    $gateway = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $gateway);

    return $gateway;
}

/**
 * Données d'appareil valides pour les connexions de l'application.
 *
 * @return array<string, string>
 */
function deviceFields(): array
{
    return ['device_name' => 'Tecno Spark 10', 'platform' => 'android', 'app_version' => '1.0.0'];
}
