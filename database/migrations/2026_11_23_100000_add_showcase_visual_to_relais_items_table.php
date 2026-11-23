<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visuel public d'un relais sur la vitrine (WEB-05, ADM-17) : aucun, image d'origine, image floutée automatiquement,
 * ou version floutée déposée par la rédaction (photo ou vidéo). Les originaux restent privés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->string('showcase_visual', 20)->default('none')->after('showcase_with_image')->comment('none, original, blurred, edited');
            $table->string('showcase_blurred_path')->nullable()->after('showcase_visual')->comment('Vignette floutée automatiquement (disque medias)');
            $table->string('showcase_media_path')->nullable()->after('showcase_blurred_path')->comment('Version floutée déposée par la rédaction');
            $table->string('showcase_media_mime', 100)->nullable()->after('showcase_media_path');
            $table->string('showcase_poster_path')->nullable()->after('showcase_media_mime')->comment('Image publique de la version déposée');
        });

        DB::table('relais_items')->where('showcase_with_image', true)->update(['showcase_visual' => 'original']);
    }

    public function down(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->dropColumn(['showcase_visual', 'showcase_blurred_path', 'showcase_media_path', 'showcase_media_mime', 'showcase_poster_path']);
        });
    }
};
