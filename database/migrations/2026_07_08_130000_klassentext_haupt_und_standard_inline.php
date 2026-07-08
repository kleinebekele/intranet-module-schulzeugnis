<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nachjustierung:
 *  - Standard für klassentext_neue_zeile = false (Text läuft hintereinander weg;
 *    neue Zeile nur bei Bedarf). Bestehende Zeilen entsprechend setzen.
 *  - fach_id im Klassentext nullbar, damit auch der Haupttext einen
 *    klassenweiten Text bekommen kann (fach_id = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->boolean('klassentext_neue_zeile')->default(false)->change();
        });
        DB::table('zeugnis_abschnitte')->update(['klassentext_neue_zeile' => false]);

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropForeign(['fach_id']);
        });
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->unsignedBigInteger('fach_id')->nullable()->change();
            $table->foreign('fach_id')->references('id')->on('zeugnis_faecher')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->boolean('klassentext_neue_zeile')->default(true)->change();
        });
    }
};
