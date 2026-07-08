<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwischengespeichertes Ergebnis der Überlauf-/Auto-Verkleinerungs-Analyse
 * (damit die Zeugnis-Tabelle nicht bei jedem Aufruf pro Schüler neu rechnen muss).
 * Wird bei Inhaltsänderungen neu gesetzt; null = noch nicht berechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnisse', function (Blueprint $table) {
            $table->string('ueberlauf_status')->nullable();          // leer | ok | verkleinert | ueberlauf
            $table->unsignedInteger('ueberlauf_passt_bei')->nullable(); // Schriftgröße, bei der es passt
        });
    }

    public function down(): void
    {
        Schema::table('zeugnisse', function (Blueprint $table) {
            $table->dropColumn(['ueberlauf_status', 'ueberlauf_passt_bei']);
        });
    }
};
