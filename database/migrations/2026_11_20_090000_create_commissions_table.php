<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Commissions des Relais (MOB-26, ADM-27) : un montant dû par relais validé ou diffusé, payé à la main par
 * Mobile Money puis marqué « payé » avec sa référence. Ajoute la permission « commissions.manage ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('relais_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->comment('validated, broadcast');
            $table->unsignedInteger('amount')->comment('FCFA');
            $table->string('status', 20)->default('due')->index()->comment('due, paid, cancelled');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('payment_phone')->nullable()->comment('Chiffré');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['relais_item_id', 'kind']);
            $table->index(['user_id', 'status']);
        });

        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
