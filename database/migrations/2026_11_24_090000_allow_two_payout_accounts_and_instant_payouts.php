<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portefeuille (MOB-26) : un Relais enregistre jusqu'à deux numéros Mobile Money et choisit lequel créditer à chaque
 * retrait ; le retrait part immédiatement par la passerelle de paiement (« simulation » tant qu'aucun prestataire
 * n'est branché, « manual » : payé depuis la console). Chaque retrait garde la passerelle qui l'a réglé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index('user_id');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('method')->comment('simulation, manual, puis le prestataire de paiement');
        });

        DB::table('payouts')->where('status', 'paid')->update(['provider' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
