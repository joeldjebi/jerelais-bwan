<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces du dossier (MOB-03) : stockées sur le disque privé « identity », jamais avec les médias (§9.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('candidature_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64)->index()->comment('Détection des doublons de pièces (COO-03)');
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->unique(['candidature_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_documents');
    }
};
