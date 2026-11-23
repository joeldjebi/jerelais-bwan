<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portefeuille du Relais (MOB-26, ADM-27) : chaque relais diffusé crédite le portefeuille ; le Relais demande un
 * retrait, l'administration le paie par Mobile Money (ou le refuse). Les paiements déjà enregistrés deviennent des
 * retraits payés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('status', 20)->default('paid')->after('amount')->index()->comment('requested, paid, rejected');
            $table->string('holder_name', 120)->nullable()->after('phone');
            $table->timestamp('requested_at')->nullable()->after('holder_name');
            $table->string('rejection_reason', 500)->nullable()->after('paid_by');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->string('reference', 120)->nullable()->change();
            $table->timestamp('paid_at')->nullable()->change();
        });

        DB::table('payouts')->whereNull('requested_at')->update(['requested_at' => DB::raw('paid_at')]);
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['status', 'holder_name', 'requested_at', 'rejection_reason', 'rejected_at']);
        });
    }
};
