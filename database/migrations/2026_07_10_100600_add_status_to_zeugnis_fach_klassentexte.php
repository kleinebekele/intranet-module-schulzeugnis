<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klassenweite Texte (je Fach bzw. Hauptzeugnis, fach_id = null) bekommen einen eigenen
 * Bearbeitungsstatus – damit in der „Klassenweit"-Zeile der Zeugnis-Tabelle erkennbar ist,
 * welcher gemeinsame Text noch unbearbeitet bzw. unkorrigiert ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->string('status')->default('unbearbeitet')->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
