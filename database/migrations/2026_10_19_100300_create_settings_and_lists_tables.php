<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres de la plateforme et listes paramétrables (ADM-23, Annexe B, COO-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('examples')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('applies_to', 20)->comment('relais | candidature');
            $table->string('label');
            $table->string('slug');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['applies_to', 'slug']);
            $table->index(['applies_to', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejection_reasons');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('settings');
    }
};
