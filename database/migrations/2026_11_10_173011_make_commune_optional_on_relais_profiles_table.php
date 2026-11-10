<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription depuis l'application (MOB-01) : le Relais est activé avant d'avoir déclaré sa commune.
 * La commune est renseignée à la vérification de son identité ; ses relais sont routés par GPS ou commune déclarée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relais_profiles', function (Blueprint $table) {
            $table->foreignId('commune_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('relais_profiles', function (Blueprint $table) {
            $table->foreignId('commune_id')->nullable(false)->change();
        });
    }
};
