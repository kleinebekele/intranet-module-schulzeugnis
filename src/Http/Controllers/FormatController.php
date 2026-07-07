<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Verwaltung der Zeugnisformate (Vorlagen). Start mit festen Typen (Text/Noten);
 * ein freier Abschnitts-Baukasten kann später andocken.
 */
class FormatController
{
    public function index()
    {
        $formate = Format::orderBy('name')->get();

        return view('schulzeugnis::formate.index', compact('formate'));
    }

    public function create()
    {
        return view('schulzeugnis::formate.form', ['format' => new Format()]);
    }

    public function store(Request $request)
    {
        $format = Format::create($this->validated($request));

        Protokoll::log('format_angelegt', [
            'beschreibung' => "Zeugnisformat {$format->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.formate.index')
            ->with('status', "Format {$format->name} angelegt.");
    }

    public function edit(Format $format)
    {
        return view('schulzeugnis::formate.form', compact('format'));
    }

    public function update(Request $request, Format $format)
    {
        $alt = $format->name;

        $format->update($this->validated($request));

        Protokoll::log('format_geaendert', [
            'beschreibung' => 'Zeugnisformat bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $format->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.formate.index')
            ->with('status', "Format {$format->name} gespeichert.");
    }

    public function toggle(Format $format)
    {
        $format->update(['aktiv' => ! $format->aktiv]);

        Protokoll::log($format->aktiv ? 'format_reaktiviert' : 'format_archiviert', [
            'beschreibung' => "Zeugnisformat {$format->name} " . ($format->aktiv ? 'reaktiviert' : 'archiviert'),
        ]);

        return redirect()
            ->route('module.schulzeugnis.formate.index')
            ->with('status', "Format {$format->name} " . ($format->aktiv ? 'reaktiviert.' : 'archiviert.'));
    }

    public function destroy(Format $format)
    {
        $verwendet = DB::table('zeugnis_klassen')->where('standard_format_id', $format->id)->exists()
            || DB::table('zeugnis_schuljahr_schueler')->where('format_override_id', $format->id)->exists()
            || DB::table('zeugnisse')->where('format_id', $format->id)->exists();

        if ($verwendet) {
            return redirect()
                ->route('module.schulzeugnis.formate.index')
                ->with('error', "{$format->name} wird bereits verwendet – bitte archivieren statt löschen.");
        }

        $name = $format->name;

        Protokoll::log('format_geloescht', [
            'beschreibung' => "Zeugnisformat {$name} gelöscht",
        ]);

        $format->delete();

        return redirect()
            ->route('module.schulzeugnis.formate.index')
            ->with('status', "Format {$name} gelöscht.");
    }

    /** Vorschau als HTML (im Browser) – identisches Markup wie das PDF. */
    public function vorschau(Format $format)
    {
        return view('schulzeugnis::formate.render', $this->renderDaten($format));
    }

    /** Vorschau als PDF (dompdf) – gleiches Layout, echtes Papierformat. */
    public function pdf(Format $format)
    {
        [$groesse, $lage] = $format->broschuere
            ? ['a3', 'landscape']
            : [$format->seitenformat === 'a3' ? 'a3' : 'a4', $format->ausrichtung === 'quer' ? 'landscape' : 'portrait'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulzeugnis::formate.render', $this->renderDaten($format))
            ->setPaper($groesse, $lage);

        return $pdf->stream("zeugnis-vorschau-{$format->id}.pdf");
    }

    /** Der visuelle Editor (WYSIWYG). */
    public function designer(Format $format)
    {
        return view('schulzeugnis::formate.designer', [
            'format'       => $format,
            'designSeite'  => $format->broschuere ? ['b' => 210, 'h' => 297] : $format->seiteMm(),
            'seitenAnzahl' => $format->seitenAnzahl(),
            'seitenLabels' => $format->broschuere
                ? ['Titelseite', 'innen links', 'innen rechts', 'Rückseite']
                : ['Seite'],
            'elemente'     => $format->layout ?: $this->standardLayout(),
            'bindungen'    => $this->bindungen(),
            'daten'        => $this->beispielDaten(),
        ]);
    }

    /** Layout aus dem Editor entgegennehmen und am Format speichern. */
    public function saveLayout(Request $request, Format $format)
    {
        $roh = $request->input('elemente', []);

        $elemente = collect(is_array($roh) ? $roh : [])
            ->map(fn ($e) => $this->sanitizeElement($e))
            ->filter()
            ->values()
            ->all();

        $format->update(['layout' => $elemente]);

        Protokoll::log('format_layout_gespeichert', [
            'beschreibung' => "Layout von {$format->name} gespeichert ({$format->name})",
        ]);

        return response()->json(['ok' => true, 'anzahl' => count($elemente)]);
    }

    /** Bild/Logo hochladen (auf die public-Disk), gibt Pfad + URL zurück. */
    public function uploadBild(Request $request, Format $format)
    {
        $request->validate([
            'bild' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
        ]);

        $path = $request->file('bild')->store("schulzeugnis/{$format->id}", 'public');

        Protokoll::log('format_bild_hochgeladen', [
            'beschreibung' => "Bild für Format {$format->name} hochgeladen",
        ]);

        return response()->json([
            'ok'   => true,
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ]);
    }

    /** Verfügbare Datenbindungen für die Palette. */
    private function bindungen(): array
    {
        return [
            'schulname'        => 'Schulname',
            'titel'            => 'Titel (Zeugnis + Schuljahr)',
            'schueler.name'    => 'Schüler: Name',
            'schueler.geboren' => 'Schüler: geboren am/in',
            'klasse.zeile'     => 'Klasse',
            'haupttext'        => 'Haupttext (Klassenlehrer)',
            'fachtexte'        => 'Fachtexte (Liste)',
            'ausgabe.zeile'    => 'Ort / Datum',
            'unterschrift'     => 'Unterschrift-Label',
        ];
    }

    /**
     * Ein Element aus dem Editor säubern/typisieren, bevor es gespeichert wird.
     *
     * @param  mixed  $e
     * @return array<string,mixed>|null
     */
    private function sanitizeElement($e): ?array
    {
        if (! is_array($e)) {
            return null;
        }

        $typ = $e['typ'] ?? 'text';
        if (! in_array($typ, ['text', 'feld', 'block', 'unterschrift', 'bild', 'linie'], true)) {
            return null;
        }

        $out = [
            'typ'     => $typ,
            'seite'   => max(1, (int) ($e['seite'] ?? 1)),
            'bindung' => isset($e['bindung']) ? (string) $e['bindung'] : null,
            'text'    => isset($e['text']) ? (string) $e['text'] : null,
            'x'       => round((float) ($e['x'] ?? 10), 1),
            'y'       => round((float) ($e['y'] ?? 10), 1),
            'w'       => round((float) ($e['w'] ?? 40), 1),
            'h'       => round((float) ($e['h'] ?? 10), 1),
            'size'      => (int) ($e['size'] ?? 11),
            'align'     => in_array(($e['align'] ?? 'left'), ['left', 'center', 'right'], true) ? ($e['align'] ?? 'left') : 'left',
            'bold'      => (bool) ($e['bold'] ?? false),
            'italic'    => (bool) ($e['italic'] ?? false),
            'underline' => (bool) ($e['underline'] ?? false),
            'font'      => in_array(($e['font'] ?? 'DejaVu Sans'), ['DejaVu Sans', 'DejaVu Serif', 'DejaVu Sans Mono'], true) ? ($e['font'] ?? 'DejaVu Sans') : 'DejaVu Sans',
            'color'     => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#1f2937',
            'bg'        => (isset($e['bg']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $e['bg'])) ? $e['bg'] : null,
        ];

        if ($typ === 'bild') {
            $out['bild'] = isset($e['bild']) ? (string) $e['bild'] : null;
        }
        if ($typ === 'linie') {
            $out['staerke'] = round((float) ($e['staerke'] ?? 0.3), 2);
            $out['stil'] = in_array(($e['stil'] ?? 'solid'), ['solid', 'dashed', 'dotted'], true) ? $e['stil'] : 'solid';
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function renderDaten(Format $format): array
    {
        $elemente = $this->resolveBilder($format->layout ?: $this->standardLayout());

        return [
            'seiten' => $this->baueSeiten($format, $elemente),
            'daten'  => $this->beispielDaten(),
        ];
    }

    /**
     * Bild-Elemente in einbettbare data-URIs auflösen (für Browser-Vorschau UND PDF).
     *
     * @param  array<int,array<string,mixed>>  $elemente
     * @return array<int,array<string,mixed>>
     */
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

    /**
     * Physische Blätter mit Panels – Einzelblatt oder gefaltete A3-Broschüre.
     *
     * @param  array<int,array<string,mixed>>  $elemente
     * @return array<int,array<string,mixed>>
     */
    private function baueSeiten(Format $format, array $elemente): array
    {
        if ($format->broschuere) {
            $seite = fn (int $n) => array_values(array_filter($elemente, fn ($e) => (int) ($e['seite'] ?? 1) === $n));

            // Falzbogen: Außen = Rückseite(4) | Titel(1), Innen = Seite 2 | Seite 3.
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

        $s = $format->seiteMm();

        return [
            ['b' => $s['b'], 'h' => $s['h'], 'panels' => [
                ['x' => 0, 'y' => 0, 'w' => $s['b'], 'h' => $s['h'], 'elemente' => $elemente],
            ]],
        ];
    }

    /**
     * Beispiel-Daten für die Vorschau (bis der echte Zeugnis-Datensatz existiert).
     *
     * @return array<string,mixed>
     */
    private function beispielDaten(): array
    {
        return [
            'schulname'             => 'Freie Waldorfschule Musterstadt',
            'titel'                 => 'Zeugnis 2026/2027',
            'schueler.name'         => 'Lina Mustermann',
            'schueler.geboren'      => 'geboren am 14.03.2015 in Musterstadt',
            'klasse.zeile'          => 'Klasse 5a',
            'haupttext'             => "Lina hat ein arbeitsreiches und frohes Schuljahr erlebt. Mit wachem Interesse "
                . "hat sie am Unterricht teilgenommen und sich in der Klassengemeinschaft hilfsbereit gezeigt. "
                . "Besonders in den künstlerischen Fächern ist sie sichtlich aufgeblüht.\n\n"
                . "(Beispieltext für die Layout-Vorschau.)",
            'fachtexte'             => [
                ['fach' => 'Deutsch', 'text' => 'Lina liest sicher und mit Freude und bringt eigene Gedanken lebendig ein.'],
                ['fach' => 'Mathematik', 'text' => 'Im Rechnen arbeitet sie sorgfältig und erfasst neue Zusammenhänge rasch.'],
                ['fach' => 'Eurythmie', 'text' => 'Mit Anmut und Konzentration bewegt sich Lina im Raum.'],
            ],
            'ausgabe.zeile'         => 'Musterstadt, den 30.06.2027',
            'unterschrift'          => 'Klassenlehrer/in',
        ];
    }

    /**
     * Standard-Layout für ein A4-Textzeugnis (bis der visuelle Editor – Phase 2 –
     * die Elemente selbst setzt). Positionen in mm.
     *
     * @return array<int,array<string,mixed>>
     */
    private function standardLayout(): array
    {
        return [
            ['typ' => 'feld', 'bindung' => 'schulname', 'x' => 15, 'y' => 14, 'w' => 180, 'h' => 8, 'size' => 15, 'align' => 'center', 'bold' => true],
            ['typ' => 'feld', 'bindung' => 'titel', 'x' => 15, 'y' => 32, 'w' => 180, 'h' => 12, 'size' => 22, 'align' => 'center', 'bold' => true],
            ['typ' => 'feld', 'bindung' => 'schueler.name', 'x' => 15, 'y' => 54, 'w' => 180, 'h' => 8, 'size' => 14, 'align' => 'center', 'bold' => true],
            ['typ' => 'feld', 'bindung' => 'schueler.geboren', 'x' => 15, 'y' => 63, 'w' => 180, 'h' => 6, 'size' => 10, 'align' => 'center'],
            ['typ' => 'feld', 'bindung' => 'klasse.zeile', 'x' => 15, 'y' => 70, 'w' => 180, 'h' => 6, 'size' => 10, 'align' => 'center'],
            ['typ' => 'block', 'bindung' => 'haupttext', 'x' => 20, 'y' => 88, 'w' => 170, 'h' => 105, 'size' => 11, 'align' => 'left'],
            ['typ' => 'block', 'bindung' => 'fachtexte', 'x' => 20, 'y' => 198, 'w' => 170, 'h' => 70, 'size' => 11, 'align' => 'left'],
            ['typ' => 'feld', 'bindung' => 'ausgabe.zeile', 'x' => 20, 'y' => 278, 'w' => 90, 'h' => 6, 'size' => 10, 'align' => 'left'],
            ['typ' => 'unterschrift', 'bindung' => 'unterschrift', 'x' => 125, 'y' => 278, 'w' => 65, 'h' => 8, 'size' => 10, 'align' => 'center'],
        ];
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'typ'          => ['required', Rule::in(['text', 'noten'])],
            'seitenformat' => ['required', Rule::in(['a4', 'a3'])],
            'ausrichtung'  => ['required', Rule::in(['hoch', 'quer'])],
            'broschuere'   => ['nullable', 'boolean'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'aktiv'        => ['nullable', 'boolean'],
        ]);

        $data['aktiv']      = $request->boolean('aktiv');
        $data['broschuere'] = $request->boolean('broschuere');

        return $data;
    }
}
