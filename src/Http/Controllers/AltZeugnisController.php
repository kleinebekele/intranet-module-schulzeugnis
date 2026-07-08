<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use setasign\Fpdi\Fpdi;

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

        $name = 'zeugnisse-a3-broschuere-' . now()->format('Y-m-d-His') . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
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
