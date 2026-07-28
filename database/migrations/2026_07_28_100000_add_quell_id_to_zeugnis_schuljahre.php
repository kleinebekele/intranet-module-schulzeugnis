<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinterlegt die stabile externe ID (aus dem Quellsystem Linear) am Schuljahr.
 *
 * Zweck: Der automatische Abgleich (Ekkon-Task Linear/ZeugnisImport) erkennt
 * Schuljahre über diese ID wieder – in Linear ist das `WD_Zeitraum.ID`
 * (z. B. 28 = Schuljahr 2025/2026). Der Name bleibt frei pflegbar, ohne dass
 * ein erneuter Import ein Duplikat anlegt.
 *
 * Loser Wert – kein FK, index für den Abgleich. Konsistent zur quell_id bei
 * Lehrern und Schülern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_schuljahre', function (Blueprint $table) {
            $table->string('quell_id')->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_schuljahre', function (Blueprint $table) {
            $table->dropIndex(['quell_id']);
            $table->dropColumn('quell_id');
        });
    }
};
