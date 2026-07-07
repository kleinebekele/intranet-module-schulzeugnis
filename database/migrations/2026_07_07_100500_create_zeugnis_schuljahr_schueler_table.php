<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schüler je Schuljahr – modul-eigene Zeile pro Schuljahr (= zugleich die
 * "Einschulung": trägt schon klasse_id, daher keine separate Zwischentabelle).
 *
 * WICHTIG: KEINERLEI Verweis auf die Core-users-Tabelle. Schüler ≠ User – kein
 * Login, keine Verknüpfung, kein vorzeitiges Einsehen des eigenen Zeugnisses.
 *
 * quell_id = stabile Schüler-ID aus dem Quellsystem, loser Wert (kein FK). Sie
 * verbindet dieselbe Person über die Jahre; der Klartext-Name macht die Zeile
 * selbstständig, falls die Quelle wegfällt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_schuljahr_schueler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schuljahr_id')->constrained('zeugnis_schuljahre')->cascadeOnDelete();
            $table->foreignId('klasse_id')->nullable()->constrained('zeugnis_klassen')->nullOnDelete();
            $table->string('quell_id')->nullable()->index(); // stabile externe ID, loser Wert
            $table->string('vorname');
            $table->string('nachname');
            $table->date('geburtsdatum')->nullable();
            $table->string('geburtsort')->nullable();
            $table->string('geschlecht')->nullable();        // für Anrede/Textbausteine
            // Abweichendes Format nur für diesen Schüler/dieses Jahr (überschreibt Klassen-Standard):
            $table->foreignId('format_override_id')->nullable()->constrained('zeugnis_formate')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_schuljahr_schueler');
    }
};
