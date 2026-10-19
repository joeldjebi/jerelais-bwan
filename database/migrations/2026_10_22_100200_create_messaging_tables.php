<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie entre les Relais et leurs coordonnateurs (MOB-22, COO-20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('relais_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->constrained()->restrictOnDelete();
            $table->foreignId('relais_item_id')->nullable()->comment('Conversation rattachée à un relais')->constrained()->restrictOnDelete();
            $table->timestamp('last_message_at', 6)->nullable();
            $table->timestamp('relais_read_at', 6)->nullable();
            $table->timestamp('staff_read_at', 6)->nullable();
            $table->timestamp('relais_notified_at')->nullable()->comment('Dernier SMS de rappel envoyé au Relais');
            $table->timestamps();

            $table->unique(['relais_profile_id', 'relais_item_id']);
            $table->index(['commune_id', 'last_message_at']);
        });

        // Une seule conversation générale par Relais (les NULL ne sont pas comparés par l'index unique).
        DB::statement('create unique index conversations_general_unique on conversations (relais_profile_id) where relais_item_id is null');

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->boolean('from_staff');
            $table->text('body');
            $table->uuid('broadcast_uuid')->nullable()->index()->comment('Message groupé à tous les Relais d’une commune');
            $table->timestamp('created_at', 6);

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
