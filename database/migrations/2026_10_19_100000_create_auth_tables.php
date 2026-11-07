<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes de vérification SMS et appareils connectés (MOB-01, MOB-21, MOB-31).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->char('phone_hash', 64)->index();
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone_hash', 'consumed_at', 'expires_at']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('platform', 10);
            $table->string('app_version', 20)->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('otp_codes');
    }
};
