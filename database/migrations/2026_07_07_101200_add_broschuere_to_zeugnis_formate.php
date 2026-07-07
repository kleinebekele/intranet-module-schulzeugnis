<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broschüren-Modus: gefaltete DIN-A3-Broschüre = 4 A4-Seiten auf einem Falzbogen.
 * Ist er aktiv, sind die Seiten A4; seitenformat/ausrichtung werden ignoriert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->boolean('broschuere')->default(false)->after('ausrichtung');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->dropColumn('broschuere');
        });
    }
};
