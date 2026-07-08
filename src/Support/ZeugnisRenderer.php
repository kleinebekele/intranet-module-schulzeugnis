<?php

namespace Intranet\Modules\Schulzeugnis\Support;

use Illuminate\Support\Facades\Storage;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;

/**
 * Rendert ein befülltes Zeugnis durch das Format-Layout: echte Schülerdaten,
 * {Zeugnistext} aus den Abschnitten, Bilder als data-URI. Passt der Text nicht,
 * wird die Schriftgröße der Zeugnistext-Felder automatisch verkleinert (bis 8 pt);
 * reicht auch das nicht, meldet analyse() einen Überlauf.
 *
 * Die Textverteilung ist bewusst identisch zum Designer/FormatController
 * (dompdf-Schriftvermessung, Auffangfelder „nur bei Überhang").
 */
class ZeugnisRenderer
{
    private const MM_TO_PT = 2.83465;
    private const MIN_GROESSE = 8;
    private const SCHULNAME = 'Freie Waldorfschule Gütersloh';

    /**
     * Vollständige Render-Daten für die Blade-Vorlage schulzeugnis::formate.render.
     *
     * @return array{seiten:array,daten:array,groesse:int,ueberlauf:bool,passtBei:?int,basis:int}
     */
    public function render(Zeugnis $zeugnis): array
    {
        $format = $this->format($zeugnis);
        $daten  = $this->daten($zeugnis);
        $layout = $format?->layout ?: [];

        $elemente = $this->ersetzeVariablen($this->resolveBilder($layout), $daten);
        $basis    = $this->basisGroesse($elemente);

        $ergebnis = $this->verteileMitShrink($elemente, (string) ($daten['zeugnistext'] ?? ''), $basis);

        return [
            'seiten'   => $this->baueSeiten($format, $ergebnis['elemente']),
            'daten'    => $daten,
            'groesse'  => $ergebnis['groesse'],
            'ueberlauf' => $ergebnis['ueberlauf'],
            'passtBei' => $ergebnis['ueberlauf'] ? null : $ergebnis['groesse'],
            'basis'    => $basis,
        ];
    }

    /**
     * Nur die Überlauf-Analyse (für die Tabellen-Spalte) – günstig: prüft zuerst
     * die Basisgröße, die Schrumpf-Schleife läuft nur bei tatsächlichem Überlauf.
     *
     * @return array{status:'leer'|'ok'|'verkleinert'|'ueberlauf',passtBei:?int,basis:int}
     */
    public function analyse(Zeugnis $zeugnis): array
    {
        $format = $this->format($zeugnis);
        $layout = $format?->layout ?: [];
        $text   = (string) ($this->daten($zeugnis)['zeugnistext'] ?? '');

        $hatTextbereich = collect($layout)->contains(fn ($e) => ($e['typ'] ?? '') === 'textbereich');
        if (! $hatTextbereich || trim($text) === '') {
            return ['status' => 'leer', 'passtBei' => null, 'basis' => 0];
        }

        $basis = $this->basisGroesse($layout);

        if ($this->fuelle($layout, $text, $basis)['rest'] <= 0) {
            return ['status' => 'ok', 'passtBei' => $basis, 'basis' => $basis];
        }

        for ($size = $basis - 1; $size >= self::MIN_GROESSE; $size--) {
            if ($this->fuelle($layout, $text, $size)['rest'] <= 0) {
                return ['status' => 'verkleinert', 'passtBei' => $size, 'basis' => $basis];
            }
        }

        return ['status' => 'ueberlauf', 'passtBei' => null, 'basis' => $basis];
    }

    private function format(Zeugnis $zeugnis): ?Format
    {
        if ($zeugnis->relationLoaded('format') && $zeugnis->format) {
            return $zeugnis->format;
        }

        return $zeugnis->format_id ? Format::find($zeugnis->format_id) : null;
    }

    /**
     * Baut die Datenwerte (Bindungen + {Zeugnistext}) aus dem Zeugnis.
     * Ist das Zeugnis abgeschlossen, gelten die eingefrorenen Schnappschüsse.
     *
     * @return array<string,mixed>
     */
    public function daten(Zeugnis $zeugnis): array
    {
        $zeugnis->loadMissing(['schueler.klasse.schuljahr', 'schueler.klasse.klassenlehrer', 'abschnitte.fach']);
        $schueler = $zeugnis->schueler;
        $klasse   = $schueler?->klasse;
        $schuljahr = $klasse?->schuljahr;

        $abgeschlossen = $zeugnis->istAbgeschlossen();
        $name      = $abgeschlossen ? $zeugnis->ausgestellt_auf_name : $schueler?->fullName();
        $gebDatum  = $abgeschlossen ? $zeugnis->ausgestellt_geburtsdatum : $schueler?->geburtsdatum;
        $gebOrt    = $abgeschlossen ? $zeugnis->ausgestellt_geburtsort : $schueler?->geburtsort;

        $abschnitte = $zeugnis->abschnitte->sortBy([['reihenfolge', 'asc'], ['id', 'asc']]);
        $haupttext  = (string) ($abschnitte->firstWhere('typ', Abschnitt::TYP_HAUPTTEXT)?->inhalt ?? '');

        $fachtexte = $abschnitte
            ->whereIn('typ', [Abschnitt::TYP_FACHTEXT, Abschnitt::TYP_NOTE])
            ->map(fn ($a) => [
                'fach' => $a->fach?->name ?? '',
                'text' => (string) ($a->typ === Abschnitt::TYP_NOTE ? trim(($a->note ? 'Note: ' . $a->note . '  ' : '') . ($a->inhalt ?? '')) : ($a->inhalt ?? '')),
            ])
            ->filter(fn ($f) => trim($f['text']) !== '')
            ->values()
            ->all();

        // {Zeugnistext} = Haupttext + je Fach „Fach\nText", der Reihe nach.
        $teile = [];
        if (trim($haupttext) !== '') {
            $teile[] = $haupttext;
        }
        foreach ($fachtexte as $f) {
            $teile[] = trim($f['fach'] . "\n" . $f['text']);
        }
        $zeugnistext = implode("\n\n", $teile);

        $gebDatumStr = $gebDatum ? $gebDatum->format('d.m.Y') : '';
        $geboren = trim(($gebDatumStr ? 'geboren am ' . $gebDatumStr : '') . ($gebOrt ? ' in ' . $gebOrt : ''));

        $ausgabe = $schuljahr?->ausgabe_datum
            ? 'Gütersloh, den ' . $schuljahr->ausgabe_datum->format('d.m.Y')
            : '';

        return [
            'schulname'             => self::SCHULNAME,
            'titel'                 => 'Zeugnis ' . ($schuljahr?->name ?? ''),
            'schueler.name'         => $name,
            'schueler.geboren'      => $geboren,
            'schueler.geburtsdatum' => $gebDatumStr,
            'schueler.geburtsort'   => $gebOrt,
            'klasse.zeile'          => $klasse ? 'Klasse ' . $klasse->name : '',
            'klasse'                => $klasse?->name ?? '',
            'schuljahr'             => $schuljahr?->name ?? '',
            'haupttext'             => $haupttext,
            'fachtexte'             => $fachtexte,
            'zeugnistext'           => $zeugnistext,
            'zeugnisspruch'         => '',
            'ausgabe.zeile'         => $ausgabe,
            'unterschrift'          => $klasse?->klassenlehrer?->fullName() ?: 'Klassenlehrer/in',
        ];
    }

    /** Basis-Schriftgröße = kleinste Größe der Zeugnistext-Felder (Designer koppelt sie ohnehin). */
    private function basisGroesse(array $elemente): int
    {
        $groessen = collect($elemente)
            ->filter(fn ($e) => ($e['typ'] ?? '') === 'textbereich')
            ->map(fn ($e) => (int) ($e['size'] ?? 11));

        return $groessen->isEmpty() ? 11 : (int) $groessen->min();
    }

    /**
     * Verteilt den Text und verkleinert die Zeugnistext-Felder bei Bedarf.
     *
     * @return array{elemente:array,groesse:int,ueberlauf:bool}
     */
    private function verteileMitShrink(array $elemente, string $text, int $basis): array
    {
        for ($size = $basis; $size >= self::MIN_GROESSE; $size--) {
            $res = $this->fuelle($elemente, $text, $size);
            if ($res['rest'] <= 0) {
                return ['elemente' => $res['elemente'], 'groesse' => $size, 'ueberlauf' => false];
            }
        }

        $res = $this->fuelle($elemente, $text, self::MIN_GROESSE);

        return ['elemente' => $res['elemente'], 'groesse' => self::MIN_GROESSE, 'ueberlauf' => true];
    }

    /**
     * Kern der Textverteilung (identisch zum FormatController), mit einheitlicher
     * Größen-Übersteuerung für alle Zeugnistext-Felder und Rückgabe des Überhangs.
     *
     * @return array{elemente:array,rest:int}
     */
    private function fuelle(array $elemente, string $text, int $groesse): array
    {
        $alle = [];
        foreach ($elemente as $i => $e) {
            if (($e['typ'] ?? '') === 'textbereich') {
                $elemente[$i]['size'] = $groesse; // einheitliche Größe (Auto-Shrink)
                $alle[] = $i;
            }
        }

        if (empty($alle) || trim($text) === '') {
            return ['elemente' => $elemente, 'rest' => 0];
        }

        $mmToPt = self::MM_TO_PT;
        $fm     = $this->fontMetrics();

        $byPos = fn ($a, $b) => [$elemente[$a]['seite'] ?? 1, $elemente[$a]['y'] ?? 0, $elemente[$a]['x'] ?? 0]
            <=> [$elemente[$b]['seite'] ?? 1, $elemente[$b]['y'] ?? 0, $elemente[$b]['x'] ?? 0];

        $fest = array_values(array_filter($alle, fn ($i) => empty($elemente[$i]['nurUeberhang'])));
        usort($fest, $byPos);
        $bedingt = array_values(array_filter($alle, fn ($i) => ! empty($elemente[$i]['nurUeberhang'])));

        $verteile = function (array $order) use (&$elemente, $mmToPt, $fm, $text) {
            $first  = $elemente[$order[0]];
            $size   = (float) ($first['size'] ?? 11);
            $family = $first['font'] ?? 'DejaVu Sans';
            $font   = ($fm && method_exists($fm, 'getFont')) ? $fm->getFont($family, 'normal') : null;
            $mess = function (string $s) use ($fm, $font, $size) {
                if ($fm && $font) {
                    return (float) $fm->getTextWidth($s, $font, $size);
                }

                return mb_strlen($s) * $size * 0.52;
            };

            $minBreitePt = min(array_map(fn ($i) => ((float) ($elemente[$i]['w'] ?? 40)) * $mmToPt - 4, $order));
            $zeilen = $this->umbrechen($text, max(10, $minBreitePt), $mess);

            $pos = 0;
            $inhalt = [];
            foreach ($order as $i) {
                $hPt       = ((float) ($elemente[$i]['h'] ?? 10)) * $mmToPt;
                $sz        = (float) ($elemente[$i]['size'] ?? 11);
                $maxZeilen = max(1, (int) floor($hPt / ($sz * 1.35)));
                $anteil    = array_slice($zeilen, $pos, $maxZeilen);
                $pos      += count($anteil);
                $inhalt[$i] = implode("\n", $anteil);
            }

            return ['inhalt' => $inhalt, 'rest' => count($zeilen) - $pos];
        };

        if (! empty($fest)) {
            $res = $verteile($fest);
            if ($res['rest'] > 0 && ! empty($bedingt)) {
                $alleSort = $alle;
                usort($alleSort, $byPos);
                $res = $verteile($alleSort);
            }
        } else {
            $alleSort = $alle;
            usort($alleSort, $byPos);
            $res = $verteile($alleSort);
        }

        foreach ($alle as $i) {
            $elemente[$i]['inhalt'] = $res['inhalt'][$i] ?? '';
        }

        return ['elemente' => $elemente, 'rest' => (int) $res['rest']];
    }

    /** @return array<int,string> */
    private function umbrechen(string $text, float $breitePt, callable $mess): array
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

    private function fontMetrics()
    {
        try {
            return app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string,string> Anzeigename => Datenschlüssel */
    private function variablen(): array
    {
        return [
            'Name'          => 'schueler.name',
            'Geburtsdatum'  => 'schueler.geburtsdatum',
            'Geburtsort'    => 'schueler.geburtsort',
            'Klasse'        => 'klasse',
            'Schuljahr'     => 'schuljahr',
            'Schulname'     => 'schulname',
            'Zeugnisspruch' => 'zeugnisspruch',
        ];
    }

    private function ersetzeVariablen(array $elemente, array $daten): array
    {
        $map = $this->variablen();

        return array_map(function ($e) use ($daten, $map) {
            if (($e['typ'] ?? '') === 'text' && ! empty($e['text'])) {
                $e['text'] = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($daten, $map) {
                    return isset($map[$m[1]]) ? (string) ($daten[$map[$m[1]]] ?? $m[0]) : $m[0];
                }, $e['text']);
            }

            return $e;
        }, $elemente);
    }

    private function resolveBilder(array $elemente): array
    {
        return array_map(function ($e) {
            if (($e['typ'] ?? '') === 'bild' && ! empty($e['bild'])) {
                $e['src'] = $this->bildDataUri($e['bild']);
            }

            return $e;
        }, $elemente);
    }

    private function bildDataUri(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:' . ($disk->mimeType($path) ?: 'image/png') . ';base64,' . base64_encode($disk->get($path));
    }

    /** @return array<int,array<string,mixed>> */
    private function baueSeiten(?Format $format, array $elemente): array
    {
        if ($format && $format->broschuere) {
            $seite = fn (int $n) => array_values(array_filter($elemente, fn ($e) => (int) ($e['seite'] ?? 1) === $n));

            return [
                ['b' => 420, 'h' => 297, 'panels' => [
                    ['x' => 0,   'y' => 0, 'w' => 210, 'h' => 297, 'elemente' => $seite(4)],
                    ['x' => 210, 'y' => 0, 'w' => 210, 'h' => 297, 'elemente' => $seite(1)],
                ]],
                ['b' => 420, 'h' => 297, 'panels' => [
                    ['x' => 0,   'y' => 0, 'w' => 210, 'h' => 297, 'elemente' => $seite(2)],
                    ['x' => 210, 'y' => 0, 'w' => 210, 'h' => 297, 'elemente' => $seite(3)],
                ]],
            ];
        }

        $s = $format ? $format->seiteMm() : ['b' => 210, 'h' => 297];

        return [
            ['b' => $s['b'], 'h' => $s['h'], 'panels' => [
                ['x' => 0, 'y' => 0, 'w' => $s['b'], 'h' => $s['h'], 'elemente' => $elemente],
            ]],
        ];
    }
}
