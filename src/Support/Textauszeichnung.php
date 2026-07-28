<?php

namespace Intranet\Modules\Schulzeugnis\Support;

/**
 * Leichte Inline-Auszeichnung für Zeugnistexte: **fett**, *kursiv*, __unterstrichen__.
 *
 * Die Texte bleiben in der Datenbank und im Protokoll roher Marker-Text (kein HTML) –
 * damit funktionieren old(), Wiederherstellen und der Vergleich unverändert. Erst beim
 * Rendern wird der Text hier in Stil-Segmente zerlegt:
 *
 *  - `parse()` liefert Absätze → Wörter → Segmente {text, fett, kursiv, unterstrichen}.
 *    Die Marker sind Toggles; ihr Zustand läuft über Wort- UND Absatzgrenzen weiter
 *    (eine Passage über mehrere Absätze braucht nur ein Markerpaar). Longest-Match:
 *    `**` vor `*`; ein einzelnes `_` ist KEIN Marker (kommt in Namen vor).
 *  - `breite()` misst Segmente mit der jeweils passenden dompdf-Schriftvariante
 *    (fett ist breiter als normal) – dadurch stimmen Zeilenumbruch und
 *    Überlauf-Warnung auch für ausgezeichnete Texte.
 *  - `zuHtml()` erzeugt je Segment ein IN SICH GESCHLOSSENES <span> (Nutzertext
 *    immer escaped). Weil kein Tag über Segmentgrenzen reicht, kann der Renderer
 *    Zeilen beliebig auf Rahmen und Seiten verteilen, ohne HTML zu zerreißen.
 */
final class Textauszeichnung
{
    /** @var array{text:string,fett:bool,kursiv:bool,unterstrichen:bool} Segment-Shape (Doku) */
    private const SEGMENT = ['text' => '', 'fett' => false, 'kursiv' => false, 'unterstrichen' => false];

    /** Schneller Vorab-Check: kann der Text überhaupt Marker enthalten? */
    public static function hatMarker(string $text): bool
    {
        return str_contains($text, '*') || str_contains($text, '__');
    }

    /**
     * Marker-Text → Absätze → Wörter → Segmente. Marker sind entfernt.
     *
     * Leerer Absatz = leeres Wort-Array (ergibt beim Umbruch eine Leerzeile).
     * Ein Wort nur aus Markern (z. B. „**") entfällt, seine Toggles wirken trotzdem.
     *
     * @return array<int, array<int, array<int, array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>>>
     */
    public static function parse(string $text): array
    {
        $text = self::utf8($text);

        $fett = $kursiv = $unterstrichen = false;
        $absaetze = [];

        foreach (explode("\n", $text) as $absatz) {
            $woerter = [];

            if (trim($absatz) !== '') {
                foreach (preg_split('/\s+/u', trim($absatz)) as $roh) {
                    $segmente = [];
                    $puffer = '';
                    $push = function () use (&$segmente, &$puffer, &$fett, &$kursiv, &$unterstrichen): void {
                        if ($puffer !== '') {
                            $segmente[] = ['text' => $puffer, 'fett' => $fett, 'kursiv' => $kursiv, 'unterstrichen' => $unterstrichen];
                            $puffer = '';
                        }
                    };

                    // Alternation-Reihenfolge = Longest-Match: `**` gewinnt vor `*`.
                    foreach (preg_split('/(\*\*|__|\*)/u', $roh, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
                        if ($token === '**') {
                            $push();
                            $fett = ! $fett;
                        } elseif ($token === '__') {
                            $push();
                            $unterstrichen = ! $unterstrichen;
                        } elseif ($token === '*') {
                            $push();
                            $kursiv = ! $kursiv;
                        } else {
                            $puffer .= $token;
                        }
                    }
                    $push();

                    if ($segmente !== []) {
                        $woerter[] = self::mergeGleichstilig($segmente);
                    }
                }
            }

            $absaetze[] = $woerter;
        }

        return $absaetze;
    }

    /** Marker entfernen → Klartext (Wortzählung, Variablen, die escaped drucken). */
    public static function ohneMarker(string $text): string
    {
        $text = self::utf8($text);

        if (! self::hatMarker($text)) {
            return $text;
        }

        $absaetze = [];
        foreach (self::parse($text) as $woerter) {
            $absaetze[] = implode(' ', array_map(self::class.'::zeileText', $woerter));
        }

        return implode("\n", $absaetze);
    }

    /**
     * Offene Stile am Textende schließen („versiegeln"). Nötig, wenn Teiltexte
     * verkettet werden: Ein vergessener Schließ-Marker in einem Fachtext darf
     * nicht alle folgenden Fächer mitfärben.
     */
    public static function abschliessen(string $text): string
    {
        $text = self::utf8($text);

        if (! self::hatMarker($text)) {
            return $text;
        }

        preg_match_all('/\*\*|__|\*/u', $text, $treffer);
        $anzahl = array_count_values($treffer[0] ?? []);

        return $text
            .((($anzahl['**'] ?? 0) % 2) ? '**' : '')
            .((($anzahl['*'] ?? 0) % 2) ? '*' : '')
            .((($anzahl['__'] ?? 0) % 2) ? '__' : '');
    }

    /**
     * dompdf-Font-Handles je Stilkombination (null ohne FontMetrics).
     *
     * @return array<string,mixed>|null
     */
    public static function fonts(?object $fm, string $family): ?array
    {
        if (! $fm || ! method_exists($fm, 'getFont')) {
            return null;
        }

        return [
            'normal'      => $fm->getFont($family, 'normal'),
            'bold'        => $fm->getFont($family, 'bold'),
            'italic'      => $fm->getFont($family, 'italic'),
            'bold_italic' => $fm->getFont($family, 'bold_italic'),
        ];
    }

    /**
     * Breite einer Segmentliste in pt. Jedes Segment wird mit seiner Schrift-
     * variante gemessen (Unterstreichung ändert die Breite nicht); Fallback ist
     * dieselbe Zeichen-Schätzung wie bisher im Renderer.
     *
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $segmente
     */
    public static function breite(array $segmente, ?object $fm, ?array $fonts, float $size): float
    {
        $summe = 0.0;

        foreach ($segmente as $s) {
            $font = null;
            if ($fm && $fonts) {
                $key = $s['fett'] && $s['kursiv'] ? 'bold_italic' : ($s['fett'] ? 'bold' : ($s['kursiv'] ? 'italic' : 'normal'));
                $font = $fonts[$key] ?? $fonts['normal'] ?? null;
            }

            $summe += $font
                ? (float) $fm->getTextWidth($s['text'], $font, $size)
                : mb_strlen($s['text']) * $size * 0.52;
        }

        return $summe;
    }

    /**
     * Klartext einer Zeile/eines Worts (Segmentliste).
     *
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $segmente
     */
    public static function zeileText(array $segmente): string
    {
        return implode('', array_column($segmente, 'text'));
    }

    /**
     * EINE Zeile (Segmentliste) → HTML. Jedes Segment ist in sich geschlossen,
     * der Nutzertext immer escaped – das Markup selbst ist hartverdrahtet.
     *
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $segmente
     */
    public static function zuHtml(array $segmente): string
    {
        $html = '';

        foreach ($segmente as $s) {
            $stil = ($s['fett'] ? 'font-weight:bold;' : '')
                .($s['kursiv'] ? 'font-style:italic;' : '')
                .($s['unterstrichen'] ? 'text-decoration:underline;' : '');

            $html .= $stil === ''
                ? e($s['text'])
                : '<span style="'.$stil.'">'.e($s['text']).'</span>';
        }

        return $html;
    }

    /**
     * Ganzer Marker-Text → HTML-Fließtext für frei umbrechende Blöcke
     * (white-space: pre-line; dompdf bricht selbst um, keine Vermessung).
     */
    public static function zuHtmlFliesstext(string $text): string
    {
        $text = self::utf8($text);

        if (! self::hatMarker($text)) {
            return e($text);
        }

        $absaetze = [];
        foreach (self::parse($text) as $woerter) {
            $absaetze[] = implode(' ', array_map(self::class.'::zuHtml', $woerter));
        }

        return implode("\n", $absaetze);
    }

    /**
     * Zwei Segmentlisten verketten und gleichstilige Nahtstellen mergen
     * (für den Zeilenaufbau im Umbruch).
     *
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $a
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $b
     * @return array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>
     */
    public static function verbinde(array $a, array $b): array
    {
        return self::mergeGleichstilig(array_merge($a, $b));
    }

    /**
     * 20 Jahre Datenbestand: vereinzelt stecken Latin-1/Windows-1252-Bytes in
     * alten Texten. An der Grenze reparieren statt crashen – preg_split mit /u
     * liefert auf kaputtem UTF-8 sonst false (und dompdf druckt Zeichensalat).
     */
    private static function utf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8')
            ? $text
            : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /**
     * Benachbarte Segmente mit identischen Stil-Flags zusammenziehen.
     *
     * @param  array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>  $segmente
     * @return array<int,array{text:string,fett:bool,kursiv:bool,unterstrichen:bool}>
     */
    private static function mergeGleichstilig(array $segmente): array
    {
        $ergebnis = [];

        foreach ($segmente as $s) {
            $letzt = $ergebnis === [] ? null : $ergebnis[count($ergebnis) - 1];
            if ($letzt !== null
                && $letzt['fett'] === $s['fett']
                && $letzt['kursiv'] === $s['kursiv']
                && $letzt['unterstrichen'] === $s['unterstrichen']) {
                $ergebnis[count($ergebnis) - 1]['text'] .= $s['text'];
            } else {
                $ergebnis[] = $s;
            }
        }

        return $ergebnis;
    }
}
