<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Support\Collection;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
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

        $leer = [
            'schuljahr'        => $schuljahr,
            'istAdmin'         => $istAdmin,
            'eigeneGruppen'    => [],
            'korrekturGruppen' => [],
            'eigeneAnzahl'     => 0,
            'korrekturAnzahl'  => 0,
            'letzteAenderung'  => collect(),
            'stati'            => Abschnitt::STATI,
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

        // 1) Eigene, noch nicht vollständige Abschnitte (Zeugnis nicht abgeschlossen).
        $eigene = collect();
        if ($relevanteKlassen->isNotEmpty()) {
            $eigene = Abschnitt::where('status', '!=', 'vollstaendig')
                ->whereHas('zeugnis', fn ($q) => $q
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

        // Letzte protokollierte Änderung je Abschnitt (was zuletzt, wann, von wem) –
        // als Referenz auf den Änderungsverlauf direkt in der Aufgabenliste.
        $alleIds = $eigene->pluck('id')->merge($korrektur->pluck('id'))->unique();
        $letzteAenderung = Protokoll::whereIn('abschnitt_id', $alleIds)
            ->whereIn('aktion', [
                'abschnitt_geaendert', 'abschnitt_status', 'abschnitt_notiz',
                'abschnitt_klassentext', 'abschnitt_wiederhergestellt', 'abschnitt_klassentext_wiederhergestellt',
            ])
            ->orderByDesc('id')
            ->get(['id', 'abschnitt_id', 'akteur_name', 'beschreibung', 'created_at'])
            ->groupBy('abschnitt_id')
            ->map(fn (Collection $g) => $g->first());

        return view('schulzeugnis::todo.index', [
            'schuljahr'        => $schuljahr,
            'istAdmin'         => false,
            'eigeneGruppen'    => $this->gruppiere($eigene),
            'korrekturGruppen' => $this->gruppiere($korrektur),
            'eigeneAnzahl'     => $eigene->count(),
            'korrekturAnzahl'  => $korrektur->count(),
            'letzteAenderung'  => $letzteAenderung,
            'stati'            => Abschnitt::STATI,
        ]);
    }

    /**
     * Abschnitte zu einer nach Klasse → Fach geordneten Struktur zusammenfassen.
     *
     * @param  Collection<int,Abschnitt>  $abschnitte
     * @return array<int,array{klasse:Klasse,anzahl:int,faecher:array}>
     */
    private function gruppiere(Collection $abschnitte): array
    {
        return $abschnitte
            ->groupBy(fn (Abschnitt $a) => $a->zeugnis->schueler->klasse->id)
            ->map(function (Collection $proKlasse) {
                $klasse = $proKlasse->first()->zeugnis->schueler->klasse;

                $faecher = $proKlasse
                    ->groupBy(fn (Abschnitt $a) => $a->typ === Abschnitt::TYP_HAUPTTEXT ? 'haupt' : (string) $a->fach_id)
                    ->map(function (Collection $proFach) {
                        $erst     = $proFach->first();
                        $istHaupt = $erst->typ === Abschnitt::TYP_HAUPTTEXT;

                        $items = $proFach
                            ->sortBy(fn (Abschnitt $a) => sprintf(
                                '%s|%s',
                                $a->zeugnis->schueler->nachname ?? '',
                                $a->zeugnis->schueler->vorname ?? ''
                            ))
                            ->values();

                        return [
                            'label'       => $istHaupt ? 'Haupttext' : ($erst->fach?->name ?? 'Fachtext'),
                            'reihenfolge' => $istHaupt ? -1 : ($erst->fach?->reihenfolge ?? 999),
                            'anzahl'      => $items->count(),
                            'items'       => $items,
                        ];
                    })
                    ->sortBy('reihenfolge')
                    ->values()
                    ->all();

                return [
                    'klasse'  => $klasse,
                    'anzahl'  => $proKlasse->count(),
                    'faecher' => $faecher,
                ];
            })
            ->sortBy(fn ($g) => $g['klasse']->name, SORT_NATURAL)
            ->values()
            ->all();
    }
}
