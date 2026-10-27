<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Fach kann „durch das Hauptzeugnis ersetzt" sein (z. B. der Unterricht, den das
 * Hauptzeugnis beschreibt): In Klassen mit Hauptzeugnis erscheint es dann weder als
 * eigene Spalte noch als Abschnitt im Fachzeugnis noch in den ToDos – das Hauptzeugnis
 * steht dafür nur EINMAL da. Bestehende Abschnitte bleiben unangetastet (nur ausgeblendet).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('zeugnis_faecher', 'durch_hauptzeugnis')) {
            return;
        }

        Schema::table('zeugnis_faecher', function (Blueprint $table) {
            $table->boolean('durch_hauptzeugnis')->default(false)->after('aktiv');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_faecher', function (Blueprint $table) {
            $table->dropColumn('durch_hauptzeugnis');
        });
    }
};
