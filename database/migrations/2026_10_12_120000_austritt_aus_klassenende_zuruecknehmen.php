<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reparatur zu v1.6.0: Der Linear-Import hat `WD_Schl.AustrittKl` als Austritt gelesen.
 * Das ist aber das Ende der Klassenzugehörigkeit und steht bei JEDEM Kind auf dem
 * letzten Schultag – dadurch galten ganze Jahrgänge als ausgetreten und (bei einem
 * Schuljahresende danach) als für das Zeugnis gesperrt.
 *
 * Zurückgenommen wird nur genau dieses Massendatum: je Schuljahr das häufigste
 * Austrittsdatum, sofern es mindestens die Hälfte der Schüler (und mindestens fünf)
 * betrifft. Einzelne, von Hand eingetragene Austritte bleiben unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jahre = DB::table('zeugnis_schuljahr_schueler')->select('schuljahr_id')->distinct()->pluck('schuljahr_id');

        foreach ($jahre as $sjId) {
            $gesamt = DB::table('zeugnis_schuljahr_schueler')->where('schuljahr_id', $sjId)->count();
            $haeufigst = DB::table('zeugnis_schuljahr_schueler')
                ->where('schuljahr_id', $sjId)
                ->whereNotNull('austritt_am')
                ->select('austritt_am', DB::raw('COUNT(*) as n'))
                ->groupBy('austritt_am')
                ->orderByDesc('n')
                ->first();

            if (! $haeufigst || $haeufigst->n < 5 || $haeufigst->n * 2 < $gesamt) {
                continue;
            }

            DB::table('zeugnis_schuljahr_schueler')
                ->where('schuljahr_id', $sjId)
                ->where('austritt_am', $haeufigst->austritt_am)
                ->update(['austritt_am' => null, 'zeugnis_gesperrt' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nicht umkehrbar – die zurückgenommenen Werte waren falsch.
    }
};
