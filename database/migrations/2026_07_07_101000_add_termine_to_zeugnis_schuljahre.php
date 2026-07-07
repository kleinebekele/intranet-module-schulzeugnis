<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt das Schuljahr um zwei Termine:
 *  - ausgabe_datum: Tag der Zeugnisausgabe
 *  - eingabe_frist: bis wann müssen die Daten (Texte/Noten) eingepflegt sein
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_schuljahre', function (Blueprint $table) {
            $table->date('ausgabe_datum')->nullable()->after('end_date');
            $table->date('eingabe_frist')->nullable()->after('ausgabe_datum');
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_schuljahre', function (Blueprint $table) {
            $table->dropColumn(['ausgabe_datum', 'eingabe_frist']);
        });
    }
};
