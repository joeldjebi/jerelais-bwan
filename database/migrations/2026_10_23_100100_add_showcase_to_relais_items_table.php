<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine « Vu sur REFLET TV » (WEB-05, ADM-17) : relais diffusés publiés par la rédaction, avec un texte public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->timestamp('showcased_at')->nullable()->after('broadcast_url')->index();
            $table->string('showcase_title', 150)->nullable()->after('showcased_at');
            $table->text('showcase_text')->nullable()->after('showcase_title');
            $table->boolean('showcase_with_image')->default(false)->after('showcase_text');
        });
    }

    public function down(): void
    {
        Schema::table('relais_items', function (Blueprint $table) {
            $table->dropColumn(['showcased_at', 'showcase_title', 'showcase_text', 'showcase_with_image']);
        });
    }
};
