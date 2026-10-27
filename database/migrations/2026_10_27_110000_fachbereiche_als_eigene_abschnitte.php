<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hauptzeugnis: jeder Fachbereich wird ein eigener Baustein wie ein Fach – je Schüler
 * ein Abschnitt (typ = fachbereich, eigener Status/Korrektoren) und je Klasse ein
 * klassenweiter Text (zeugnis_fach_klassentexte.bereich_id).
 *
 * Übernahme der bisherigen Daten (nichts wird gelöscht):
 *  - Bereichtexte des einen HAU-Abschnitts → je Bereich ein Fachbereich-Abschnitt
 *    (Status/Korrektoren vom HAU-Abschnitt, sofern der Bereich Text hat).
 *    HAU-Abschnitt und Bereichtexte bleiben als Altbestand liegen.
 *  - zeugnis_hauptbereiche.klassentext → Klassentext-Zeile mit bereich_id.
 *
 * bereich_id ist ein loser Verweis ohne FK: ein entfernter Bereich darf seinen
 * Klassentext nicht löschen und nicht zum Haupttext (bereich_id null) machen.
 * Idempotent – ein zweiter Lauf legt nichts doppelt an.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('zeugnis_fach_klassentexte', 'bereich_id')) {
            Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
                $table->unsignedBigInteger('bereich_id')->nullable()->after('fach_id')->index();
            });
        }

        $jetzt = now();

        // 1) Klassentexte je Fachbereich.
        foreach (DB::table('zeugnis_hauptbereiche')->whereNotNull('klassentext')->get() as $b) {
            if (trim(strip_tags((string) $b->klassentext)) === '') {
                continue;
            }
            $da = DB::table('zeugnis_fach_klassentexte')
                ->where('klasse_id', $b->klasse_id)->where('bereich_id', $b->id)->exists();
            if (! $da) {
                DB::table('zeugnis_fach_klassentexte')->insert([
                    'klasse_id'  => $b->klasse_id,
                    'fach_id'    => null,
                    'bereich_id' => $b->id,
                    'art'        => 'fach',
                    'text'       => $b->klassentext,
                    'status'     => 'in_arbeit',
                    'created_at' => $jetzt,
                    'updated_at' => $jetzt,
                ]);
            }
        }

        // 2) Schülertexte je Fachbereich als eigene Abschnitte.
        $hauAbschnitte = DB::table('zeugnis_abschnitte')->where('typ', 'hauptzeugnis')->get();
        foreach ($hauAbschnitte as $hau) {
            $korrektoren = DB::table('zeugnis_abschnitt_korrektoren')->where('abschnitt_id', $hau->id)->pluck('lehrer_id');
            $bereichtexte = DB::table('zeugnis_bereichtexte')->where('abschnitt_id', $hau->id)->orderBy('reihenfolge')->orderBy('id')->get();

            foreach ($bereichtexte as $bt) {
                $vorhanden = DB::table('zeugnis_abschnitte')
                    ->where('zeugnis_id', $hau->zeugnis_id)
                    ->where('typ', 'fachbereich')
                    ->when($bt->bereich_id, fn ($q) => $q->where('bereich_id', $bt->bereich_id),
                        fn ($q) => $q->whereNull('bereich_id')->where('bereich_name', $bt->bereich_name))
                    ->exists();
                if ($vorhanden) {
                    continue;
                }

                $gefuellt = trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags((string) $bt->inhalt), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== '';

                $id = DB::table('zeugnis_abschnitte')->insertGetId([
                    'zeugnis_id'             => $hau->zeugnis_id,
                    'typ'                    => 'fachbereich',
                    'fach_id'                => null,
                    'bereich_id'             => $bt->bereich_id,
                    'bereich_name'           => $bt->bereich_name,
                    'autor_lehrer_id'        => $hau->autor_lehrer_id,
                    'autor_name'             => $hau->autor_name,
                    'inhalt'                 => $bt->inhalt,
                    'reihenfolge'            => $bt->reihenfolge,
                    'status'                 => $gefuellt ? $hau->status : 'unbearbeitet',
                    'klassentext_neue_zeile' => $hau->klassentext_neue_zeile ?? false,
                    'created_at'             => $jetzt,
                    'updated_at'             => $jetzt,
                ]);

                if ($gefuellt) {
                    foreach ($korrektoren as $lehrerId) {
                        DB::table('zeugnis_abschnitt_korrektoren')->insert([
                            'abschnitt_id' => $id,
                            'lehrer_id'    => $lehrerId,
                            'created_at'   => $jetzt,
                            'updated_at'   => $jetzt,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Übernommene Daten bleiben – nur die Spalte wird entfernt.
        if (Schema::hasColumn('zeugnis_fach_klassentexte', 'bereich_id')) {
            Schema::table('zeugnis_fach_klassentexte', function (Blueprint $table) {
                $table->dropIndex(['bereich_id']);
                $table->dropColumn('bereich_id');
            });
        }
    }
};
