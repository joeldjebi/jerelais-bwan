<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pièces d'identité acceptées pour la vérification (MOB-01), gérées depuis la console (Référentiels).
 * Les cinq pièces d'origine gardent leur valeur : les vérifications existantes restent valides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug', 30)->unique()->comment('Valeur envoyée par l’application dans document_type');
            $table->string('name', 120);
            $table->boolean('two_sided')->default(true)->comment('Recto et verso, sinon une seule page');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $types = [
            ['cni', 'Carte nationale d’identité (CNI)', true],
            ['passeport', 'Passeport', false],
            ['permis', 'Permis de conduire', true],
            ['carte_consulaire', 'Carte consulaire', true],
            ['attestation_identite', 'Attestation d’identité', false],
        ];

        DB::table('identity_document_types')->insert(array_map(fn (array $type, int $index): array => [
            'uuid' => (string) Str::uuid7(),
            'slug' => $type[0],
            'name' => $type[1],
            'two_sided' => $type[2],
            'position' => $index + 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $types, array_keys($types)));
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_document_types');
    }
};
