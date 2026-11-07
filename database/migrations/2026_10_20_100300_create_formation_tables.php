<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formation des candidats et quiz (MOB-05) : fiches courtes, questions à choix, tentatives notées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_modules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary');
            $table->text('body')->comment('Markdown');
            $table->string('icon', 30)->default('book');
            $table->string('video_url')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('prompt');
            $table->text('explanation');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quiz_choices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('score')->comment('Pourcentage de bonnes réponses');
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedTinyInteger('passing_score');
            $table->boolean('passed');
            $table->jsonb('answers');
            $table->timestamps();

            $table->index(['user_id', 'passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_choices');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('formation_modules');
    }
};
