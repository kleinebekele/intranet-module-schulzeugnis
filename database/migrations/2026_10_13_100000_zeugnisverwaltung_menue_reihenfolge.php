<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Neue Reihenfolge der Menüpunkte unter „Zeugnisverwaltung":
 * Schuljahre, Schulstufen, Klassen, Fächer, Lehrer, Schüler, Zeugnissprüche,
 * Zeugnisformate, Stammdaten-Import.
 *
 * Der Core übernimmt die Manifest-Reihenfolge nur beim Anlegen eines Menüpunkts
 * (danach gehört sie der Modulverwaltung). Deshalb hier einmalig umsortieren –
 * innerhalb der Positionen, die die Gruppe bisher belegt, damit Punkte außerhalb
 * der Gruppe (Klassenräume, ToDos, Alte Zeugnisse) ihren Platz behalten.
 */
return new class extends Migration
{
    private const REIHENFOLGE = ['schuljahre', 'stufen', 'klassen', 'faecher', 'lehrer', 'schueler', 'sprueche', 'formate', 'import'];

    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_menu_items')) {
            return;
        }
        $modulId = DB::table('modules')->where('key', 'schulzeugnis')->value('id');
        if (! $modulId) {
            return; // Modul noch nicht synchronisiert – das Manifest gibt die Reihenfolge dann vor.
        }

        $punkte = DB::table('module_menu_items')->where('module_id', $modulId)
            ->whereIn('key', self::REIHENFOLGE)->get(['id', 'key', 'position']);
        $plaetze = $punkte->pluck('position')->map(fn ($p) => (int) $p)->sort()->values();
        $nachKey = $punkte->keyBy('key');

        $i = 0;
        foreach (self::REIHENFOLGE as $key) {
            if (isset($nachKey[$key])) {
                DB::table('module_menu_items')->where('id', $nachKey[$key]->id)->update(['position' => $plaetze[$i++]]);
            }
        }
    }

    public function down(): void
    {
        // Reihenfolge ist Darstellung – kein Zurück nötig.
    }
};
