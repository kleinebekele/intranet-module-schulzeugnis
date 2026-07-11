<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mehrseitige Nicht-Broschüren-Formate: je Design-Seite eine Rolle
 * ('start' = erscheint genau einmal, 'folge' = wiederholt sich beliebig oft,
 * bis der Zeugnistext vollständig ausgegeben ist). null = eine Startseite
 * (bisheriges Verhalten). Bei Broschüren wird die Spalte ignoriert (fix 4 Seiten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->json('seiten')->nullable()->after('broschuere');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_formate', function (Blueprint $table) {
            $table->dropColumn('seiten');
        });
    }
};
