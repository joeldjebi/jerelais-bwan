<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérification d'identité des Relais inscrits depuis l'application (MOB-01) : commune, pièce d'identité,
 * selfie et photo de profil, vérifiés par l'administration. Condition pour être éligible aux gains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('localite_id')->nullable()->constrained('localites')->nullOnDelete();
            $table->string('document_type', 30)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('identity_files', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('identity_verification_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->comment('recto, verso, page, selfie, profile_photo');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64)->index();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->unique(['identity_verification_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_files');
        Schema::dropIfExists('identity_verifications');
    }
};
