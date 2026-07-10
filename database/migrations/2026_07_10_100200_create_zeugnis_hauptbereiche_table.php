<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fachbereiche eines Hauptzeugnisses – frei je Klasse definiert (z. B. "Allgemein",
 * "Rechnen", "Schreiben und Lesen"). Die Reihenfolge bestimmt die Abfolge im Zeugnis.
 * "Allgemein" wird beim Einschalten des Hauptzeugnisses automatisch angelegt und ist
 * nicht löschbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_hauptbereiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klasse_id')->constrained('zeugnis_klassen')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_hauptbereiche');
    }
};
