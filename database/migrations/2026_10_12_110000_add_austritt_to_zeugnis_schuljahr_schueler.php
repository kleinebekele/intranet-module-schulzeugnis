<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Austritt eines Schülers (aus Linear bzw. von Hand) und die daraus folgende Sperre:
 * Tritt ein Kind vor dem Schuljahresende aus, bekommt es kein Zeugnis – die Sperre
 * ist von Hand aufhebbar. Austritt zum Schuljahresende sperrt nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zeugnis_schuljahr_schueler', function (Blueprint $table) {
            if (! Schema::hasColumn('zeugnis_schuljahr_schueler', 'austritt_am')) {
                $table->date('austritt_am')->nullable()->after('geschlecht');
            }
            if (! Schema::hasColumn('zeugnis_schuljahr_schueler', 'zeugnis_gesperrt')) {
                $table->boolean('zeugnis_gesperrt')->default(false)->after('austritt_am');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zeugnis_schuljahr_schueler', function (Blueprint $table) {
            $table->dropColumn(['austritt_am', 'zeugnis_gesperrt']);
        });
    }
};
