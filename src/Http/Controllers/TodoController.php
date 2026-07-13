<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Support\Collection;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Klassentext;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;

/**
 * „Meine ToDos" – der offene Aufgabenüberblick einer Lehrkraft, gruppiert nach
 * Klasse und Fach:
 *   1. Eigene Zeugnistexte, die noch nicht vollständig sind (nur Fächer/Klassen,
 *      in denen die Lehrkraft tatsächlich einen Lehrauftrag bzw. die Klassen-
 *      leitung hat).
 *   2. Texte, um deren Korrektur die Lehrkraft gebeten wurde und die noch offen sind.
 *
 * Bewusst OHNE Admin-Sonderrolle: Ein Administrator hat nur Einsicht und bekommt
 * hier absichtlich keine ToDos – auch dann nicht, wenn er (z. B. aus Testgründen)
 * selbst als Lehrer hinterlegt ist.
 */
class TodoController
{
    /** Offene Korrektur-Stände: dann ist der Korrektor tatsächlich gefragt. */
    private const KORREKTUR_OFFEN = ['frei_zur_korrektur', 'in_korrektur', 'korrektur_noetig'];

    public function index()
    {
        $user      = auth()->user();
        $schuljahr = Schuljahr::where('is_active', true)->first();
        $istAdmin  = (bool) $user?->is_admin;

        // Gruppierungsrichtung: 'klasse' (Klasse → Fach, Standard) oder 'fach' (Fach → Klasse).
        $modus = request('gruppierung') === 'fach' ? 'fach' : 'klasse';

        $leer = [
            'schuljahr'             => $schuljahr,
            'istAdmin'              => $istAdmin,
            'modus'                 => $modus,
            'meineTexteGruppen'     => [],
            'korrigierteGruppen'    => [],
            'zuKorrigierenGruppen'  => [],
            'zuKorrigierenKlassentexte' => [],
            'meineTexteAnzahl'      => 0,
            'erledigtAnzahl'        => 0,
            'korrigierteAnzahl'     => 0,
            'zuKorrigierenAnzahl'   => 0,
            'stati'                 => Abschnitt::STATI,
        ];

        // Admin: nur Einsicht → keine ToDos. Ebenso ohne aktives Schuljahr.
        if ($istAdmin || ! $schuljahr) {
            return view('schulzeugnis::todo.index', $leer);
        }

        $meineLehrerIds = Lehrer::where('schuljahr_id', $schuljahr->id)
            ->where('core_user_id', $user->id)
            ->pluck('id');

        if ($meineLehrerIds->isEmpty()) {
            return view('schulzeugnis::todo.index', $leer);
        }

        // Verantwortungsbereich: Klassen mit Klassenleitung + je Lehrauftrag (Klasse × Fach).
        $klassenAlsKL = Klasse::where('schuljahr_id', $schuljahr->id)
            ->whereIn('klassenlehrer_id', $meineLehrerIds)
            ->pluck('id');

        $faecherJeKlasse = Lehrauftrag::whereIn('lehrer_id', $meineLehrerIds)
            ->whereHas('klasse', fn ($q) => $q->where('schuljahr_id', $schuljahr->id))
            ->get(['klasse_id', 'fach_id'])
            ->groupBy('klasse_id')
            ->map(fn ($g) => $g->pluck('fach_id')->unique()->all());

        $relevanteKlassen = $klassenAlsKL->merge($faecherJeKlasse->keys())->unique();

        // 1) Alle eigenen Abschnitte (Zeugnis nicht abgeschlossen) – inkl. erledigte,
        //    damit man sie in „Meine Zeugnistexte" bei Bedarf wieder anfassen kann.
        $eigene = collect();
        if ($relevanteKlassen->isNotEmpty()) {
            $eigene = Abschnitt::whereHas('zeugnis', fn ($q) => $q
                    ->where('status', '!=', Zeugnis::STATUS_ABGESCHLOSSEN)
                    ->whereHas('schueler', fn ($s) => $s->whereIn('klasse_id', $relevanteKlassen)))
                ->with(['fach', 'zeugnis.schueler.klasse.stufe'])
                ->get()
                ->filter(function (Abschnitt $a) use ($klassenAlsKL, $faecherJeKlasse) {
                    $klasseId = $a->zeugnis?->schueler?->klasse_id;
                    if (! $klasseId) {
                        return false;
                    }
                    if ($a->typ === Abschnitt::TYP_HAUPTTEXT) {
                        return $klassenAlsKL->contains($klasseId);
                    }

                    return in_array($a->fach_id, $faecherJeKlasse[$klasseId] ?? [], true);
                });
        }

        // 2) Offene Korrektur-Anfragen an mich.
        $korrektur = Abschnitt::whereIn('status', self::KORREKTUR_OFFEN)
            ->whereHas('korrektoren', fn ($q) => $q->whereIn('zeugnis_schuljahr_lehrer.id', $meineLehrerIds))
            ->whereHas('zeugnis', fn ($q) => $q
                ->where('status', '!=', Zeugnis::STATUS_ABGESCHLOSSEN)
                ->whereHas('schueler.klasse', fn ($k) => $k->where('schuljahr_id', $schuljahr->id)))
            ->with(['fach', 'zeugnis.schueler.klasse.stufe'])
            ->get();

        // 2b) Offene Korrektur-Anfragen an mich bei KLASSENWEITEN Texten (klassen-, nicht
        //     schülerbezogen) – separat, da sie keine Schüler-Zeile haben.
        $ktKorrektur = Klassentext::whereIn('status', self::KORREKTUR_OFFEN)
            ->whereHas('korrektoren', fn ($q) => $q->whereIn('zeugnis_schuljahr_lehrer.id', $meineLehrerIds))
            ->whereHas('klasse', fn ($k) => $k->where('schuljahr_id', $schuljahr->id))
            ->with(['klasse.stufe', 'fach'])
            ->get();

        $ktGruppen = $ktKorrektur
            ->groupBy(fn (Klassentext $kt) => $kt->klasse_id)
            ->map(function (Collection $g) {
                $klasse = $g->first()->klasse;

                return [
                    'label' => 'Klasse ' . ($klasse->name ?? '—'),
                    'sort'  => $klasse->name ?? '',
                    'farbe' => $klasse->stufe?->farbe ?: '#64748b',
                    'sub'   => $klasse->stufe?->name,
                    'items' => $g
                        ->sortBy(fn (Klassentext $kt) => $kt->fach_id === null ? -1 : ($kt->fach?->reihenfolge ?? 999))
                        ->map(fn (Klassentext $kt) => [
                            'url'    => route('module.schulzeugnis.klassenraeume.klassentexte.edit', [
                                'klasse' => $kt->klasse_id,
                                'fach'   => $kt->fach_id === null ? 'haupt' : $kt->fach_id,
                            ]),
                            'fach'   => $kt->fach?->name ?? 'Hauptzeugnis',
                            'status' => $kt->statusMeta(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('sort', SORT_NATURAL)
            ->values()
            ->all();

        // Eigene Texte aufteilen:
        //  - „Korrigierte" = Status „Korrektur durchgeführt" (eigener Tab)
        //  - „Meine Zeugnistexte" = der Rest (offen + erledigt); die Erledigten sind
        //    je Gruppe einzeln einblendbar (Aufteilung passiert in gruppiere()).
        $korrigierte = $eigene->filter(fn (Abschnitt $a) => $a->status === 'korrektur_durchgefuehrt')->values();
        $meineTexte  = $eigene->reject(fn (Abschnitt $a) => $a->status === 'korrektur_durchgefuehrt')->values();

        $offenAnzahl    = $meineTexte->reject(fn (Abschnitt $a) => $a->status === 'vollstaendig')->count();
        $erledigtAnzahl = $meineTexte->count() - $offenAnzahl;

        return view('schulzeugnis::todo.index', [
            'schuljahr'            => $schuljahr,
            'istAdmin'             => false,
            'modus'                => $modus,
            'meineTexteGruppen'    => $this->gruppiere($meineTexte, $modus),
            'korrigierteGruppen'   => $this->gruppiere($korrigierte, $modus),
            'zuKorrigierenGruppen' => $this->gruppiere($korrektur, $modus),
            'zuKorrigierenKlassentexte' => $ktGruppen,
            'meineTexteAnzahl'     => $offenAnzahl,
            'erledigtAnzahl'       => $erledigtAnzahl,
            'korrigierteAnzahl'    => $korrigierte->count(),
            'zuKorrigierenAnzahl'  => $korrektur->count() + $ktKorrektur->count(),
            'stati'                => Abschnitt::STATI,
        ]);
    }

    /**
     * Abschnitte in eine zweistufige Baumstruktur gruppieren – Reihenfolge der
     * Ebenen je nach $modus:
     *   'klasse' → Klasse (oben) › Fach (Akkordeon)
     *   'fach'   → Fach (oben)   › Klasse (Akkordeon)
     *
     * Jeder Knoten liefert 'label', 'farbe' (Stufenfarbe, nur bei Klassen-Knoten),
     * 'sub' (Stufenname, nur bei Klasse), 'anzahl' und die Kinder bzw. 'items'.
     *
     * @param  Collection<int,Abschnitt>  $abschnitte
     * @return array<int,array<string,mixed>>
     */
    private function gruppiere(Collection $abschnitte, string $modus): array
    {
        if ($abschnitte->isEmpty()) {
            return [];
        }

        // Meta-Beschreibung der beiden Gruppierungs-Dimensionen.
        $klasseMeta = fn (Abschnitt $a) => [
            'key'     => $a->zeugnis->schueler->klasse->id,
            'label'   => 'Klasse ' . $a->zeugnis->schueler->klasse->name,
            'sort'    => $a->zeugnis->schueler->klasse->name,
            'farbe'   => $a->zeugnis->schueler->klasse->stufe?->farbe ?: '#64748b',
            'sub'     => $a->zeugnis->schueler->klasse->stufe?->name,
            'natural' => true,
        ];
        $fachMeta = function (Abschnitt $a) {
            $istHaupt = $a->typ === Abschnitt::TYP_HAUPTTEXT;

            return [
                'key'     => $istHaupt ? 'haupt' : (string) $a->fach_id,
                'label'   => $istHaupt ? 'Haupttext' : ($a->fach?->name ?? 'Fachtext'),
                'sort'    => $istHaupt ? -1 : ($a->fach?->reihenfolge ?? 999),
                'farbe'   => null,
                'sub'     => null,
                'natural' => false,
            ];
        };

        [$primaer, $sekundaer] = $modus === 'fach'
            ? [$fachMeta, $klasseMeta]
            : [$klasseMeta, $fachMeta];

        $primaerNatural   = $modus !== 'fach';
        $sekundaerNatural = $modus === 'fach';

        return $abschnitte
            ->groupBy(fn (Abschnitt $a) => $primaer($a)['key'])
            ->map(function (Collection $proPrim) use ($primaer, $sekundaer, $sekundaerNatural) {
                $pm = $primaer($proPrim->first());

                $kinder = $proPrim
                    ->groupBy(fn (Abschnitt $a) => $sekundaer($a)['key'])
                    ->map(function (Collection $proSek) use ($sekundaer) {
                        $sm = $sekundaer($proSek->first());

                        // Innerhalb eines Blattes offen vs. erledigt (Status „Vollständig") trennen.
                        $sortiert = fn (Collection $c) => $c
                            ->sortBy(fn (Abschnitt $a) => sprintf(
                                '%s|%s',
                                $a->zeugnis->schueler->nachname ?? '',
                                $a->zeugnis->schueler->vorname ?? ''
                            ))
                            ->values();

                        $offen    = $sortiert($proSek->reject(fn (Abschnitt $a) => $a->status === 'vollstaendig'));
                        $erledigt = $sortiert($proSek->filter(fn (Abschnitt $a) => $a->status === 'vollstaendig'));

                        return [
                            'label'          => $sm['label'],
                            'farbe'          => $sm['farbe'],
                            'sub'            => $sm['sub'],
                            'sort'           => $sm['sort'],
                            'anzahl'         => $offen->count(),
                            'erledigtAnzahl' => $erledigt->count(),
                            'offen'          => $offen,
                            'erledigt'       => $erledigt,
                        ];
                    })
                    ->sortBy('sort', $sekundaerNatural ? SORT_NATURAL : SORT_REGULAR)
                    ->values()
                    ->all();

                return [
                    'label'  => $pm['label'],
                    'farbe'  => $pm['farbe'],
                    'sub'    => $pm['sub'],
                    'sort'   => $pm['sort'],
                    'anzahl' => $proPrim->reject(fn (Abschnitt $a) => $a->status === 'vollstaendig')->count(),
                    'kinder' => $kinder,
                ];
            })
            ->sortBy('sort', $primaerNatural ? SORT_NATURAL : SORT_REGULAR)
            ->values()
            ->all();
    }
}
