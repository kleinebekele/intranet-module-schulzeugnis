<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abschnitt – die einzelnen Bausteine eines Zeugnisses. Bewusst gleichartig mit
 * einem 'typ' modelliert (haupttext | fachtext | note), damit neue Abschnittsarten
 * später ohne Schema-Umbau dazukommen können.
 *
 * autor_lehrer_id ist ein LOSER Verweis (kein FK) + autor_name als eingefrorener
 * Klartext: So bleibt "wer hat das geschrieben" erhalten, auch wenn der Lehrer-
 * Datensatz irgendwann verschwindet. status je Abschnitt ('offen'|'fertig')
 * erlaubt später die Übersicht "welche Fachtexte fehlen noch".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_abschnitte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zeugnis_id')->constrained('zeugnisse')->cascadeOnDelete();
            $table->string('typ');                        // 'haupttext' | 'fachtext' | 'note'
            $table->foreignId('fach_id')->nullable()->constrained('zeugnis_faecher')->nullOnDelete();
            $table->unsignedBigInteger('autor_lehrer_id')->nullable()->index(); // loser Verweis
            $table->string('autor_name')->nullable();     // eingefrorener Autor-Klartext
            $table->longText('inhalt')->nullable();        // Freitext (Haupt-/Fachtext)
            $table->string('note')->nullable();            // Wert für Abschlusszeugnisse (Format später)
            $table->string('status')->default('offen');    // 'offen' | 'fertig' (pro Abschnitt)
            $table->integer('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_abschnitte');
    }
};
