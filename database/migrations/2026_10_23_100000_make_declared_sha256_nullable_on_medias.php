<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoi depuis le site (WEB-04) : l'empreinte annoncée est facultative pour les très gros fichiers,
 * le serveur la calcule de toute façon à la réception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medias', function (Blueprint $table) {
            $table->char('declared_sha256', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medias', function (Blueprint $table) {
            $table->char('declared_sha256', 64)->nullable(false)->change();
        });
    }
};
