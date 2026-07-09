<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe – Schulstufe (z. B. Primarstufe, Sekundarstufe I …), jahresübergreifende
 * Stammdaten. Jede Stufe trägt eine eigene Türfarbe für die Klassenräume-Ansicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_stufen', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // z. B. "Sekundarstufe I"
            $table->string('farbe', 7)->default('#6b7280'); // Türfarbe als Hex (#rrggbb)
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_stufen');
    }
};
