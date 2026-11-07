<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiers de candidature des futurs Relais (MOB-02 à MOB-06, WEB-02, COO-01, COO-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatures', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('localite_id')->nullable()->constrained('localites')->nullOnDelete();
            $table->foreignId('lieu_type_id')->nullable()->constrained('lieu_types')->nullOnDelete();
            $table->string('status', 30)->default('draft');

            // Identité (MOB-02)
            $table->string('last_name')->nullable();
            $table->string('first_names')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_minor')->default(false);

            // Profil (MOB-02)
            $table->string('occupation', 40)->nullable();
            $table->jsonb('languages')->nullable();
            $table->jsonb('interests')->nullable();
            $table->text('motivation')->nullable();

            // Suivi et décision (MOB-06, COO-02)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('rejection_reasons')->restrictOnDelete();
            $table->text('decision_comment')->nullable();
            $table->timestamps();

            $table->index(['commune_id', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
