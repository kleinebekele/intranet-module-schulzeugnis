<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klassen-Schalter: bekommt diese Klasse einen Zeugnisspruch? Analog zu
 * hat_fachzeugnis / hat_hauptzeugnis. Steuert die Auto-Anlage der Schüler-Sprüche
 * und die Spruch-Spalte in der Zeugnisübersicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->boolean('hat_zeugnisspruch')->default(false)->after('hat_hauptzeugnis');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_klassen', function (Blueprint $table) {
            $table->dropColumn('hat_zeugnisspruch');
        });
    }
};
