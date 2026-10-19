<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le Relais (observateur) recruté : numéro, commune, statut, badge vérifiable (MOB-07, COO-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relais_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('candidature_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->constrained()->restrictOnDelete();
            $table->foreignId('localite_id')->nullable()->constrained('localites')->nullOnDelete();
            $table->string('number', 20)->nullable()->unique();
            $table->string('display_name');
            $table->string('status', 20)->default('active');
            $table->char('badge_token', 40)->unique()->comment('Jeton du QR code de vérification');
            $table->decimal('reliability', 5, 2)->nullable()->comment('Indicateur interne, jamais public');
            $table->timestamp('activated_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index(['commune_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relais_profiles');
    }
};
