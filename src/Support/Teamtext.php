<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Illuminate\Support\Collection;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Teiltext;

/**
 * Team-Teaching an einem Fachtext: Haben mindestens zwei Lehrkräfte einen Lehrauftrag
 * im selben Fach der Klasse, schreibt zunächst jede ihren eigenen Teiltext.
 *
 *  - getrennt: jeder bearbeitet nur seinen Teiltext; auf dem Zeugnis (Abschnitt.inhalt)
 *    stehen die Teiltexte nacheinander, gleiche nur einmal.
 *  - Stimmen ALLE dem Zusammenfassen zu, wird der verbundene Text zum gemeinsamen Feld
 *    (Abschnitt.zusammengefasst), das alle bearbeiten.
 *  - Nimmt eine Lehrkraft die Zustimmung zurück, bekommt jeder Teiltext den aktuellen
 *    gemeinsamen Text, und es ist wieder getrennt.
 *
 * Bereits geschriebene Texte (vor Einführung) gelten als zusammengefasst – so geht nichts
 * verloren und niemandem wird ein fremder Text als eigener untergeschoben.
 */
class Teamtext
{
    /** Fachlehrer des Abschnitts (Lehrauftrag Klasse × Fach), in Lehrauftrags-Reihenfolge. */
    public static function fachlehrer(Abschnitt $abschnitt): Collection
    {
        if ($abschnitt->typ !== Abschnitt::TYP_FACHTEXT || ! $abschnitt->fach_id) {
            return collect();
        }
        $klasse = $abschnitt->zeugnis?->schueler?->klasse;
        if (! $klasse) {
            return collect();
        }

        $ids = Lehrauftrag::where('klasse_id', $klasse->id)->where('fach_id', $abschnitt->fach_id)
            ->orderBy('id')->pluck('lehrer_id')->unique()->values();

        return Lehrer::whereIn('id', $ids)->get()
            ->sortBy(fn (Lehrer $l) => $ids->search($l->id))
            ->values();
    }

    /**
     * Team-Modus prüfen und Teiltexte für alle Fachlehrer anlegen.
     *
     * @return Collection<int,Lehrer>|null  Fachlehrer, wenn Team-Modus; sonst null
     */
    public static function sicherstellen(Abschnitt $abschnitt): ?Collection
    {
        $lehrer = self::fachlehrer($abschnitt);
        if ($lehrer->count() < 2) {
            return null;
        }

        $vorhanden = $abschnitt->teiltexte()->get()->keyBy('lehrer_id');
        $erstmals  = $vorhanden->isEmpty();
        if ($erstmals && Abschnitt::textGefuellt($abschnitt->inhalt) && ! $abschnitt->zusammengefasst) {
            $abschnitt->forceFill(['zusammengefasst' => true])->save();
        }

        foreach ($lehrer as $l) {
            if (! isset($vorhanden[$l->id])) {
                // Neu im Team: im gemeinsamen Feld steigt man mit ein, sonst beginnt man leer.
                $abschnitt->teiltexte()->create([
                    'lehrer_id'      => $l->id,
                    'autor_name'     => $l->fullName(),
                    'inhalt'         => $abschnitt->zusammengefasst ? $abschnitt->inhalt : null,
                    'zusammenfassen' => (bool) $abschnitt->zusammengefasst,
                ]);
            }
        }
        $abschnitt->unsetRelation('teiltexte');

        return $lehrer;
    }

    /** Teiltexte der aktuellen Fachlehrer, in deren Reihenfolge. @return Collection<int,Teiltext> */
    public static function teiltexte(Abschnitt $abschnitt, Collection $lehrer): Collection
    {
        $nach = $abschnitt->teiltexte()->get()->keyBy('lehrer_id');

        return $lehrer->map(fn (Lehrer $l) => $nach[$l->id] ?? null)->filter()->values();
    }

    /** Teiltexte nacheinander (Absatz dazwischen), leere weg, gleiche nur einmal. */
    public static function verbinden(Collection $teiltexte): ?string
    {
        $texte = [];
        foreach ($teiltexte as $t) {
            $text = trim((string) $t->inhalt);
            if (Abschnitt::textGefuellt($text) && ! in_array($text, $texte, true)) {
                $texte[] = $text;
            }
        }

        return $texte === [] ? null : implode("\n\n", $texte);
    }

    /** Stimmen alle Fachlehrer dem Zusammenfassen zu? */
    public static function alleStimmenZu(Collection $teiltexte, Collection $lehrer): bool
    {
        return $teiltexte->count() === $lehrer->count()
            && $teiltexte->every(fn (Teiltext $t) => $t->zusammenfassen);
    }
}
