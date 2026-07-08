<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Smalot\PdfParser\Parser;

/**
 * Gemeinsame Helfer der „Alte …-umwandeln"-Werkzeuge: PDF-Text je Seite lesen,
 * Zeugnis-Grenzen an der ersten Seite (Geburtszeile) erkennen, Schülername
 * bestimmen sowie Dateinamen säubern / temporäre Dateien aufräumen.
 */
trait VerarbeitetAlteZeugnisse
{
    /**
     * Text je Seite (0-basiert) über den PDF-Parser lesen. Null, wenn sich die
     * PDF nicht als Text lesen lässt (z. B. reine Scan-PDF).
     *
     * @return array<int,string>|null
     */
    protected function seitenTexte(string $pfad): ?array
    {
        try {
            $texte = [];
            foreach ((new Parser())->parseFile($pfad)->getPages() as $i => $page) {
                $texte[$i] = (string) $page->getText();
            }

            return $texte;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Zeugnis-Gruppen anhand der ersten Seiten bestimmen. Eine erste Seite
     * enthält die Geburtszeile („… geboren am …"); alle Folgeseiten bis zur
     * nächsten ersten Seite gehören zum selben Zeugnis.
     *
     * @param  array<int,string>  $seitenTexte
     * @return array<int,array{start:int,laenge:int}>
     */
    protected function zeugnisGruppen(array $seitenTexte): array
    {
        $starts = [];
        foreach ($seitenTexte as $i => $text) {
            if ($this->istErsteSeite($text)) {
                $starts[] = $i;
            }
        }

        // Sicherstellen, dass die erste Seite der Datei ein Gruppenanfang ist.
        if ($starts === [] || $starts[0] !== 0) {
            array_unshift($starts, 0);
        }

        $anzahl = count($seitenTexte);
        $gruppen = [];
        foreach ($starts as $k => $start) {
            $ende = $starts[$k + 1] ?? $anzahl;
            $gruppen[] = ['start' => $start, 'laenge' => $ende - $start];
        }

        return $gruppen;
    }

    /** Erste Seite eines Zeugnisses? Erkennbar an der Geburtszeile. */
    protected function istErsteSeite(string $text): bool
    {
        return (bool) preg_match('/geboren\s+am/iu', $text);
    }

    /**
     * Schülername von der ersten Zeugnis-Seite. Muster des alten Programms:
     *   Julian Lechthoff
     *   - geboren am 01.08.2018 in Bielefeld -
     *   erhält für die Klasse 01 im Schuljahr 2025 / 2026 folgendes Zeugnis:
     * Der Name ist also die Zeile direkt ÜBER „… geboren am …" (Fallback: erste
     * nicht-leere Zeile).
     */
    protected function nameAusZeugnis(string $text): ?string
    {
        $zeilen = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
            fn ($z) => $z !== ''
        ));

        if ($zeilen === []) {
            return null;
        }

        foreach ($zeilen as $i => $zeile) {
            if ($i > 0 && preg_match('/geboren\s+am/iu', $zeile)) {
                return $zeilen[$i - 1];
            }
        }

        return $zeilen[0];
    }

    /** Dateiname säubern: entfernt dateisystem-/header-kritische Zeichen, behält Umlaute. */
    protected function sichererName(string $name): string
    {
        $name = preg_replace('~[\\\\/:*?"<>|\x00-\x1F]~u', '', $name) ?? '';

        return trim(mb_substr($name, 0, 100));
    }

    /** Temporäre Ausgabe-PDFs älter als eine Stunde entfernen. */
    protected function alteDateienAufraeumen(string $dir): void
    {
        foreach (glob($dir . '/*.pdf') ?: [] as $datei) {
            if (is_file($datei) && filemtime($datei) < time() - 3600) {
                @unlink($datei);
            }
        }
    }
}
