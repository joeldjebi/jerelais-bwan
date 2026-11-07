<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appels à relais (ADM-14, MOB-23, COO-21) : zone ciblée (national, communes, régions, rayon), type de lieu, consignes, date limite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appels_relais', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 150);
            $table->text('instructions');
            $table->string('target', 20)->comment('national | communes | regions | radius');
            $table->geometry('center', subtype: 'point', srid: 4326)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->foreignId('lieu_type_id')->nullable()->constrained('lieu_types')->nullOnDelete();
            $table->string('status', 20)->default('open')->comment('pending_approval | open | closed');
            $table->boolean('is_local')->default(false)->comment('Appel local d’un coordonnateur');
            $table->timestamp('deadline_at');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });

        Schema::create('appel_commune', function (Blueprint $table) {
            $table->foreignId('appel_relais_id')->constrained('appels_relais')->cascadeOnDelete();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->primary(['appel_relais_id', 'commune_id']);
        });

        Schema::create('appel_region', function (Blueprint $table) {
            $table->foreignId('appel_relais_id')->constrained('appels_relais')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->primary(['appel_relais_id', 'region_id']);
        });

        Schema::table('relais_items', function (Blueprint $table) {
            $table->foreignId('appel_relais_id')->nullable()->after('category_id')->constrained('appels_relais')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appel_relais_id');
        });
        Schema::dropIfExists('appel_region');
        Schema::dropIfExists('appel_commune');
        Schema::dropIfExists('appels_relais');
    }
};
