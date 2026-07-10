<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Abschnitt eines Hauptzeugnisses (typ = 'fachbereich') verweist auf den
 * Fachbereich der Klasse. Loser Bezug per FK mit nullOnDelete – wird ein Fachbereich
 * entfernt, bleibt ein bereits geschriebener Schülertext erhalten (bereich_id = null).
 * bereich_name hält die Überschrift als Klartext-Fallback, damit die Zeile
 * selbstständig bleibt (Anzeige nutzt bevorzugt den lebenden Bereich, sonst diesen Wert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->foreignId('bereich_id')
                ->nullable()
                ->after('fach_id')
                ->constrained('zeugnis_hauptbereiche')
                ->nullOnDelete();
            $table->string('bereich_name')->nullable()->after('bereich_id');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bereich_id');
            $table->dropColumn('bereich_name');
        });
    }
};
