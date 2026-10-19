<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit non modifiable (ADM-25, §10 Traçabilité).
 * Un trigger PostgreSQL interdit toute modification ou suppression ; chaque ligne
 * porte l'empreinte de la précédente (chaîne vérifiable par `php artisan audit:verify`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->timestampTz('occurred_at')->index();
            // Un compte présent dans l'audit ne se supprime pas : il se pseudonymise (§9.4).
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->foreignId('commune_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->jsonb('payload')->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);

            $table->index(['subject_type', 'subject_id']);
            $table->index('commune_id');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs est en ajout seul : % interdit', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();

            CREATE TRIGGER audit_logs_no_truncate
                BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_append_only()');
    }
};
