<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : les tests réinitialisent la base (RefreshDatabase). Ils refusent de démarrer sur une base
     * qui n'est pas une base de test — par exemple quand un « config:cache » fige la configuration de développement.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test') && $database !== ':memory:') {
            throw new RuntimeException("Tests interrompus : la base « {$database} » n’est pas une base de test. Lancez « php artisan config:clear ».");
        }

        return $app;
    }
}
