<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits des personnes et durées de conservation (§9.4) : pseudonymisation des comptes, purge des médias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('pseudonymized_at')->nullable();
        });

        Schema::table('medias', function (Blueprint $table) {
            $table->timestamp('purged_at')->nullable()->comment('Fichier supprimé du stockage ; empreinte et métadonnées conservées');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('pseudonymized_at'));
        Schema::table('medias', fn (Blueprint $table) => $table->dropColumn('purged_at'));
    }
};
