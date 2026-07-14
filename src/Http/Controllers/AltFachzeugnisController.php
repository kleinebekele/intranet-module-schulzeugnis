<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intranet\Modules\Schulzeugnis\Support\VerarbeitetAlteZeugnisse;
use setasign\Fpdi\Fpdi;

/**
 * Hilfswerkzeug: PDF alter Fachzeugnisse (normale A4-Seiten, beidseitig gedruckt)
 * duplex-tauglich machen. Jedes Fachzeugnis mit UNGERADER Seitenzahl bekommt eine
 * Leerseite angehängt, damit beim beidseitigen Druck das nächste Fachzeugnis
 * wieder auf der Vorderseite eines neuen Blattes beginnt. Die Grenzen werden – wie
 * beim A3-Werkzeug – an der ersten Seite (Geburtszeile) erkannt.
 */
class AltFachzeugnisController
{
    use VerarbeitetAlteZeugnisse;

    public function umwandeln(Request $request)
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:102400'],
        ], [], ['pdf' => 'PDF-Datei']);

        $pfad = $request->file('pdf')->getRealPath();

        // Text je Seite lesen – daran erkennen wir die Fachzeugnis-Grenzen.
        $seitenTexte = $this->seitenTexte($pfad);

        if ($seitenTexte === null) {
            return back()->with('error',
                'Der Text der PDF ließ sich nicht lesen (vermutlich eine reine Scan-PDF). '
                . 'Ohne lesbaren Text lassen sich die Grenzen der einzelnen Fachzeugnisse nicht erkennen.');
        }


        $pdf = new Fpdi('P', 'mm', 'A4');
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

        $gruppen = $this->zeugnisGruppen($seitenTexte);

        // Jede Gruppe übernehmen; bei ungerader Seitenzahl eine Leerseite anhängen.
        $ergaenzt = [];
        foreach ($gruppen as $g) {
            for ($s = 0; $s < $g['laenge']; $s++) {
                $seiteNr = $g['start'] + $s + 1; // 1-basiert
                if ($seiteNr > $seiten) {
                    break;
                }
                $this->seiteUebernehmen($pdf, $pdf->importPage($seiteNr));
            }

            if ($g['laenge'] % 2 === 1) {
                // Leerseite im Format der ersten Seite dieses Fachzeugnisses.
                $this->leerseite($pdf, $pdf->importPage($g['start'] + 1));
                $ergaenzt[] = [
                    'name'       => $this->nameAusZeugnis($seitenTexte[$g['start']] ?? ''),
                    'anzahl'     => $g['laenge'],
                    'startSeite' => $g['start'] + 1,
                ];
            }
        }

        // Umgewandelte PDF temporär ablegen (Download über Token auf der Ergebnisseite).
        $dir = storage_path('app/schulzeugnis-tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->alteDateienAufraeumen($dir);

        $token = Str::random(40);
        $pdf->Output('F', $dir . '/' . $token . '.pdf');

        // Ausgabename = Eingabename + "_duplex".
        $basis = $this->sichererName(pathinfo($request->file('pdf')->getClientOriginalName(), PATHINFO_FILENAME));
        $ausgabeName = ($basis !== '' ? $basis : 'fachzeugnisse') . '_duplex.pdf';

        return view('schulzeugnis::altfachzeugnisse.ergebnis', [
            'token'       => $token,
            'seiten'      => $seiten,
            'fachzeugnisse' => count($gruppen),
            'ergaenzt'    => $ergaenzt,
            'ausgabeName' => $ausgabeName,
        ]);
    }

    /** Umgewandelte PDF herunterladen (und danach löschen). */
    public function download(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);

        $pfad = storage_path('app/schulzeugnis-tmp/' . $token . '.pdf');

        if (! is_file($pfad)) {
            return redirect()->route('module.schulzeugnis.altumwandeln.index')
                ->with('error', 'Die umgewandelte Datei ist nicht mehr verfügbar (abgelaufen). Bitte erneut umwandeln.');
        }

        $name = $this->sichererName(pathinfo((string) $request->query('name'), PATHINFO_FILENAME));
        $name = ($name !== '' ? $name : 'fachzeugnisse-duplex') . '.pdf';

        return response()->download($pfad, $name, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend();
    }

    /**
     * Erste Seite eines Fachzeugnisses. Sie trägt oben Name + Geburtszeile und
     * darunter den festen Kopf „… ist Bestandteil des Zeugnisses …". Dieser Kopf
     * ist der zuverlässigste Anker – er kann in keinem Beurteilungstext zufällig
     * vorkommen (anders als eine Geburts-Erwähnung).
     */
    protected function istErsteSeite(string $text): bool
    {
        return (bool) preg_match('/Bestandteil des Zeugnisses/iu', $text);
    }

    /** Eine importierte Quellseite formatgetreu übernehmen. */
    private function seiteUebernehmen(Fpdi $pdf, string $template): void
    {
        $groesse = $pdf->getTemplateSize($template);
        $pdf->AddPage($groesse['orientation'], [$groesse['width'], $groesse['height']]);
        $pdf->useTemplate($template, 0, 0, $groesse['width'], $groesse['height']);
    }

    /** Eine leere Seite im Format der übergebenen Vorlage anhängen. */
    private function leerseite(Fpdi $pdf, string $template): void
    {
        $groesse = $pdf->getTemplateSize($template);
        $pdf->AddPage($groesse['orientation'], [$groesse['width'], $groesse['height']]);
    }
}
