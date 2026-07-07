<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lehrauftrag – wer unterrichtet welches Fach in welcher Klasse.
 * klasse_id trägt schon das Schuljahr, daher kein extra schuljahr_id.
 * Mehrere Zeilen je Fach/Klasse = Team-Teaching (mehrere Fachlehrer) möglich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_lehrauftraege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klasse_id')->constrained('zeugnis_klassen')->cascadeOnDelete();
            $table->foreignId('fach_id')->constrained('zeugnis_faecher')->cascadeOnDelete();
            $table->foreignId('lehrer_id')->constrained('zeugnis_schuljahr_lehrer')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_lehrauftraege');
    }
};
