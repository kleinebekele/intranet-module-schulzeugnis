<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Beispieltext;
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

    public function duplicate(Format $format)
    {
        $kopie = $format->replicate();
        $kopie->name = $format->name . ' (Kopie)';
        $kopie->save();

        Protokoll::log('format_dupliziert', [
            'beschreibung' => "Zeugnisformat {$format->name} dupliziert",
        ]);

        return redirect()
            ->route('module.schulzeugnis.formate.index')
            ->with('status', "Format {$format->name} als Kopie dupliziert.");
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
    public function vorschau(Request $request, Format $format)
    {
        return view('schulzeugnis::formate.render', $this->renderDaten($format, $this->probe($request)));
    }

    /** Vorschau als PDF (dompdf) – gleiches Layout, echtes Papierformat. */
    public function pdf(Request $request, Format $format)
    {
        [$groesse, $lage] = $format->broschuere
            ? ['a3', 'landscape']
            : [$format->seitenformat === 'a3' ? 'a3' : 'a4', $format->ausrichtung === 'quer' ? 'landscape' : 'portrait'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulzeugnis::formate.render', $this->renderDaten($format, $this->probe($request)))
            ->setPaper($groesse, $lage)
            ->setOption('tempDir', \Intranet\Modules\Schulzeugnis\Support\PdfTemp::dir());

        return $pdf->stream("zeugnis-vorschau-{$format->id}.pdf");
    }

    /** Gewählte Beispieltext-Variante (Positions-Schlüssel) aus der Query. */
    private function probe(Request $request): string
    {
        return (string) $request->query('probe', '1');
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
            'seitenRollen' => $format->broschuere ? [] : $format->seitenRollen(),
            'elemente'     => $format->layout ?: $this->standardLayout(),
            'bindungen'    => $this->bindungen(),
            'variablen'    => $this->variablen(),
            'daten'        => $this->beispielDaten(),
            'textproben'   => $this->zeugnistextProben(),
        ]);
    }

    /** Eigene Beispieltexte speichern (ersetzt die komplette Liste). */
    public function saveTextproben(Request $request)
    {
        $data = $request->validate([
            'texte'          => ['present', 'array'],
            'texte.*.name'   => ['required', 'string', 'max:120'],
            'texte.*.text'   => ['required', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            Beispieltext::query()->delete();
            foreach (array_values($data['texte']) as $i => $t) {
                Beispieltext::create([
                    'position' => $i + 1,
                    'name'     => trim($t['name']),
                    'text'     => $t['text'],
                ]);
            }
        });

        Protokoll::log('beispieltexte_gespeichert', [
            'beschreibung' => 'Beispiel-Zeugnistexte für die Vorschau gespeichert (' . count($data['texte']) . ')',
        ]);

        return response()->json(['ok' => true, 'textproben' => $this->zeugnistextProben()]);
    }

    /** Eigene Beispieltexte verwerfen – es greifen wieder die Standardtexte. */
    public function resetTextproben()
    {
        Beispieltext::query()->delete();

        Protokoll::log('beispieltexte_zurueckgesetzt', [
            'beschreibung' => 'Beispiel-Zeugnistexte auf Standard zurückgesetzt',
        ]);

        return response()->json(['ok' => true, 'textproben' => $this->zeugnistextProben()]);
    }

    /** Layout aus dem Editor entgegennehmen und am Format speichern. */
    public function saveLayout(Request $request, Format $format)
    {
        // Rohes JSON lesen (nicht $request->input), damit die TrimStrings-Middleware
        // keine führenden/abschließenden Leerzeichen in Texten entfernt.
        $payload = json_decode($request->getContent(), true);
        $roh = (is_array($payload) && is_array($payload['elemente'] ?? null)) ? $payload['elemente'] : [];

        $elemente = collect($roh)
            ->map(fn ($e) => $this->sanitizeElement($e))
            ->filter()
            ->values()
            ->all();

        $daten = ['layout' => $elemente];

        // Seiten-Rollen (nur Nicht-Broschüre): 'start' erscheint einmal,
        // 'folge' wiederholt sich beliebig oft, bis der Zeugnistext durch ist.
        if (! $format->broschuere && is_array($payload['seiten'] ?? null)) {
            $rollen = array_values(array_map(
                fn ($r) => $r === 'folge' ? 'folge' : 'start',
                array_slice($payload['seiten'], 0, 20)
            ));
            $daten['seiten'] = $rollen ?: ['start'];
        }

        // Element-Seiten auf den gültigen Bereich klammern.
        $anzahl = $format->broschuere ? 4 : count($daten['seiten'] ?? $format->seitenRollen());
        $daten['layout'] = array_map(function ($e) use ($anzahl) {
            $e['seite'] = min($anzahl, max(1, (int) ($e['seite'] ?? 1)));

            return $e;
        }, $elemente);

        $format->update($daten);

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
            'schueler.name'         => 'Schüler: Name',
            'schueler.geboren'      => 'Schüler: geboren am/in (zusammengesetzt)',
            'schueler.geburtsdatum' => 'Schüler: Geburtsdatum',
            'schueler.geburtsort'   => 'Schüler: Geburtsort',
            'klasse.zeile'          => 'Klasse',
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
        if (! in_array($typ, ['text', 'feld', 'block', 'unterschrift', 'bild', 'linie', 'textbereich'], true)) {
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
        if ($typ === 'textbereich') {
            // Auffangfeld: wird in der Füll-Reihenfolge ans Ende gestellt und
            // fängt nur den Überhang, statt vorne mit dem Zeugnistext zu beginnen.
            $out['nurUeberhang'] = (bool) ($e['nurUeberhang'] ?? false);
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function renderDaten(Format $format, string $probe = ''): array
    {
        $proben = $this->zeugnistextProben();
        $key    = isset($proben[$probe]) ? $probe : (string) array_key_first($proben);
        $daten  = $this->beispielDaten($proben[$key]['text'] ?? '');
        $elemente = $this->ersetzeVariablen($this->resolveBilder($format->layout ?: $this->standardLayout()), $daten);

        // Mit Folgeseiten wächst das Zeugnis, bis der Text durch ist – die
        // Verteilung übernimmt die geteilte Textverteilung (wie beim echten Zeugnis).
        if ($format->hatFolgeseiten()) {
            $res = \Intranet\Modules\Schulzeugnis\Support\Textverteilung::verteilen(
                $elemente,
                (string) ($daten['zeugnistext'] ?? ''),
                $format->seitenRollen(),
                $this->fontMetrics()
            );

            return [
                'seiten' => $this->seitenZuBlaettern($format, $res['seiten']),
                'daten'  => $daten,
            ];
        }

        $elemente = $this->fuelleTextbereiche($elemente, (string) ($daten['zeugnistext'] ?? ''));

        return [
            'seiten' => $this->baueSeiten($format, $elemente),
            'daten'  => $daten,
        ];
    }

    /**
     * Verteilt den {Zeugnistext} auf alle Textbereich-Rahmen (nach Seite/Position
     * sortiert). Umbruch exakt über dompdfs eigene Schriftvermessung. Überlauf
     * (Rest passt nicht mehr) wird hier NICHT ausgegeben und keine Seite ergänzt –
     * das ist später Sache des befüllten Zeugnisses (Warnung in der DB).
     *
     * @param  array<int,array<string,mixed>>  $elemente
     * @return array<int,array<string,mixed>>
     */
    private function fuelleTextbereiche(array $elemente, string $text): array
    {
        $alle = [];
        foreach ($elemente as $i => $e) {
            if (($e['typ'] ?? '') === 'textbereich') {
                $alle[] = $i;
            }
        }

        if (empty($alle) || trim($text) === '') {
            return $elemente;
        }

        $mmToPt = 2.83465;
        $fm     = $this->fontMetrics();

        $byPos = fn ($a, $b) => [$elemente[$a]['seite'] ?? 1, $elemente[$a]['y'] ?? 0, $elemente[$a]['x'] ?? 0]
            <=> [$elemente[$b]['seite'] ?? 1, $elemente[$b]['y'] ?? 0, $elemente[$b]['x'] ?? 0];

        // Feste Felder (immer) und Zusatzfelder (nur bei Überhang) trennen.
        $fest = array_values(array_filter($alle, fn ($i) => empty($elemente[$i]['nurUeberhang'])));
        usort($fest, $byPos);
        $bedingt = array_values(array_filter($alle, fn ($i) => ! empty($elemente[$i]['nurUeberhang'])));

        // Verteilt den Text der Reihe nach über die gegebenen Felder.
        $verteile = function (array $order) use ($elemente, $mmToPt, $fm, $text) {
            $first  = $elemente[$order[0]];
            $size   = (float) ($first['size'] ?? 11);
            $family = $first['font'] ?? 'DejaVu Sans';
            $font   = ($fm && method_exists($fm, 'getFont')) ? $fm->getFont($family, 'normal') : null;
            $mess = function (string $s) use ($fm, $font, $size) {
                if ($fm && $font) {
                    return (float) $fm->getTextWidth($s, $font, $size);
                }

                return mb_strlen($s) * $size * 0.52; // Fallback-Schätzung
            };

            // Sicherheits-Rand: an der schmalsten Spalte umbrechen, dann passt es überall.
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

        // Erst nur die festen Felder. Passt der Text nicht und es gibt Zusatzfelder
        // (nurUeberhang), werden alle Felder in natürlicher Reihenfolge genutzt –
        // die Zusatzfelder erscheinen dann an ihrer echten Position (z. B. zuerst).
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

        // Alle Textbereiche setzen; nicht genutzte Zusatzfelder bleiben leer.
        foreach ($alle as $i) {
            $elemente[$i]['inhalt'] = $res['inhalt'][$i] ?? '';
        }

        return $elemente;
    }

    /**
     * Bricht Text in sichtbare Zeilen um (respektiert \n als Absätze/Leerzeilen).
     *
     * @return array<int,string>
     */
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

    /** Platzhalter-Variablen für freie Textfelder (Anzeigename => Datenschlüssel). */
    private function variablen(): array
    {
        return [
            'Name'         => 'schueler.name',
            'Geburtsdatum' => 'schueler.geburtsdatum',
            'Geburtsort'   => 'schueler.geburtsort',
            'Klasse'       => 'klasse',
            'Schuljahr'    => 'schuljahr',
            'Schulname'    => 'schulname',
            'Zeugnisspruch' => 'zeugnisspruch',
        ];
    }

    /**
     * Ersetzt {Variablen} in freien Textelementen durch die Datenwerte.
     *
     * @param  array<int,array<string,mixed>>  $elemente
     * @param  array<string,mixed>  $daten
     * @return array<int,array<string,mixed>>
     */
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

        // Nicht-Broschüre: ein Blatt je Design-Seite (Element-Seite geklammert).
        $anzahl = $format->seitenAnzahl();
        $liste  = [];
        for ($n = 1; $n <= $anzahl; $n++) {
            $liste[] = array_values(array_filter(
                $elemente,
                fn ($e) => min($anzahl, max(1, (int) ($e['seite'] ?? 1))) === $n
            ));
        }

        return $this->seitenZuBlaettern($format, $liste);
    }

    /**
     * Fertige Seiten-Listen (je Seite die Elemente) in Blätter der Formatgröße gießen.
     *
     * @param  array<int,array<int,array<string,mixed>>>  $seiten
     * @return array<int,array<string,mixed>>
     */
    private function seitenZuBlaettern(Format $format, array $seiten): array
    {
        $s = $format->seiteMm();

        return array_map(fn ($els) => [
            'b' => $s['b'], 'h' => $s['h'], 'panels' => [
                ['x' => 0, 'y' => 0, 'w' => $s['b'], 'h' => $s['h'], 'elemente' => $els],
            ],
        ], $seiten);
    }

    /** Wörter zählen (für die Label-Angabe der Beispieltexte). */
    private function woerter(string $text): int
    {
        $text = trim($text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    /**
     * Verfügbare Beispiel-Zeugnistexte für die Vorschau – zuerst die selbst
     * gepflegten aus der DB, sonst die eingebauten Standardtexte. Nach Position
     * geschlüsselt (1..N); das Label ergänzt die berechnete Wörterzahl.
     *
     * @return array<string,array{label:string,name:string,text:string}>
     */
    private function zeugnistextProben(): array
    {
        $rows = Beispieltext::orderBy('position')->orderBy('id')->get();

        $liste = $rows->isNotEmpty()
            ? $rows->map(fn ($r) => ['name' => $r->name, 'text' => $r->text])->all()
            : $this->standardProben();

        $proben = [];
        foreach (array_values($liste) as $i => $p) {
            $proben[(string) ($i + 1)] = [
                'label' => $p['name'] . ' (' . $this->woerter($p['text']) . ' Wörter)',
                'name'  => $p['name'],
                'text'  => $p['text'],
            ];
        }

        return $proben;
    }

    /**
     * Fünf eingebaute Beispiel-Zeugnistexte steigender Länge (Standard, falls
     * keine eigenen gepflegt sind) – zum Testen der Textverteilung/Überlauf.
     * Die Ziel-Wortzahlen entsprechen ca. 100/200/300/400/450 % von rund einer
     * Seite (≈ 340 Wörter).
     *
     * @return array<int,array{name:string,text:string}>
     */
    private function standardProben(): array
    {
        $intro = "Lina hat ein arbeitsreiches, frohes und ereignisreiches Schuljahr erlebt. Mit wachem Interesse und großer Ausdauer hat sie am Unterricht teilgenommen und sich in der Klassengemeinschaft hilfsbereit, verlässlich und rücksichtsvoll gezeigt. Besonders in den künstlerischen Fächern ist sie sichtlich aufgeblüht und hat andere Kinder mit ihrer Begeisterung angesteckt.";

        // Fach-/Epochenblöcke in fester Reihenfolge; längere Varianten nehmen
        // (satzweise) weitere Blöcke hinzu, bis die Ziel-Wortzahl erreicht ist.
        $bloecke = [
            'Sozialverhalten und Arbeitshaltung' => "In der Klassengemeinschaft ist Lina eine warmherzige und verlässliche Mitschülerin, die anderen Kindern aufmerksam begegnet und in Streitfragen um Ausgleich bemüht ist. Ihre Aufgaben ergreift sie mit Fleiß und Sorgfalt und bringt begonnene Arbeiten geduldig zu Ende. Auch wenn etwas nicht auf Anhieb gelingt, bleibt sie zuversichtlich und versucht es mit ruhiger Beharrlichkeit erneut. Im Morgenkreis und bei gemeinsamen Vorhaben übernimmt sie gern Verantwortung und denkt an das Wohl der ganzen Gruppe.",
            'Formenzeichnen' => "Mit ruhiger Hand führt Lina die fließenden und gespiegelten Formen aus und erfasst deren Gesetzmäßigkeiten mit wachem Blick. Gerade und geschwungene Linien setzt sie zunehmend sicher gegeneinander und findet dabei ein feines Gleichgewicht zwischen Spannung und Ruhe. Ihre Blätter gestaltet sie sauber, sorgfältig und mit sichtlicher Freude an Linie, Rhythmus und Farbe. Auch anspruchsvollere Verwandlungsformen greift sie mutig auf und arbeitet sie mit Geduld bis zur stimmigen Gestalt aus.",
            'Deutsch – Lesen und Sprechen' => "Lina liest sicher, betont und mit echtem Ausdruck und erfasst den Sinn des Gelesenen zuverlässig. Beim Vortragen von Gedichten und Sprüchen spricht sie deutlich, gegliedert und mit lebendiger Stimme. Im Erzählen bringt sie eigene Gedanken anschaulich und in geordneter Folge ein und hört zugleich aufmerksam auf die Beiträge der anderen Kinder. Unbekannte Wörter erschließt sie sich zunehmend selbstständig aus dem Zusammenhang.",
            'Deutsch – Schreiben und Grammatik' => "Beim freien Schreiben findet Lina eine anschauliche, bildhafte Sprache und achtet zunehmend auf einen sauberen Satzbau und eine übersichtliche Gliederung. Die Regeln der Rechtschreibung wendet sie mit wachsender Sicherheit an und überarbeitet eigene Texte aufmerksam und selbstkritisch. Die Wortarten unterscheidet sie sicher und setzt sie bewusst ein. Ihre Handschrift ist im Laufe des Jahres gleichmäßig, flüssig und gut lesbar geworden.",
            'Mathematik' => "Im Rechnen arbeitet Lina sorgfältig, ausdauernd und erfasst neue Zusammenhänge rasch. Die schriftlichen Rechenverfahren in den vier Grundrechenarten wendet sie sicher an; beim Sachrechnen entwickelt sie eigene Lösungswege und erklärt diese ruhig, klar und verständlich. Das kleine Einmaleins beherrscht sie geläufig und setzt es beweglich ein. Beim Kopfrechnen zeigt sie zunehmend Schnelligkeit und traut sich auch an anspruchsvolle Knobelaufgaben heran.",
            'Rechnen – Geometrie' => "In der Freihandgeometrie zeichnet Lina Kreise, Dreiecke und regelmäßige Muster mit ruhiger Hand und wachsender Genauigkeit. Die Gesetzmäßigkeiten der Figuren erfasst sie mit Freude und entdeckt dabei selbst überraschende Zusammenhänge. Ihre Konstruktionen führt sie sauber aus und gestaltet die Blätter mit Sorgfalt, Farbe und einem feinen Sinn für Symmetrie und Rhythmus.",
            'Rechnen – Sachaufgaben und Messen' => "Beim Messen von Längen, Gewichten und Zeiten geht Lina umsichtig und genau vor und wählt passende Einheiten sicher aus. Sachaufgaben aus dem Alltag durchdringt sie ruhig, erkennt die wesentlichen Angaben und findet eigene Rechenwege. Ihre Ergebnisse prüft sie auf Sinnhaftigkeit und stellt den Lösungsweg klar und übersichtlich dar. Auch mehrschrittige Aufgaben löst sie mit wachsender Selbstständigkeit.",
            'Sachkunde und Heimatkunde' => "Mit großer Neugier verfolgt Lina die Geschichten und Bilder aus Natur und Heimat und bringt eigene Beobachtungen lebhaft ein. Zusammenhänge in ihrer Umgebung erfasst sie aufmerksam und behält sie anschaulich im Gedächtnis. Bei Ausflügen und Beobachtungsaufgaben ist sie mit Eifer und wachen Sinnen dabei und stellt kluge Fragen. Ihre Hefteinträge gestaltet sie sorgfältig, übersichtlich und mit liebevollen Zeichnungen.",
            'Tier- und Pflanzenkunde' => "Den Erzählungen über Tiere und Pflanzen folgt Lina mit Anteilnahme und einem feinen Gespür für das Lebendige. Gestalt, Lebensraum und Gewohnheiten der Tiere beschreibt sie treffend und mit Wärme. Ihre Beobachtungen hält sie in sorgfältigen Zeichnungen und kurzen, gut formulierten Texten anschaulich fest. Immer wieder verknüpft sie Neues mit eigenen Erlebnissen aus Garten, Wald und Wiese.",
            'Geografie' => "In der Heimatkunde und ersten Geografie zeichnet Lina Wege, Wiesen und Höhenzüge der Umgebung mit wachem Blick in einfache Karten ein. Himmelsrichtungen und Maßstab erfasst sie zunehmend sicher und orientiert sich im Gelände aufmerksam. Mit Freude berichtet sie von eigenen Wanderungen und ordnet das Erlebte anschaulich in das größere Bild der Landschaft ein.",
            'Geschichte' => "Den Bildern und Erzählungen aus alten Zeiten begegnet Lina mit lebhafter Vorstellungskraft und ehrlicher Anteilnahme. Sie merkt sich Namen, Orte und Zusammenhänge gut und gibt Begebenheiten in eigenen Worten lebendig wieder. In Gesprächen bringt sie eigene Fragen ein und verbindet Vergangenes nachdenklich mit dem eigenen Leben und der Gegenwart.",
            'Englisch' => "Lina beteiligt sich lebhaft an Liedern, Reimen und Sprüchen und nimmt neue Wörter rasch und sicher auf. Kurze Dialoge und kleine Szenen trägt sie mit wachsender Sicherheit und guter Aussprache vor. Sie versteht einfache Anweisungen zuverlässig und antwortet zunehmend mutig und bereitwillig in der fremden Sprache. Auch beim Zählen, bei Farben und im Wortschatz des Alltags ist sie sattelfest geworden.",
            'Französisch' => "Auch im Französischen singt und spricht Lina mit Freude mit und ahmt Klang und Melodie der Sprache aufmerksam nach. Reime und kleine Verse behält sie gut und trägt sie gern und ausdrucksvoll vor der Gruppe vor. Ihren Wortschatz erweitert sie stetig und setzt ihn in vertrauten Situationen bereits sicher ein. Neue Sprachspiele greift sie mit Eifer auf und hat sichtlich Freude am Klang der Wörter.",
            'Eurythmie' => "Mit Anmut und Konzentration bewegt sich Lina im Raum und nimmt die Formen und Gesten mit feinem Gespür auf. Laute und Rhythmen setzt sie beweglich in Bewegung um und findet dabei zu einem harmonischen, gesammelten Ausdruck. In der Gruppe ist sie eine aufmerksame, rücksichtsvolle und verlässliche Partnerin. Auch schwierigere Raumformen erfasst sie rasch und führt sie mit Sicherheit und Freude aus.",
            'Musik – Singen und Flöte' => "Im gemeinsamen Singen und beim Flötenspiel ist Lina mit Hingabe und wacher Aufmerksamkeit dabei. Rhythmen erfasst sie sicher, hält ihre Stimme verlässlich in der Gruppe und hört fein auf ihre Mitspieler. Neue Lieder und Griffe erlernt sie rasch und übt mit erfreulicher Ausdauer und Genauigkeit. Bei Klassenspielen und Monatsfeiern trägt sie mit Freude und Zuverlässigkeit zum Gelingen bei.",
            'Chor und Orchester' => "Im Klassenchor singt Lina mit klarer Stimme und hält ihre Stimmlage auch im mehrstimmigen Satz verlässlich. Beim gemeinsamen Musizieren fügt sie sich aufmerksam in das Ganze ein und achtet auf Einsatz und Lautstärke. Proben verfolgt sie geduldig und diszipliniert und trägt mit Freude zum festlichen Gelingen der Aufführungen bei.",
            'Malen und Aquarell' => "Im Malen mit Aquarellfarben ergreift Lina die Farben mutig und mit einer feinen Empfindung für ihre Stimmung. Farbübergänge gestaltet sie behutsam und geduldig, sodass ihre Bilder lebendig, licht und ausgewogen wirken. Sie geht mit Pinsel, Farbe und Papier sorgsam um und freut sich sichtlich am Entstehen der Blätter. Eigene Bildideen entwickelt sie zunehmend selbstständig und mit Ausdauer.",
            'Plastizieren und Werken' => "Beim Formen mit Bienenwachs und Ton arbeitet Lina mit Ruhe, Geduld und Vorstellungskraft. Aus dem Ganzen heraus entwickelt sie stimmige Gestalten und bringt eigene Ideen behutsam und ausdauernd zum Ausdruck. Beim Werken mit Holz geht sie umsichtig und sicher mit den Werkzeugen um. Sie hilft anderen Kindern bereitwillig weiter und räumt ihren Arbeitsplatz gewissenhaft auf.",
            'Handarbeit' => "Beim Stricken und Häkeln arbeitet Lina ausdauernd, gewissenhaft und mit großer Geduld. Maschen und Muster führt sie gleichmäßig aus und erkennt eigene Fehler selbstständig, um sie ruhig und ohne Unmut zu berichtigen. Ihre fertigen Stücke zeigen ein feines Gespür für Muster, Farbe und Ordnung und werden mit sichtbarem Stolz vollendet. Auch aufwendige Arbeiten bringt sie mit Freude zu Ende.",
            'Gartenbau' => "Bei der Arbeit im Garten packt Lina tatkräftig, freudig und mit Sinn für das Ganze an. Sie beobachtet das Wachsen und Reifen der Pflanzen aufmerksam und übernimmt Pflegeaufgaben zuverlässig und selbstständig. Auch anstrengende Arbeiten führt sie ausdauernd und klaglos aus und erlebt die Früchte ihrer Mühe mit sichtlicher Freude. Im Umgang mit Werkzeug und Erde ist sie umsichtig und ordentlich.",
            'Turnen und Bewegung' => "Bei Spiel und Bewegung zeigt Lina Mut, Geschick und einen fairen, freundlichen Umgang mit den anderen Kindern. Neue Übungen greift sie freudig auf und bleibt auch bei Schwierigkeiten beharrlich, mutig und zuversichtlich. In Mannschaftsspielen ist sie eine rücksichtsvolle Mitspielerin, die sich mit Einsatz für die Gemeinschaft einbringt. Ihre Bewegungen sind sicherer, kräftiger und geschickter geworden.",
            'Wandertage und Klassenfahrt' => "Auf den Wandertagen und der Klassenfahrt zeigt sich Lina als ausdauernde, fröhliche und rücksichtsvolle Begleiterin. Auch längere Strecken meistert sie klaglos und hilft müden Kindern mit aufmunternden Worten. In der Gemeinschaft übernimmt sie kleine Aufgaben zuverlässig und trägt mit ihrer heiteren Art zu einer guten Stimmung bei. Das gemeinsame Erleben in der Natur genießt sie sichtlich.",
            'Jahresfeste und Jahreszeitentisch' => "Die Vorbereitung der Jahresfeste begleitet Lina mit Freude, Andacht und tatkräftigem Einsatz. Den Jahreszeitentisch gestaltet sie aufmerksam mit und bringt eigene Fundstücke und Ideen liebevoll ein. Lieder, Sprüche und Reigen der Feste lernt sie gewissenhaft und trägt sie mit innerer Beteiligung vor. Die Stimmung der Feste nimmt sie fein wahr und gibt sie in Bildern und Erzählungen wieder.",
            'Erzählteil' => "Den Erzählungen und Bildern des Erzählteils folgt Lina mit innerer Anteilnahme und wachem Gemüt. Das Gehörte gibt sie stimmungsvoll und in eigenen Worten treffend wieder und verbindet es mit eigenen Gedanken und Fragen. Immer wieder bringt sie feine Beobachtungen ein, die die ganze Klasse bereichern. Die Stimmung der Bilder trägt sie über den Tag und lässt sie in eigene Erzählungen und Zeichnungen einfließen.",
            'Ausblick' => "Lina hat sich im Laufe des Schuljahres in vielerlei Hinsicht erfreulich entwickelt und ihre Fähigkeiten mit Freude und Ernst entfaltet. Sie darf mit berechtigtem Vertrauen auf das Erreichte blicken und die kommenden Aufgaben mutig und tatkräftig ergreifen. Ihre herzliche, fleißige und zugewandte Art wird sie auch künftig gut begleiten. Wir wünschen Lina für ihren weiteren Weg viel Freude, Mut und Zuversicht.",
        ];

        // Satzweiser Aufbau: volle Blöcke werden aufgenommen, der letzte Block
        // wird satzgenau abgeschnitten, sobald die Ziel-Wortzahl erreicht ist.
        $bauen = function (int $ziel) use ($intro, $bloecke) {
            $teile = [$intro];
            $summe = $this->woerter($intro);

            foreach ($bloecke as $ueberschrift => $absatz) {
                if ($summe >= $ziel) {
                    break;
                }
                $saetze = preg_split('/(?<=[.!?])\s+/u', $absatz);
                $genommen = [];
                foreach ($saetze as $satz) {
                    $genommen[] = $satz;
                    $summe += $this->woerter($satz);
                    if ($summe >= $ziel) {
                        break;
                    }
                }
                $teile[] = $ueberschrift . "\n" . implode(' ', $genommen);
            }

            return implode("\n\n", $teile);
        };

        $proben = [];
        foreach ([1 => 340, 2 => 680, 3 => 1020, 4 => 1360, 5 => 1530] as $nr => $ziel) {
            $proben[] = [
                'name' => 'Variante ' . $nr,
                'text' => $bauen($ziel),
            ];
        }

        return $proben;
    }

    /**
     * Beispiel-Daten für die Vorschau (bis der echte Zeugnis-Datensatz existiert).
     *
     * @return array<string,mixed>
     */
    private function beispielDaten(?string $zeugnistext = null): array
    {
        if ($zeugnistext === null) {
            $proben      = $this->zeugnistextProben();
            $zeugnistext = $proben[array_key_first($proben)]['text'] ?? '';
        }

        return [
            'schulname'             => 'Freie Waldorfschule Musterstadt',
            'titel'                 => 'Zeugnis 2026/2027',
            'schueler.name'         => 'Lina Mustermann',
            'schueler.geboren'      => 'geboren am 14.03.2015 in Musterstadt',
            'schueler.geburtsdatum' => '14.03.2015',
            'schueler.geburtsort'   => 'Musterstadt',
            'klasse.zeile'          => 'Klasse 5a',
            'klasse'                => '5a',
            'schuljahr'             => '2026/2027',
            'haupttext'             => "Lina hat ein arbeitsreiches und frohes Schuljahr erlebt. Mit wachem Interesse "
                . "hat sie am Unterricht teilgenommen und sich in der Klassengemeinschaft hilfsbereit gezeigt. "
                . "Besonders in den künstlerischen Fächern ist sie sichtlich aufgeblüht.\n\n"
                . "(Beispieltext für die Layout-Vorschau.)",
            'fachtexte'             => [
                ['fach' => 'Deutsch', 'text' => 'Lina liest sicher und mit Freude und bringt eigene Gedanken lebendig ein.'],
                ['fach' => 'Mathematik', 'text' => 'Im Rechnen arbeitet sie sorgfältig und erfasst neue Zusammenhänge rasch.'],
                ['fach' => 'Eurythmie', 'text' => 'Mit Anmut und Konzentration bewegt sich Lina im Raum.'],
            ],
            'zeugnistext'           => $zeugnistext,
            'zeugnisspruch'         => "Wie das Licht am Morgen die Erde neu erweckt,\n"
                . "so wächst in dir die Kraft, die Mut und Freude weckt.",
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
            'ausrichtung'  => ['required', Rule::in(['hoch', 'quer', 'broschuere'])],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'aktiv'        => ['nullable', 'boolean'],
        ]);

        $data['aktiv'] = $request->boolean('aktiv');

        // Broschüre ist eine Ausrichtungs-Option im Formular; in der DB bleibt sie
        // das bewährte Flag (Falzbogen A3 quer = 4 A4-Seiten, fix).
        $data['broschuere'] = $data['ausrichtung'] === 'broschuere';
        if ($data['broschuere']) {
            $data['ausrichtung'] = 'quer';
        }

        return $data;
    }
}
