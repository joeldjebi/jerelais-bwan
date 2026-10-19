<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Médias d'un relais (MOB-11, MOB-12, MOB-16) et leurs versions dérivées (vignettes sans EXIF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('relais_item_id')->constrained()->restrictOnDelete();
            $table->uuid('client_uuid')->comment('Idempotence de l’envoi côté application');
            $table->string('kind', 10)->comment('photo | video | audio');
            $table->string('status', 20)->default('pending');
            $table->string('original_name', 200)->nullable();
            $table->string('declared_mime', 100);
            $table->string('mime', 100)->nullable()->comment('Type lu dans le contenu');
            $table->unsignedBigInteger('size');
            $table->char('declared_sha256', 64);
            $table->char('sha256', 64)->nullable()->index();
            $table->char('phash', 16)->nullable()->index()->comment('Empreinte perceptuelle (COO-13)');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('upload_id')->nullable()->comment('Envoi multipart S3 en cours');
            $table->unsignedInteger('part_size');
            $table->boolean('is_imported')->default(false)->comment('Importé de la galerie (MOB-12)');
            $table->timestamp('device_captured_at')->nullable();
            $table->timestamp('captured_at')->nullable()->comment('Date de prise de vue lue dans le fichier');
            $table->decimal('duration_s', 8, 2)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->jsonb('metadata')->nullable()->comment('Métadonnées utiles (appareil, orientation, GPS du fichier)');
            $table->string('rejection', 40)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['relais_item_id', 'client_uuid']);
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('medias')->cascadeOnDelete();
            $table->string('kind', 20)->comment('thumbnail | preview');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('medias');
    }
};
