<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Klassentext;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;

/**
 * Team-Teaching: Haben mindestens zwei Lehrkräfte einen Lehrauftrag im selben Fach der
 * Klasse, schreibt zunächst jede ihren eigenen Teiltext – am Schülertext (Abschnitt,
 * Feld `inhalt`) wie am klassenweiten Text des Fachs (Klassentext, Feld `text`).
 *
 *  - getrennt: jeder bearbeitet nur seinen Teiltext; der Text fürs Zeugnis (Träger-Feld)
 *    sind die Teiltexte nacheinander, gleiche nur einmal.
 *  - Stimmen ALLE dem Zusammenfassen zu, wird der verbundene Text zum gemeinsamen Feld
 *    (`zusammengefasst` am Träger), das alle bearbeiten.
 *  - Nimmt eine Lehrkraft die Zustimmung zurück, bekommt jeder Teiltext den aktuellen
 *    gemeinsamen Text, und es ist wieder getrennt.
 *
 * Bereits geschriebene Texte (vor Einführung) gelten als zusammengefasst – so geht nichts
 * verloren und niemandem wird ein fremder Text als eigener untergeschoben.
 */
class Teamtext
{
    /** Fachlehrer einer Klasse in einem Fach, in Lehrauftrags-Reihenfolge. */
    public static function fachlehrerVon(Klasse $klasse, int $fachId): Collection
    {
        $ids = Lehrauftrag::where('klasse_id', $klasse->id)->where('fach_id', $fachId)
            ->orderBy('id')->pluck('lehrer_id')->unique()->values();

        return Lehrer::whereIn('id', $ids)->get()
            ->sortBy(fn (Lehrer $l) => $ids->search($l->id))
            ->values();
    }

    /** Fachlehrer eines Fachtext-Abschnitts. */
    public static function fachlehrer(Abschnitt $abschnitt): Collection
    {
        $klasse = $abschnitt->zeugnis?->schueler?->klasse;
        if ($abschnitt->typ !== Abschnitt::TYP_FACHTEXT || ! $abschnitt->fach_id || ! $klasse) {
            return collect();
        }

        return self::fachlehrerVon($klasse, (int) $abschnitt->fach_id);
    }

    /** Team-Modus am Schülertext prüfen + Teiltexte anlegen. @return Collection<int,Lehrer>|null */
    public static function sicherstellen(Abschnitt $abschnitt): ?Collection
    {
        return self::traegerSicherstellen($abschnitt, 'inhalt', self::fachlehrer($abschnitt));
    }

    /** Team-Modus am klassenweiten Text eines Fachs (nicht Haupttext/Spruch). @return Collection<int,Lehrer>|null */
    public static function sicherstellenKlassentext(Klassentext $kt, ?Klasse $klasse): ?Collection
    {
        if (($kt->art ?? 'fach') !== 'fach' || ! $kt->fach_id || ! $klasse) {
            return null;
        }
        $lehrer = self::fachlehrerVon($klasse, (int) $kt->fach_id);
        if ($lehrer->count() < 2) {
            return null;
        }
        if (! $kt->exists) {
            $kt->save();
        }

        return self::traegerSicherstellen($kt, 'text', $lehrer);
    }

    /** Ist der Träger im Team-Modus und noch getrennt? (Klassentext) */
    public static function klassentextGetrennt(Klassentext $kt, ?Klasse $klasse): bool
    {
        return self::sicherstellenKlassentext($kt, $klasse) !== null && ! $kt->zusammengefasst;
    }

    /**
     * @param  Abschnitt|Klassentext  $traeger
     * @return Collection<int,Lehrer>|null
     */
    private static function traegerSicherstellen(Model $traeger, string $feld, Collection $lehrer): ?Collection
    {
        if ($lehrer->count() < 2) {
            return null;
        }

        $vorhanden = $traeger->teiltexte()->get()->keyBy('lehrer_id');
        $erstmals  = $vorhanden->isEmpty();
        if ($erstmals && Abschnitt::textGefuellt($traeger->{$feld}) && ! $traeger->zusammengefasst) {
            $traeger->forceFill(['zusammengefasst' => true])->save();
        }

        foreach ($lehrer as $l) {
            if (! isset($vorhanden[$l->id])) {
                // Neu im Team: im gemeinsamen Feld steigt man mit ein, sonst beginnt man leer.
                $traeger->teiltexte()->create([
                    'lehrer_id'      => $l->id,
                    'autor_name'     => $l->fullName(),
                    'inhalt'         => $traeger->zusammengefasst ? $traeger->{$feld} : null,
                    'zusammenfassen' => (bool) $traeger->zusammengefasst,
                ]);
            }
        }
        $traeger->unsetRelation('teiltexte');

        return $lehrer;
    }

    /** Teiltexte der aktuellen Fachlehrer, in deren Reihenfolge. @param Abschnitt|Klassentext $traeger */
    public static function teiltexte(Model $traeger, Collection $lehrer): Collection
    {
        $nach = $traeger->teiltexte()->get()->keyBy('lehrer_id');

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
            && $teiltexte->every(fn ($t) => $t->zusammenfassen);
    }
}
