<?php

namespace Intranet\Modules\Schulzeugnis\Support;

/**
 * Textverteilung für Nicht-Broschüren-Formate mit Folgeseiten: {Zeugnistext}
 * füllt zuerst die Zeugnistext-Felder der Startseiten (inkl. Auffangfelder
 * „nur bei Überhang"), der Rest fließt in Kopien der Folgeseite(n) – beliebig
 * oft wiederholt, bis der Text vollständig ausgegeben ist. Startseiten
 * erscheinen genau einmal; nicht benötigte Folgeseiten entfallen ganz.
 *
 * Umbruch- und Mess-Logik identisch zu FormatController/ZeugnisRenderer
 * (dompdf-Schriftvermessung); der Designer spiegelt die Verteilung clientseitig.
 */
class Textverteilung
{
    private const MM_TO_PT = 2.83465;

    /** Sicherheitsgrenze gegen Endlos-Wachstum (jedes Feld nimmt pro Kopie ≥ 1 Zeile). */
    public const MAX_FOLGESEITEN = 100;

    /**
     * @param  array<int,array<string,mixed>>  $elemente  Layout (Variablen/Bilder bereits aufgelöst)
     * @param  string  $text  der komplette {Zeugnistext}
     * @param  array<int,string>  $rollen  je Design-Seite 'start'|'folge' (Index 0 = Seite 1)
     * @param  object|null  $fontMetrics  dompdf-FontMetrics (null = Näherungs-Schätzung)
     * @return array{seiten:array<int,array<int,array<string,mixed>>>,rest:int,folgeseiten:int}
     *         'seiten' = physische Seiten in Ausgabe-Reihenfolge (je Seite die Elemente)
     */
    public static function verteilen(array $elemente, string $text, array $rollen, $fontMetrics = null): array
    {
        $anzahl = max(1, count($rollen));
        $rolle  = fn (int $n): string => $rollen[$n - 1] ?? 'start';

        // Element-Seite auf den definierten Bereich klammern.
        foreach ($elemente as $i => $e) {
            $elemente[$i]['seite'] = min($anzahl, max(1, (int) ($e['seite'] ?? 1)));
        }

        $tb = [];
        foreach ($elemente as $i => $e) {
            if (($e['typ'] ?? '') === 'textbereich') {
                $tb[] = $i;
            }
        }

        if (empty($tb) || trim($text) === '') {
            return ['seiten' => self::seitenAufbauen($elemente, $rollen, []), 'rest' => 0, 'folgeseiten' => 0];
        }

        $byPos = fn ($a, $b) => [$elemente[$a]['seite'], $elemente[$a]['y'] ?? 0, $elemente[$a]['x'] ?? 0]
            <=> [$elemente[$b]['seite'], $elemente[$b]['y'] ?? 0, $elemente[$b]['x'] ?? 0];

        $startTb = array_values(array_filter($tb, fn ($i) => $rolle($elemente[$i]['seite']) === 'start'));

        // Folgeseiten mit mindestens einem Textbereich (nur die können sich wiederholen).
        $folgeSeiten = [];
        foreach ($tb as $i) {
            $s = $elemente[$i]['seite'];
            if ($rolle($s) === 'folge' && ! in_array($s, $folgeSeiten, true)) {
                $folgeSeiten[] = $s;
            }
        }
        sort($folgeSeiten);

        // Zeilenumbruch an der schmalsten Spalte aller Zeugnistext-Felder.
        $sortiert = $tb;
        usort($sortiert, $byPos);
        $first  = $elemente[$sortiert[0]];
        $size   = (float) ($first['size'] ?? 11);
        $family = $first['font'] ?? 'DejaVu Sans';
        $font   = ($fontMetrics && method_exists($fontMetrics, 'getFont')) ? $fontMetrics->getFont($family, 'normal') : null;
        $mess   = function (string $s) use ($fontMetrics, $font, $size) {
            if ($fontMetrics && $font) {
                return (float) $fontMetrics->getTextWidth($s, $font, $size);
            }

            return mb_strlen($s) * $size * 0.52; // Fallback-Schätzung
        };

        $minBreitePt = min(array_map(fn ($i) => ((float) ($elemente[$i]['w'] ?? 40)) * self::MM_TO_PT - 4, $tb));
        $zeilen      = self::umbrechen($text, max(10, $minBreitePt), $mess);

        $maxZeilen = fn (array $e): int => max(1, (int) floor(
            ((float) ($e['h'] ?? 10)) * self::MM_TO_PT / (((float) ($e['size'] ?? 11)) * 1.35)
        ));

        // Phase 1: Startseiten – erst nur die festen Felder, bei Überhang inkl. Auffangfelder.
        $fuellen = function (array $order, int $pos) use (&$elemente, $maxZeilen, $zeilen): int {
            foreach ($order as $i) {
                $anteil = array_slice($zeilen, $pos, $maxZeilen($elemente[$i]));
                $pos   += count($anteil);
                $elemente[$i]['inhalt'] = implode("\n", $anteil);
            }

            return $pos;
        };

        $fest = array_values(array_filter($startTb, fn ($i) => empty($elemente[$i]['nurUeberhang'])));
        usort($fest, $byPos);
        $bedingt = array_values(array_filter($startTb, fn ($i) => ! empty($elemente[$i]['nurUeberhang'])));

        foreach ($startTb as $i) {
            $elemente[$i]['inhalt'] = '';
        }
        $pos = $fuellen($fest, 0);
        if ($pos < count($zeilen) && ! empty($bedingt)) {
            $alleStart = $startTb;
            usort($alleStart, $byPos);
            $pos = $fuellen($alleStart, 0);
        }

        // Phase 2: Kopien der Folgeseite(n), bis der Text durch ist.
        $instanzen = [];
        if ($pos < count($zeilen) && ! empty($folgeSeiten)) {
            $z = 0;
            while ($pos < count($zeilen) && count($instanzen) < self::MAX_FOLGESEITEN) {
                $vorlage = $folgeSeiten[$z % count($folgeSeiten)];
                $kopie   = array_values(array_filter($elemente, fn ($e) => $e['seite'] === $vorlage));

                $idx = [];
                foreach ($kopie as $k => $e) {
                    if (($e['typ'] ?? '') === 'textbereich') {
                        $idx[] = $k;
                    }
                }
                usort($idx, fn ($a, $b) => [$kopie[$a]['y'] ?? 0, $kopie[$a]['x'] ?? 0] <=> [$kopie[$b]['y'] ?? 0, $kopie[$b]['x'] ?? 0]);

                foreach ($idx as $k) {
                    $anteil = array_slice($zeilen, $pos, $maxZeilen($kopie[$k]));
                    $pos   += count($anteil);
                    $kopie[$k]['inhalt'] = implode("\n", $anteil);
                }

                $instanzen[] = $kopie;
                $z++;
            }
        }

        return [
            'seiten'      => self::seitenAufbauen($elemente, $rollen, $instanzen),
            'rest'        => count($zeilen) - $pos,
            'folgeseiten' => count($instanzen),
        ];
    }

    /**
     * Physische Seiten in Ausgabe-Reihenfolge: Startseiten genau einmal an ihrer
     * Position; an der Stelle der ersten Folgeseite stehen alle Kopien (0..n).
     *
     * @param  array<int,array<string,mixed>>  $elemente
     * @param  array<int,string>  $rollen
     * @param  array<int,array<int,array<string,mixed>>>  $instanzen
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function seitenAufbauen(array $elemente, array $rollen, array $instanzen): array
    {
        $anzahl     = max(1, count($rollen));
        $ersteFolge = null;
        foreach ($rollen as $i => $r) {
            if ($r === 'folge') {
                $ersteFolge = $i + 1;
                break;
            }
        }

        $seiten = [];
        for ($n = 1; $n <= $anzahl; $n++) {
            if (($rollen[$n - 1] ?? 'start') === 'folge') {
                if ($n === $ersteFolge) {
                    foreach ($instanzen as $inst) {
                        $seiten[] = $inst;
                    }
                }
                continue;
            }
            $seiten[] = array_values(array_filter($elemente, fn ($e) => ($e['seite'] ?? 1) === $n));
        }

        // Nie ganz ohne Seite (z. B. nur Folgeseiten + leerer Text).
        if (empty($seiten)) {
            $seiten[] = [];
        }

        return $seiten;
    }

    /**
     * Bricht Text in sichtbare Zeilen um (respektiert \n als Absätze/Leerzeilen) –
     * identisch zu FormatController/ZeugnisRenderer.
     *
     * @return array<int,string>
     */
    private static function umbrechen(string $text, float $breitePt, callable $mess): array
    {
        $zeilen = [];

        foreach (explode("\n", $text) as $absatz) {
            if (trim($absatz) === '') {
                $zeilen[] = '';
                continue;
            }

            $woerter = preg_split('/\s+/u', trim($absatz));
            $aktuell = '';

            foreach ($woerter as $w) {
                $probe = $aktuell === '' ? $w : $aktuell . ' ' . $w;
                if ($aktuell === '' || $mess($probe) <= $breitePt) {
                    $aktuell = $probe;
                } else {
                    $zeilen[] = $aktuell;
                    $aktuell  = $w;
                }
            }

            $zeilen[] = $aktuell;
        }

        return $zeilen;
    }
}
