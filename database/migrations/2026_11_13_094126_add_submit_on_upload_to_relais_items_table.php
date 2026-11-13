<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoi en un seul appel depuis l'application (MOB-19) : le relais est soumis automatiquement
 * dès que son dernier fichier est reçu. Sans effet sur les relais créés autrement (site web).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->boolean('submit_on_upload')->default(false)->after('client_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('relais_items', fn (Blueprint $table) => $table->dropColumn('submit_on_upload'));
    }
};
