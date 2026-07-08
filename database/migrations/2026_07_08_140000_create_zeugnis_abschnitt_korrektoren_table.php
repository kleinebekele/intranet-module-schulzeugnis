<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung: welche Lehrer dürfen einen bestimmten Abschnitt korrigieren.
 * Wird beim Setzen eines Korrektur-Status gepflegt. Nur diese Lehrer dürfen
 * den Status danach auf „in Korrektur" / „Korrektur durchgeführt" setzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_abschnitt_korrektoren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abschnitt_id')->constrained('zeugnis_abschnitte')->cascadeOnDelete();
            $table->foreignId('lehrer_id')->constrained('zeugnis_schuljahr_lehrer')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['abschnitt_id', 'lehrer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_abschnitt_korrektoren');
    }
};
