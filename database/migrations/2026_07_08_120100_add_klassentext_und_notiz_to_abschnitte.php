<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Je Abschnitt: soll der Schülertext nach dem klassenweiten Text auf einer
 * neuen Zeile beginnen (klassentext_neue_zeile)? Plus ein internes Notizfeld
 * (erscheint nicht auf dem Zeugnis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->boolean('klassentext_neue_zeile')->default(true);
            $table->text('notiz')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_abschnitte', function (Blueprint $table) {
            $table->dropColumn(['klassentext_neue_zeile', 'notiz']);
        });
    }
};
