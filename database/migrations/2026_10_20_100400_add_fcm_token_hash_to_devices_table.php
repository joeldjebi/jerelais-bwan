<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empreinte du jeton push (chiffré) pour repérer un même appareil sur plusieurs comptes (COO-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->char('fcm_token_hash', 64)->nullable()->index()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('fcm_token_hash');
        });
    }
};
