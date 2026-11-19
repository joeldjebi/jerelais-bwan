<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Annonces de l'administration aux Relais (MOB-21) : notification push (SMS de secours facultatif), immédiate ou
 * programmée, à tout le réseau ou à une partie ; chaque destinataire la retrouve dans l'application.
 * Ajoute la permission « notifications.send ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title', 80);
            $table->string('body', 500);
            $table->string('screen', 30)->default('home')->comment('Écran ouvert par l’application');
            $table->string('audience', 30)->comment('all, regions, communes, relais, identity_pending, payout_missing');
            $table->json('audience_ids')->nullable()->comment('Régions, communes ou compte visés (uuid)');
            $table->boolean('with_sms')->default(false)->comment('SMS aux Relais sans application joignable');
            $table->string('status', 20)->default('scheduled')->index()->comment('scheduled, sending, sent, cancelled');
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('push_count')->default(0);
            $table->unsignedInteger('sms_count')->default(0);
            $table->unsignedInteger('unreached_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10)->nullable()->comment('push, sms ou null (non joint)');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcements');
    }
};
