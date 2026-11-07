<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les relais (contenus envoyés), leur cycle de vie (§8.1) et l'historique de leurs statuts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relais_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->uuid('client_uuid')->unique()->comment('Idempotence : identifiant créé par l’application');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('relais_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->comment('Commune de routage')->constrained()->restrictOnDelete();
            $table->foreignId('declared_commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            $table->string('routed_by', 20)->nullable()->comment('gps | declared');
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('state', 30)->index();
            $table->string('urgency', 20)->default('normal');
            $table->string('title', 120)->nullable();
            $table->text('description')->nullable();

            // Position et métadonnées de capture (MOB-13)
            $table->geometry('location', subtype: 'point', srid: 4326)->nullable();
            $table->unsignedInteger('location_accuracy_m')->nullable();
            $table->string('declared_place', 200)->nullable();
            $table->timestamp('device_captured_at')->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('app_version', 20)->nullable();

            // Protection des personnes (MOB-15, COO-16)
            $table->boolean('has_minors')->nullable();
            $table->string('people_consent', 20)->nullable()->comment('yes | no | not_applicable');
            $table->string('credit_mode', 20)->nullable()->comment('named | pseudonym | anonymous');
            $table->string('credit_name', 80)->nullable();
            $table->boolean('blur_required')->nullable();

            // Traitement (§8.2, COO-14, COO-17)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_until')->nullable();
            $table->foreignId('rejection_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('decision_comment')->nullable();
            $table->text('internal_note')->nullable()->comment('COO-18 : jamais visible du Relais');
            $table->timestamps();

            $table->index(['commune_id', 'state', 'urgency', 'received_at']);
            $table->index(['user_id', 'created_at']);
            $table->spatialIndex('location');
        });

        Schema::create('relais_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relais_item_id')->constrained()->cascadeOnDelete();
            $table->string('from', 30)->nullable();
            $table->string('to', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['relais_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relais_status_history');
        Schema::dropIfExists('relais_items');
    }
};
