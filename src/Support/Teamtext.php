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
 *  - Jeder schreibt seinen Teiltext; wer zustimmt, gehört zur Gruppe. Ab zwei Mitgliedern
 *    teilt die Gruppe EINEN Text (in jedem ihrer Teiltexte gleich gespeichert).
 *  - Wer sich herauslöst, betrifft nur sich: Er behält den bisherigen gemeinsamen Text als
 *    eigenen, die übrigen bleiben (ab zwei) zusammengefasst.
 *  - Entschieden (zusammenfassen / herauslösen) wird je KLASSE und FACH zweimal: einmal
 *    für den klassenweiten Text, einmal für alle Schülertexte zugleich
 *    ({@see zustimmungUeberall()}). Geschrieben wird weiter je Schüler.
 *  - Text fürs Zeugnis (Träger-Feld) = alle Teiltexte nacheinander, gleiche nur einmal.
 *    `zusammengefasst` am Träger = ALLE Fachlehrer in einer Gruppe (erst dann arbeiten
 *    Korrektoren/Wiederherstellen am Träger-Text; das übernimmt sicherstellen() zurück).
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
        $klasse = $abschnitt->zeugnis?->schueler?->klasse;

        // Neue Teiltexte übernehmen die Entscheidung der Klasse für die Schülertexte.
        return self::traegerSicherstellen($abschnitt, 'inhalt', self::fachlehrer($abschnitt),
            $klasse && $abschnitt->fach_id ? self::vorgabenAusAbschnitten($klasse, (int) $abschnitt->fach_id, $abschnitt->id) : []);
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
    private static function traegerSicherstellen(Model $traeger, string $feld, Collection $lehrer, array $vorgaben = []): ?Collection
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
                // Neu: Entscheidung der Klasse übernehmen; im gemeinsamen Feld steigt man mit ein.
                $zustimmung = $vorgaben[$l->id] ?? (bool) $traeger->zusammengefasst;
                $traeger->teiltexte()->create([
                    'lehrer_id'      => $l->id,
                    'autor_name'     => $l->fullName(),
                    'inhalt'         => $traeger->zusammengefasst ? $traeger->{$feld} : null,
                    'zusammenfassen' => $zustimmung,
                ]);
            }
        }
        // Alle in einer Gruppe: der Träger-Text ist der gemeinsame Text – Änderungen von
        // außen (Korrektor, Wiederherstellen, Gesamt-Bearbeitung) in die Teiltexte ziehen.
        if ($traeger->zusammengefasst) {
            $traeger->teiltexte()->whereIn('lehrer_id', $lehrer->pluck('id'))
                ->where(fn ($q) => $q->whereNull('inhalt')->orWhere('inhalt', '!=', (string) $traeger->{$feld}))
                ->update(['inhalt' => $traeger->{$feld}]);
        }
        $traeger->unsetRelation('teiltexte');

        return $lehrer;
    }

    /** Entscheidung der Klasse für die Schülertexte (aus einem anderen Schülertext des Fachs). @return array<int,bool> */
    private static function vorgabenAusAbschnitten(Klasse $klasse, int $fachId, ?int $ohneId = null): array
    {
        $a = Abschnitt::where('typ', Abschnitt::TYP_FACHTEXT)->where('fach_id', $fachId)
            ->when($ohneId, fn ($q) => $q->where('id', '!=', $ohneId))
            ->whereHas('zeugnis.schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
            ->whereHas('teiltexte')->orderBy('id')->first();

        return $a ? $a->teiltexte()->pluck('zusammenfassen', 'lehrer_id')->map(fn ($v) => (bool) $v)->all() : [];
    }

    /**
     * Text der Lehrkraft speichern: in der Gruppe der gemeinsame Text (für alle Mitglieder),
     * sonst der eigene.
     */
    public static function textSpeichern(Collection $teile, Model $mein, ?string $text): void
    {
        $gruppe = self::gruppe($teile);
        if ($gruppe->contains('id', $mein->id)) {
            foreach ($gruppe as $t) {
                $t->inhalt = $text;
            }
        } else {
            $mein->inhalt = $text;
        }
    }

    /**
     * Zustimmung einer Lehrkraft an EINEM Träger ändern. Beitreten: die Gruppe teilt danach
     * den verbundenen Text ihrer Mitglieder. Herauslösen: nur diese Lehrkraft, sie behält
     * den bisherigen gemeinsamen Text als eigenen.
     */
    public static function zustimmungAnwenden(Collection $teile, int $lehrerId, bool $zustimmen): void
    {
        $t = $teile->firstWhere('lehrer_id', $lehrerId);
        if (! $t || (bool) $t->zusammenfassen === $zustimmen) {
            return;
        }
        $t->zusammenfassen = $zustimmen;
        if ($zustimmen) {
            $gruppe = self::gruppe($teile);
            if ($gruppe->contains('id', $t->id)) {
                $gemeinsam = self::verbinden($gruppe);
                foreach ($gruppe as $g) {
                    $g->inhalt = $gemeinsam;
                }
            }
        }
    }

    /**
     * Entscheidung für die Schülertexte je Klasse × Fach: Zustimmung der Lehrkraft an allen
     * Schülertexten des Fachs übernehmen (nicht abgeschlossene Zeugnisse). Der klassenweite
     * Text hat seine eigene Entscheidung und bleibt unberührt.
     *
     * @return int  Zahl der Schülertexte, die mitgezogen wurden
     */
    public static function zustimmungUeberall(Klasse $klasse, int $fachId, int $lehrerId, bool $zustimmen): int
    {
        $lehrer = self::fachlehrerVon($klasse, $fachId);
        if ($lehrer->count() < 2) {
            return 0;
        }

        $traeger = collect();
        Abschnitt::where('typ', Abschnitt::TYP_FACHTEXT)->where('fach_id', $fachId)
            ->whereHas('zeugnis', fn ($q) => $q->where('status', '!=', \Intranet\Modules\Schulzeugnis\Models\Zeugnis::STATUS_ABGESCHLOSSEN)
                ->whereHas('schueler', fn ($s) => $s->where('klasse_id', $klasse->id)))
            ->with('zeugnis.schueler.klasse')
            ->get()
            ->each(fn (Abschnitt $a) => $traeger->push([$a, 'inhalt']));

        $anzahl = 0;
        foreach ($traeger as [$t, $feld]) {
            if (! self::sicherstellen($t)) {
                continue;
            }
            $teile = self::teiltexte($t, $lehrer);
            self::zustimmungAnwenden($teile, $lehrerId, $zustimmen);
            foreach ($teile as $teil) {
                if ($teil->isDirty()) {
                    $teil->save();
                }
            }
            $t->{$feld}          = self::verbinden($teile);
            $t->zusammengefasst  = self::alleStimmenZu($teile, $lehrer);
            if ($t instanceof Abschnitt) {
                $t->status = Abschnitt::statusNachInhalt((string) $t->status, $t->hatInhalt());
                $anzahl++;
            }
            if ($t->isDirty()) {
                $t->save();
            }
        }

        return $anzahl;
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

    /**
     * Die Gruppe: alle Teiltexte mit Zustimmung – aber erst ab zwei Lehrkräften (eine
     * allein Zustimmende schreibt noch für sich).
     */
    public static function gruppe(Collection $teiltexte): Collection
    {
        $g = $teiltexte->filter(fn ($t) => $t->zusammenfassen)->values();

        return $g->count() >= 2 ? $g : collect();
    }

    /** Stimmen alle Fachlehrer dem Zusammenfassen zu? */
    public static function alleStimmenZu(Collection $teiltexte, Collection $lehrer): bool
    {
        return $teiltexte->count() === $lehrer->count()
            && $teiltexte->every(fn ($t) => $t->zusammenfassen);
    }
}
