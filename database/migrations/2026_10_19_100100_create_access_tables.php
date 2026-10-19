<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Périmètre territorial des coordonnateurs (par commune) et des superviseurs (par région) — ADM-21.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commune_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->string('assignment', 20)->default('titulaire');
            $table->timestamps();

            $table->unique(['user_id', 'commune_id']);
            $table->index(['commune_id', 'assignment']);
        });

        Schema::create('region_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_user');
        Schema::dropIfExists('commune_user');
    }
};
