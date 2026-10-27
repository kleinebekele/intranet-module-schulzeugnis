<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Illuminate\Support\Collection;
use Intranet\Modules\Schulzeugnis\Models\Schueler;

/**
 * Sucht im Schülertext nach Namen, die dort vermutlich nicht hingehören:
 *  - der Vorname eines anderen Kindes der Klasse (Text beim falschen Kind gelandet,
 *    oder aus einem anderen Zeugnis kopiert),
 *  - ein Wort, das dem eigenen Vor- oder Nachnamen sehr ähnelt (Tippfehler, „Karla"
 *    statt „Carla").
 * Nur ein Hinweis – gespeichert wird trotzdem. Geprüft werden großgeschriebene Wörter,
 * auch im Genitiv („Johannas").
 */
class Namenspruefung
{
    /** Kürzere Namen (Max, Ida …) prüfen wir nicht auf Tippfehler – zu viele echte Wörter ähneln ihnen. */
    private const MIN_LAENGE_TIPPFEHLER = 4;

    /**
     * @param  Collection<int,Schueler>  $klasse  alle Schüler der Klasse (inkl. des geprüften)
     * @return array<int,string> Hinweise, leer = nichts aufgefallen
     */
    public static function pruefen(?string $text, Schueler $schueler, Collection $klasse): array
    {
        $woerter = self::woerter($text);
        if ($woerter === []) {
            return self::pronomen($text, $schueler);
        }

        $eigene = array_merge(self::teile($schueler->vorname), self::teile($schueler->nachname));
        $eigeneKlein = array_map(fn ($t) => mb_strtolower($t), $eigene);

        // Vornamen der anderen Kinder (klein → Schreibweise), ohne Namen, die das Kind selbst trägt.
        $fremde = [];
        foreach ($klasse as $k) {
            if ((int) $k->id === (int) $schueler->id) {
                continue;
            }
            foreach (self::teile($k->vorname) as $t) {
                $klein = mb_strtolower($t);
                if (! in_array($klein, $eigeneKlein, true)) {
                    $fremde[$klein] = $t;
                }
            }
        }

        $hinweise = [];
        foreach ($woerter as $wort) {
            $klein = mb_strtolower($wort);
            $formen = [$klein];
            if (mb_strlen($klein) > 3 && str_ends_with($klein, 's')) {
                $formen[] = mb_substr($klein, 0, -1); // Genitiv: „Johannas"
            }

            if (array_intersect($formen, $eigeneKlein) !== []) {
                continue;
            }
            if ($treffer = array_values(array_intersect($formen, array_keys($fremde)))) {
                // Name in Klammern nur, wenn er anders aussieht als das Wort (Genitiv „Johannas").
                $name = $fremde[$treffer[0]];
                $hinweise[] = '„' . $wort . '" ist ein anderes Kind der Klasse'
                    . ($treffer[0] === $klein ? '.' : ' (' . $name . ').');
                continue;
            }
            foreach ($eigene as $name) {
                if (mb_strlen($name) < self::MIN_LAENGE_TIPPFEHLER) {
                    continue;
                }
                $grenze = mb_strlen($name) <= 5 ? 1 : 2;
                $abstand = min(array_map(fn ($f) => self::abstand($f, mb_strtolower($name)), $formen));
                if ($abstand > 0 && $abstand <= $grenze) {
                    $hinweise[] = '„' . $wort . '" – gemeint ist vielleicht „' . $name . '"?';
                    break;
                }
            }
        }

        return array_values(array_unique(array_merge($hinweise, self::pronomen($text, $schueler))));
    }

    private const PRONOMEN_M = ['er', 'ihm', 'ihn', 'seine', 'seinen', 'seinem', 'seiner', 'seines'];
    private const PRONOMEN_W = ['sie', 'ihr', 'ihre', 'ihren', 'ihrem', 'ihrer', 'ihres'];

    /**
     * Pronomen passend zum eingetragenen Geschlecht (m/w; sonst keine Prüfung).
     *  - Mädchen: „er/ihm/ihn/seine…" ist eindeutig falsch → immer ein Hinweis.
     *  - Junge: „sie/ihre…" kann auch Mehrzahl sein („bei ihren Aufgaben") → nur ein
     *    Hinweis, wenn im Text gar kein männliches Pronomen steht (typisch: Text eines
     *    Mädchens übernommen).
     *
     * @return array<int,string>
     */
    private static function pronomen(?string $text, Schueler $schueler): array
    {
        $g = $schueler->geschlecht;
        if (! in_array($g, ['m', 'w'], true)) {
            return [];
        }
        $rein = html_entity_decode(strip_tags(Textauszeichnung::ohneMarker((string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/\p{L}+/u', mb_strtolower($rein), $m);
        $woerter = array_unique($m[0]);

        if ($g === 'w') {
            $falsch = array_intersect(self::PRONOMEN_M, $woerter);
            $wer = 'einem Mädchen';
        } else {
            $falsch = array_intersect(self::PRONOMEN_M, $woerter) === [] ? array_intersect(self::PRONOMEN_W, $woerter) : [];
            $wer = 'einem Jungen';
        }

        return array_values(array_map(fn ($w) => '„' . $w . '" passt nicht zu ' . $wer . '.', $falsch));
    }

    /**
     * Prüft mehrere Texte (z. B. alle Abschnitte eines Zeugnisses); jeder Hinweis mit
     * vorangestellter Bezeichnung (Fach, Fachbereich …).
     *
     * @param  iterable<string,?string>  $texte  Bezeichnung => Text
     * @return array<int,string>
     */
    public static function pruefenMehrere(iterable $texte, Schueler $schueler, Collection $klasse): array
    {
        $alle = [];
        foreach ($texte as $bezeichnung => $text) {
            foreach (self::pruefen($text, $schueler, $klasse) as $h) {
                $alle[] = $bezeichnung . ': ' . $h;
            }
        }

        return $alle;
    }

    /** Großgeschriebene Wörter des reinen Textes (ohne HTML und Formatierungs-Marker). */
    private static function woerter(?string $text): array
    {
        $rein = html_entity_decode(strip_tags(Textauszeichnung::ohneMarker((string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($rein) === '') {
            return [];
        }
        preg_match_all('/(?<![\p{L}])\p{Lu}\p{L}+/u', $rein, $m);

        return array_values(array_unique($m[0]));
    }

    /** Namensteile („Anna-Lena Marie" → Anna, Lena, Marie), mindestens zwei Buchstaben. */
    private static function teile(?string $name): array
    {
        return array_values(array_filter(
            preg_split('/[\s\-]+/u', trim((string) $name)) ?: [],
            fn ($t) => mb_strlen($t) >= 2
        ));
    }

    /** Levenshtein-Abstand auf Zeichen (nicht Bytes) – Umlaute zählen einfach. */
    private static function abstand(string $a, string $b): int
    {
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $vorher = range(0, count($y));
        foreach ($x as $i => $cx) {
            $jetzt = [$i + 1];
            foreach ($y as $j => $cy) {
                $jetzt[$j + 1] = min($vorher[$j + 1] + 1, $jetzt[$j] + 1, $vorher[$j] + ($cx === $cy ? 0 : 1));
            }
            $vorher = $jetzt;
        }

        return $vorher[count($y)];
    }
}
