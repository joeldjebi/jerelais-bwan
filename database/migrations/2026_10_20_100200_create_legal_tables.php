<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textes juridiques versionnés et acceptations horodatées (MOB-04, WEB-07, ADM-23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 30);
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('body')->comment('Markdown, HTML retiré au rendu');
            $table->timestamp('published_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('channel', 10);
            $table->ipAddress()->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'legal_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('legal_documents');
    }
};
