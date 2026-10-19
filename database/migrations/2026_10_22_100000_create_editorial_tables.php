<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exploitation éditoriale (ADM-10 à ADM-15) : sélection et affectation, diffusion, exports tracés, collections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->foreignId('selected_by')->nullable()->after('internal_note')->constrained('users')->restrictOnDelete();
            $table->timestamp('selected_at')->nullable()->after('selected_by');
            $table->foreignId('assigned_to')->nullable()->after('selected_at')->comment('Journaliste affecté')->constrained('users')->restrictOnDelete();
            $table->string('programme', 120)->nullable()->after('assigned_to')->comment('Émission prévue');
            $table->timestamp('broadcast_at')->nullable()->after('programme');
            $table->string('broadcast_programme', 120)->nullable()->after('broadcast_at');
            $table->string('broadcast_url', 500)->nullable()->after('broadcast_programme');
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('relais_item_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20)->comment('zip | original');
            $table->foreignId('media_id')->nullable()->constrained('medias')->restrictOnDelete();
            $table->text('derogation_reason')->nullable()->comment('ADM-12 : export d’un relais non validé');
            $table->timestamp('created_at');

            $table->index(['relais_item_id', 'created_at']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('collection_relais_item', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('relais_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->primary(['collection_id', 'relais_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_relais_item');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('export_logs');

        Schema::table('relais_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_by');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['selected_at', 'programme', 'broadcast_at', 'broadcast_programme', 'broadcast_url']);
        });
    }
};
