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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->string('art')->default('fach')->after('fach_id');
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropUnique(['klasse_id', 'fach_id']);
            $table->unique(['klasse_id', 'fach_id', 'art']);
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropUnique(['klasse_id', 'fach_id', 'art']);
            $table->unique(['klasse_id', 'fach_id']);
        });

        Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
            $table->dropColumn('art');
        });
    }
};
