<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinterlegt die stabile externe ID (aus dem Quellsystem Linear) am Lehrer-Datensatz.
 *
 * Zweck: Lehrer werden über diese ID mit dem Intranet-Konto verknüpft – die Core-
 * `users`-Tabelle führt dieselbe ID in `externe_id`. Das ist stabiler als die E-Mail
 * (die sich ändern kann). Lehrer, die vor Kontoerstellung importiert werden, tragen
 * die quell_id und werden vom täglichen Abgleich später darüber verknüpft.
 *
 * Loser Wert – kein FK, index für den Abgleich. Konsistent zur quell_id bei Schülern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_schuljahr_lehrer', function (Blueprint $table) {
            $table->string('quell_id')->nullable()->after('core_user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_schuljahr_lehrer', function (Blueprint $table) {
            $table->dropIndex(['quell_id']);
            $table->dropColumn('quell_id');
        });
    }
};
