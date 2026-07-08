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

        if ($seiten === 0 || $seiten % 4 !== 0) {
            return back()->with('error',
                "Die hochgeladene PDF hat {$seiten} Seiten. Das ist nicht durch 4 teilbar – "
                . 'pro Zeugnis müssen genau 4 A4-Seiten vorliegen. Bitte die Original-Datei prüfen.');
        }

        for ($gruppe = 0; $gruppe < $seiten / 4; $gruppe++) {
            $basis = $gruppe * 4; // 0-basiert; Quellseiten sind 1-basiert
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
            'zeugnisse'   => (int) ($seiten / 4),
            'rauten'      => $this->rautenSeiten($pfad),
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
     * Zeugnisse (4er-Gruppen), in deren Text eine Raute „#" vorkommt – mit dem
     * Schülernamen (von der ersten Seite, unter der „Zeugnis"-Überschrift) und den
     * betroffenen Original-Seiten.
     *
     * @return array{ok:bool,treffer:array<int,array{zeugnis:int,name:?string,seiten:array<int,int>}>,fehler:?string}
     */
    private function rautenSeiten(string $pfad): array
    {
        try {
            $pages = (new Parser())->parseFile($pfad)->getPages();

            $proZeugnis = [];
            foreach ($pages as $i => $page) {
                if (str_contains((string) $page->getText(), '#')) {
                    $proZeugnis[intdiv($i, 4)][] = $i + 1;
                }
            }

            $treffer = [];
            foreach ($proZeugnis as $gruppe => $seiten) {
                $ersteSeite = $pages[$gruppe * 4] ?? null;
                $treffer[] = [
                    'zeugnis' => $gruppe + 1,
                    'name'    => $ersteSeite ? $this->nameAusZeugnis((string) $ersteSeite->getText()) : null,
                    'seiten'  => $seiten,
                ];
            }

            return ['ok' => true, 'treffer' => $treffer, 'fehler' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'treffer' => [], 'fehler' => $e->getMessage()];
        }
    }

    /** Schülername = die Zeile direkt unter der „Zeugnis"-Überschrift (heuristisch). */
    private function nameAusZeugnis(string $text): ?string
    {
        $zeilen = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
            fn ($z) => $z !== ''
        ));

        foreach ($zeilen as $i => $zeile) {
            if (preg_match('/^Zeugnis$/iu', $zeile) && isset($zeilen[$i + 1])) {
                return $zeilen[$i + 1];
            }
        }

        return null;
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
