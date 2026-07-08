<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

/**
 * Hilfswerkzeug: PDF des alten Zeugnisprogramms (je 4 A4-Seiten = ein Zeugnis)
 * in eine A3-Broschüre umschießen. Pro 4er-Gruppe entstehen zwei A3-Querbögen:
 *   Bogen 1: links = Seite 4, rechts = Seite 1
 *   Bogen 2: links = Seite 2, rechts = Seite 3
 * (klassische Falzbogen-Anordnung eines 4-seitigen Hefts).
 */
class AltZeugnisController
{
    private const A4_BREITE = 210.0;
    private const A4_HOEHE = 297.0;

    public function form()
    {
        return view('schulzeugnis::altzeugnisse.form');
    }

    public function umwandeln(Request $request)
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:102400'],
        ], [], ['pdf' => 'PDF-Datei']);

        $pfad = $request->file('pdf')->getRealPath();

        // Text je Seite lesen – daran erkennen wir die Zeugnis-Grenzen (jede erste
        // Seite enthält die Geburtszeile) und später die Rauten. Null bei Scan-PDFs.
        $seitenTexte = $this->seitenTexte($pfad);

        $pdf = new Fpdi('L', 'mm', 'A3'); // A3 quer (420 × 297 mm)
        $pdf->setAutoPageBreak(false);

        try {
            $seiten = $pdf->setSourceFile($pfad);
        } catch (\Throwable $e) {
            return back()->with('error',
                'Die PDF konnte nicht gelesen werden – vermutlich eine zu neue/komprimierte PDF-Version. '
                . 'Bitte die Datei einmal als "PDF (Version 1.4)" bzw. über "Drucken → Als PDF speichern" neu ausgeben und erneut hochladen. '
                . '(Technisch: ' . $e->getMessage() . ')');
        }

        if ($seiten === 0) {
            return back()->with('error', 'Die hochgeladene PDF enthält keine Seiten.');
        }

        // Zeugnis-Gruppen bestimmen: bei lesbarem Text automatisch an den ersten
        // Seiten, sonst strikt je 4 Seiten (dann muss die Seitenzahl durch 4 teilbar sein).
        if ($seitenTexte !== null) {
            $gruppen = $this->zeugnisGruppen($seitenTexte);
        } else {
            if ($seiten % 4 !== 0) {
                return back()->with('error',
                    "Die hochgeladene PDF hat {$seiten} Seiten und ihr Text ließ sich nicht lesen, "
                    . 'sodass die Zeugnis-Grenzen nicht automatisch erkannt werden konnten. '
                    . 'Ohne lesbaren Text müssen es genau 4 A4-Seiten pro Zeugnis sein (durch 4 teilbar).');
            }
            $gruppen = [];
            for ($i = 0; $i < $seiten; $i += 4) {
                $gruppen[] = ['start' => $i, 'laenge' => 4];
            }
        }

        // In gültige (genau 4 Seiten) und entfernte Zeugnisse aufteilen.
        $gueltig = [];
        $entfernt = [];
        foreach ($gruppen as $g) {
            if ($g['laenge'] === 4 && $g['start'] + 4 <= $seiten) {
                $gueltig[] = $g;
            } else {
                $entfernt[] = [
                    'name'       => $seitenTexte !== null ? $this->nameAusZeugnis($seitenTexte[$g['start']] ?? '') : null,
                    'anzahl'     => $g['laenge'],
                    'startSeite' => $g['start'] + 1,
                ];
            }
        }

        if ($gueltig === []) {
            return back()->with('error',
                'Es wurde kein einziges Zeugnis mit genau 4 Seiten gefunden – die Datei wurde nicht umgewandelt.');
        }

        // Gültige Zeugnisse umschießen.
        foreach ($gueltig as $g) {
            $basis = $g['start']; // 0-basiert; Quellseiten sind 1-basiert
            $this->bogen($pdf, $basis + 4, $basis + 1); // Bogen 1: links 4, rechts 1
            $this->bogen($pdf, $basis + 2, $basis + 3); // Bogen 2: links 2, rechts 3
        }

        // Umgewandelte PDF temporär ablegen (Download über Token auf der Ergebnisseite).
        $dir = storage_path('app/schulzeugnis-tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->alteDateienAufraeumen($dir);

        $token = Str::random(40);
        $pdf->Output('F', $dir . '/' . $token . '.pdf');

        // Ausgabename = Eingabename + "_A3_duplex".
        $basis = $this->sichererName(pathinfo($request->file('pdf')->getClientOriginalName(), PATHINFO_FILENAME));
        $ausgabeName = ($basis !== '' ? $basis : 'zeugnisse') . '_A3_duplex.pdf';

        return view('schulzeugnis::altzeugnisse.ergebnis', [
            'token'       => $token,
            'seiten'      => $seiten,
            'zeugnisse'   => count($gueltig),
            'rauten'      => $this->rautenPruefung($seitenTexte, $gueltig),
            'entfernt'    => $entfernt,
            'ausgabeName' => $ausgabeName,
        ]);
    }

    /** Umgewandelte PDF herunterladen (und danach löschen). */
    public function download(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);

        $pfad = storage_path('app/schulzeugnis-tmp/' . $token . '.pdf');

        if (! is_file($pfad)) {
            return redirect()->route('module.schulzeugnis.altzeugnisse.form')
                ->with('error', 'Die umgewandelte Datei ist nicht mehr verfügbar (abgelaufen). Bitte erneut umwandeln.');
        }

        $name = $this->sichererName(pathinfo((string) $request->query('name'), PATHINFO_FILENAME));
        $name = ($name !== '' ? $name : 'zeugnisse-a3-broschuere') . '.pdf';

        return response()->download($pfad, $name, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend();
    }

    /** Dateiname säubern: entfernt dateisystem-/header-kritische Zeichen, behält Umlaute. */
    private function sichererName(string $name): string
    {
        $name = preg_replace('~[\\\\/:*?"<>|\x00-\x1F]~u', '', $name) ?? '';

        return trim(mb_substr($name, 0, 100));
    }

    /**
     * Text je Seite (0-basiert) über den PDF-Parser lesen. Null, wenn sich die
     * PDF nicht als Text lesen lässt (z. B. reine Scan-PDF).
     *
     * @return array<int,string>|null
     */
    private function seitenTexte(string $pfad): ?array
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
    private function zeugnisGruppen(array $seitenTexte): array
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
    private function istErsteSeite(string $text): bool
    {
        return (bool) preg_match('/geboren\s+am/iu', $text);
    }

    /**
     * Gültige Zeugnisse, in deren Text eine Raute „#" vorkommt – mit Schülername
     * (von der ersten Seite) und den betroffenen Original-Seiten.
     *
     * @param  array<int,string>|null  $seitenTexte
     * @param  array<int,array{start:int,laenge:int}>  $gueltig
     * @return array{ok:bool,treffer:array<int,array{name:?string,seiten:array<int,int>}>,fehler:?string}
     */
    private function rautenPruefung(?array $seitenTexte, array $gueltig): array
    {
        if ($seitenTexte === null) {
            return ['ok' => false, 'treffer' => [], 'fehler' => 'PDF-Text nicht lesbar'];
        }

        $treffer = [];
        foreach ($gueltig as $g) {
            $betroffen = [];
            for ($s = 0; $s < 4; $s++) {
                $idx = $g['start'] + $s;
                if (str_contains($seitenTexte[$idx] ?? '', '#')) {
                    $betroffen[] = $idx + 1;
                }
            }
            if ($betroffen !== []) {
                $treffer[] = [
                    'name'   => $this->nameAusZeugnis($seitenTexte[$g['start']] ?? ''),
                    'seiten' => $betroffen,
                ];
            }
        }

        return ['ok' => true, 'treffer' => $treffer, 'fehler' => null];
    }

    /**
     * Schülername von der ersten Zeugnis-Seite. Muster des alten Programms:
     *   Julian Lechthoff
     *   - geboren am 01.08.2018 in Bielefeld -
     *   erhält für die Klasse 01 im Schuljahr 2025 / 2026 folgendes Zeugnis:
     * Der Name ist also die Zeile direkt ÜBER „… geboren am …" (Fallback: erste
     * nicht-leere Zeile).
     */
    private function nameAusZeugnis(string $text): ?string
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

    /** Temporäre Ausgabe-PDFs älter als eine Stunde entfernen. */
    private function alteDateienAufraeumen(string $dir): void
    {
        foreach (glob($dir . '/*.pdf') ?: [] as $datei) {
            if (is_file($datei) && filemtime($datei) < time() - 3600) {
                @unlink($datei);
            }
        }
    }

    /** Einen A3-Querbogen erzeugen: zwei A4-Quellseiten nebeneinander (links | rechts). */
    private function bogen(Fpdi $pdf, int $linkeSeite, int $rechteSeite): void
    {
        $pdf->AddPage('L', 'A3');

        $links = $pdf->importPage($linkeSeite);
        $pdf->useTemplate($links, 0, 0, self::A4_BREITE, self::A4_HOEHE);

        $rechts = $pdf->importPage($rechteSeite);
        $pdf->useTemplate($rechts, self::A4_BREITE, 0, self::A4_BREITE, self::A4_HOEHE);
    }
}
