<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Rémunération décidée relais par relais (MOB-26, ADM-27) : l'administration ou le superviseur décide de payer ou
 * non un relais validé, et le montant (libre). Chaque décision est historisée. Ajoute la permission « gains.decide »
 * (administration et superviseurs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gain_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('relais_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20)->comment('credited, declined, amended, cancelled');
            $table->unsignedInteger('amount')->nullable()->comment('FCFA, après la décision');
            $table->unsignedInteger('previous_amount')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['relais_item_id', 'id']);
        });

        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gain_decisions');
    }
};
