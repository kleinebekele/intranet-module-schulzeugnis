<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeder Fachbereich eines Hauptzeugnisses trägt einen klassenweiten Text (gilt für
 * alle Schüler der Klasse, steht vor dem individuellen Text) – analog zum Klassentext
 * je Fach. Weil der Fachbereich ohnehin klassenspezifisch ist, liegt der Text direkt
 * an der Bereichs-Zeile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_hauptbereiche', function (Blueprint $table) {
            $table->longText('klassentext')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_hauptbereiche', function (Blueprint $table) {
            $table->dropColumn('klassentext');
        });
    }
};
