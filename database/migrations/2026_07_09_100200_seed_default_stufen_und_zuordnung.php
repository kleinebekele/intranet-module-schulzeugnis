<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legt die vier Standard-Schulstufen mit Türfarben an (nur, wenn noch keine
 * existieren) und ordnet vorhandene Klassen anhand ihrer Jahrgangszahl zu:
 *   1–4 Primarstufe · 5–7 Sekundarstufe I · 8–10 Sekundarstufe II · 11–13 Oberstufe.
 *
 * Die Zuordnung greift auf den Zahlenwert am Namensanfang zu ("5a" → 5), sie ist
 * eine Erst-Befüllung – danach ist die Stufe je Klasse frei änderbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Standard-Stufen nur anlegen, wenn die Tabelle leer ist.
        if (DB::table('zeugnis_stufen')->count() === 0) {
            $now = now();
            DB::table('zeugnis_stufen')->insert([
                ['name' => 'Primarstufe',      'farbe' => '#57a05a', 'reihenfolge' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Sekundarstufe I',  'farbe' => '#3f78a8', 'reihenfolge' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Sekundarstufe II', 'farbe' => '#c1873b', 'reihenfolge' => 3, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Oberstufe',        'farbe' => '#7a5aa6', 'reihenfolge' => 4, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // 2) IDs der (Standard-)Stufen nach Namen einsammeln.
        $stufen = DB::table('zeugnis_stufen')->pluck('id', 'name');

        $spanne = [
            'Primarstufe'      => range(1, 4),
            'Sekundarstufe I'  => range(5, 7),
            'Sekundarstufe II' => range(8, 10),
            'Oberstufe'        => range(11, 13),
        ];

        // 3) Jede noch nicht zugeordnete Klasse anhand ihrer Jahrgangszahl setzen.
        foreach (DB::table('zeugnis_klassen')->whereNull('stufe_id')->get() as $klasse) {
            $jahrgang = (int) $klasse->name; // "5a" → 5, "10" → 10

            foreach ($spanne as $stufenName => $jahrgaenge) {
                if (in_array($jahrgang, $jahrgaenge, true) && isset($stufen[$stufenName])) {
                    DB::table('zeugnis_klassen')
                        ->where('id', $klasse->id)
                        ->update(['stufe_id' => $stufen[$stufenName]]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        // Zuordnung lösen; die Stufen-Stammdaten bleiben erhalten.
        DB::table('zeugnis_klassen')->update(['stufe_id' => null]);
    }
};
