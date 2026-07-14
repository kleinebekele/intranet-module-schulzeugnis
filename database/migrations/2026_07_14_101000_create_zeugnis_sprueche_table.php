<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog der Zeugnissprüche – jahresübergreifende, gepflegte Liste von Versen/
 * Sprüchen. Der Klassenlehrer wählt daraus einen Spruch je Schüler aus und kann ihn
 * danach frei bearbeiten (der ausgewählte Text wird in den Schüler-Spruch kopiert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zeugnis_sprueche', function (Blueprint $table) {
            $table->id();
            $table->string('titel')->nullable();   // optionale Bezeichnung/Herkunft
            $table->longText('text');              // der Spruch selbst
            $table->unsignedInteger('reihenfolge')->default(0);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zeugnis_sprueche');
    }
};
