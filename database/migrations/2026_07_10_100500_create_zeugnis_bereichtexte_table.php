<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Hauptzeugnis verhält sich wie EIN Fach: genau ein Abschnitt (typ = 'hauptzeugnis')
 * je Schüler trägt Status, Korrektoren und den einen Klassentext. Seine mehreren
 * Schülertexte – einer je Fachbereich – hängen als Kind-Zeilen hier.
 * bereich_name ist der eingefrorene Klartext-Fallback (Anzeige nutzt bevorzugt den
 * lebenden Bereich), damit die Zeile selbstständig bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_bereichtexte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abschnitt_id')->constrained('zeugnis_abschnitte')->cascadeOnDelete();
            $table->foreignId('bereich_id')->nullable()->constrained('zeugnis_hauptbereiche')->nullOnDelete();
            $table->string('bereich_name')->nullable();
            $table->longText('inhalt')->nullable();
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_bereichtexte');
    }
};
