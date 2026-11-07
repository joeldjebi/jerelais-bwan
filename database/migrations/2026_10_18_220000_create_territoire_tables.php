<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel géographique hiérarchique (§2.2, ADM-20) :
 * région (rattachée à un district) → commune (contour PostGIS) → localité, plus les lieux types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('district')->nullable();
            $table->string('code')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->nullable()->unique();
            $table->char('country_code', 2)->default('CI');
            $table->boolean('is_virtual')->default(false)->comment('Commune virtuelle de la diaspora');
            $table->boolean('is_active')->default(false)->comment('Ouverte aux Relais (activation progressive : pilote puis national)');
            $table->geometry('geom', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();

            $table->index('region_id');
            $table->index('is_active');
            $table->spatialIndex('geom');
        });

        Schema::create('localites', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->geometry('location', subtype: 'point', srid: 4326)->nullable();
            $table->timestamps();

            $table->unique(['commune_id', 'name']);
        });

        Schema::create('lieu_types', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('lieux', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->foreignId('localite_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lieu_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->geometry('location', subtype: 'point', srid: 4326)->nullable();
            $table->timestamps();

            $table->index(['commune_id', 'lieu_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieux');
        Schema::dropIfExists('lieu_types');
        Schema::dropIfExists('localites');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('regions');
    }
};
