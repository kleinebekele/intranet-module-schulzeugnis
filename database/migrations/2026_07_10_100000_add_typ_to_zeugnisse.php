<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Schüler kann zwei Zeugnisse haben: ein Hauptzeugnis (aus Fachbereichen) und
 * ein Fachzeugnis (aus Fächern). Der typ unterscheidet sie. Bestehende Zeugnisse
 * gelten zunächst als Fachzeugnis (Standard), bis sie neu erzeugt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnisse', function (Blueprint $table) {
            $table->string('typ')->default('fach')->after('schueler_id');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnisse', function (Blueprint $table) {
            $table->dropColumn('typ');
        });
    }
};
