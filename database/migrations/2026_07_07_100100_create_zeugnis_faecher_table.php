<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fach – feste, jahresübergreifende Stammdatenliste (Deutsch, Eurythmie, …).
 * Nicht mehr unterrichtete Fächer werden archiviert (aktiv = false), nie gelöscht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_faecher', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kuerzel')->nullable();
            $table->integer('reihenfolge')->default(0);   // Sortierung auf dem Zeugnis
            $table->boolean('aktiv')->default(true);      // archivieren statt löschen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_faecher');
    }
};
