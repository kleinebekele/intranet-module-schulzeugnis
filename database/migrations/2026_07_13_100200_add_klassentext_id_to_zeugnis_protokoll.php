<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll: loser Bezug auf einen klassenweiten Text, damit dessen Editor einen
 * eigenen Änderungsverlauf (Text/Notiz/Status) mit Vergleich/Wiederherstellen
 * zeigen kann – wie beim Abschnitt. Kein FK (append-only, [[Protokoll]] überlebt
 * Löschungen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_protokoll', function (Blueprint $table) {
            $table->unsignedBigInteger('klassentext_id')->nullable()->after('abschnitt_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_protokoll', function (Blueprint $table) {
            $table->dropColumn('klassentext_id');
        });
    }
};
