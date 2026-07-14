<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskriminator 'art' am klassenweiten Text: 'fach' (bisheriges Verhalten) oder
 * 'spruch' (klassenweiter Zeugnisspruch). So kann der klassenweite Spruch die
 * bestehende Klassentext-Mechanik (Editor, Korrektoren, Status, Verlauf) mitnutzen,
 * ohne eigene Tabelle. Der bisherige Unique-Index (klasse_id, fach_id) wird um 'art'
 * erweitert – sonst kollidierte die Spruch-Zeile (fach_id = null) mit dem Haupttext.
 *
 * Reihenfolge des Index-Tauschs: erst den neuen Index anlegen, dann den alten droppen.
 * Unter MySQL stützt der alte Unique-Index als einziger den Fremdschlüssel auf
 * klasse_id (eigener ..._klasse_id_foreign existiert nicht) – ein Drop davor
 * scheitert an Fehler 1553. Der neue Index hat klasse_id ebenfalls links außen und
 * übernimmt diese Rolle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->string('art')->default('fach')->after('fach_id');
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->unique(['klasse_id', 'fach_id', 'art']);
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropUnique(['klasse_id', 'fach_id']);
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->unique(['klasse_id', 'fach_id']);
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropUnique(['klasse_id', 'fach_id', 'art']);
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropColumn('art');
        });
    }
};
