<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert das Zeugnisformat um das Seiten-Setup und das freie Layout:
 *  - seitenformat: a4 | a3
 *  - ausrichtung:  hoch | quer
 *  - layout:       JSON-Liste der frei positionierten Elemente (mm)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->string('seitenformat', 4)->default('a4')->after('typ');
            $table->string('ausrichtung', 8)->default('hoch')->after('seitenformat');
            $table->json('layout')->nullable()->after('ausrichtung');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->dropColumn(['seitenformat', 'ausrichtung', 'layout']);
        });
    }
};
