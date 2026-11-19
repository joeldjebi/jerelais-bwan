<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement des Relais (MOB-26, ADM-27) : coordonnées Mobile Money choisies par le Relais (une par compte) et
 * historique des paiements reçus (un versement solde une ou plusieurs commissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 30)->comment('wave, orange_money, mtn_momo, moov_money');
            $table->text('phone')->comment('Chiffré');
            $table->string('holder_name', 120)->comment('Nom du titulaire du compte Mobile Money');
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount')->comment('FCFA');
            $table->string('method', 30);
            $table->string('reference', 120);
            $table->text('phone')->nullable()->comment('Chiffré');
            $table->timestamp('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_accounts');
    }
};
