<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klasse – Jahres-Klasse (z. B. "5a" im Schuljahr 2026/27), jedes Jahr neu.
 * Trägt das Standard-Zeugnisformat (vererbt an ihre Schüler) und den Klassenlehrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_klassen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schuljahr_id')->constrained('zeugnis_schuljahre')->cascadeOnDelete();
            $table->string('name');                       // z. B. "5a"
            // Standard-Zeugnisformat der Klasse (Vererbung an die Schüler):
            $table->foreignId('standard_format_id')->nullable()->constrained('zeugnis_formate')->nullOnDelete();
            // Klassenlehrer: loser Verweis auf zeugnis_schuljahr_lehrer (Tabelle folgt) –
            // kein FK, um Migrations-Reihenfolge/Zirkelbezug zu vermeiden.
            $table->unsignedBigInteger('klassenlehrer_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_klassen');
    }
};
