<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korrektoren eines klassenweiten Textes – analog zu zeugnis_abschnitt_korrektoren.
 * Diese Lehrer dürfen den Klassentext korrigieren und seinen Status auf
 * „in Korrektur" / „Korrektur durchgeführt" setzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_klassentext_korrektoren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klassentext_id')->constrained('zeugnis_fach_klassentexte')->cascadeOnDelete();
            $table->foreignId('lehrer_id')->constrained('zeugnis_schuljahr_lehrer')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['klassentext_id', 'lehrer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_klassentext_korrektoren');
    }
};
