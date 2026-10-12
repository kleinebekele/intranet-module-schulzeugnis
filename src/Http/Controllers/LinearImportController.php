<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Support\Import\FaecherImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\ImportFehler;
use Intranet\Modules\Schulzeugnis\Support\Import\KlasseImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrauftragImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrerImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\SchuelerImporter;
use Intranet\Modules\Schulzeugnis\Support\Linear\LinearQuelle;

/**
 * „Aus Linear importieren" auf den Verwaltungsseiten (Fächer, Lehrer, Klassen,
 * Schüler, Lehraufträge): liest den Linear-Zeitraum des Schuljahres, zeigt einen
 * Trockenlauf und übernimmt auf Wunsch nur die neuen oder auch die geänderten
 * Datensätze.
 *
 * Dieselben Leser wie der Nacht-Task Linear/ZeugnisImport ({@see LinearQuelle}) und
 * dieselben Importer wie der CSV-Import – gelöscht wird also auch hier nie. Was in
 * Linear fehlt, steht in der Vorschau nur als Hinweis.
 *
 * Zwischen Vorschau und Ausführen wird nichts zwischengespeichert: Beim Ausführen
 * liest der Controller Linear erneut – das Ergebnis zeigt, was tatsächlich geschah.
 */
class LinearImportController
{
    /** route = Namenspräfix der Seite (Zugriff wie die Seite, Stufe „verwalten"). */
    private const ARTEN = [
        'faecher'       => ['titel' => 'Fächer',       'importer' => FaecherImporter::class,     'route' => 'faecher',                'zurueck' => 'module.schulzeugnis.faecher.index'],
        'lehrer'        => ['titel' => 'Lehrer',       'importer' => LehrerImporter::class,      'route' => 'lehrer',                 'zurueck' => 'module.schulzeugnis.lehrer.jahr'],
        'klassen'       => ['titel' => 'Klassen',      'importer' => KlasseImporter::class,      'route' => 'klassen',                'zurueck' => 'module.schulzeugnis.klassen.jahr'],
        'schueler'      => ['titel' => 'Schüler',      'importer' => SchuelerImporter::class,    'route' => 'schueler',               'zurueck' => 'module.schulzeugnis.schueler.jahr'],
        'lehrauftraege' => ['titel' => 'Lehraufträge', 'importer' => LehrauftragImporter::class, 'route' => 'klassen.lehrauftraege',  'zurueck' => 'module.schulzeugnis.klassen.jahr'],
    ];

    /** Reihenfolge, in der die Importe aufeinander aufbauen (Hinweis in der Vorschau). */
    private const REIHENFOLGE = 'Fächer → Lehrer → Klassen → Schüler → Lehraufträge';

    // ── Einstiege je Seite ──────────────────────────────────────────────────

    public function faecherVorschau(Request $request)
    {
        return $this->vorschau($request, 'faecher', $this->schuljahrFuerFaecher($request));
    }

    public function faecherAusfuehren(Request $request)
    {
        return $this->ausfuehren($request, 'faecher', $this->schuljahrFuerFaecher($request));
    }

    public function lehrerVorschau(Request $request, Schuljahr $schuljahr)
    {
        return $this->vorschau($request, 'lehrer', $schuljahr);
    }

    public function lehrerAusfuehren(Request $request, Schuljahr $schuljahr)
    {
        return $this->ausfuehren($request, 'lehrer', $schuljahr);
    }

    public function klassenVorschau(Request $request, Schuljahr $schuljahr)
    {
        return $this->vorschau($request, 'klassen', $schuljahr);
    }

    public function klassenAusfuehren(Request $request, Schuljahr $schuljahr)
    {
        return $this->ausfuehren($request, 'klassen', $schuljahr);
    }

    public function schuelerVorschau(Request $request, Schuljahr $schuljahr)
    {
        return $this->vorschau($request, 'schueler', $schuljahr);
    }

    public function schuelerAusfuehren(Request $request, Schuljahr $schuljahr)
    {
        return $this->ausfuehren($request, 'schueler', $schuljahr);
    }

    public function lehrauftraegeVorschau(Request $request, Schuljahr $schuljahr)
    {
        return $this->vorschau($request, 'lehrauftraege', $schuljahr);
    }

    public function lehrauftraegeAusfuehren(Request $request, Schuljahr $schuljahr)
    {
        return $this->ausfuehren($request, 'lehrauftraege', $schuljahr);
    }

    // ── Ablauf ──────────────────────────────────────────────────────────────

    private function vorschau(Request $request, string $art, ?Schuljahr $schuljahr)
    {
        $meta = self::ARTEN[$art];
        if ($fehler = $this->voraussetzungFehlt($schuljahr)) {
            return $this->zurueck($art, $schuljahr)->with('error', $fehler);
        }

        try {
            $quelle = app(LinearQuelle::class);
            $wahl   = $this->zeitraum($request, $schuljahr, $quelle);
            if ($wahl['zid'] === null) {
                // Schuljahr ist noch nicht mit Linear verknüpft und kein Name passt: Zeitraum wählen lassen.
                return view('schulzeugnis::linear.zeitraum', [
                    'art'        => $art,
                    'meta'       => $meta,
                    'schuljahr'  => $schuljahr,
                    'zeitraeume' => $wahl['auswahl'],
                    'formUrl'    => $this->url($art, 'vorschau', $schuljahr),
                    'zurueckUrl' => $this->zurueckUrl($art, $schuljahr),
                ]);
            }

            [$kopf, $zeilen] = $quelle->daten($art, $wahl['zid']);
            $analyse = $this->importer($art)->analysiere($kopf, $zeilen, ['schuljahr_id' => $schuljahr->id, 'quelle_label' => 'Linear']);
            if (in_array($art, ['schueler', 'lehrer'], true)) {
                $analyse['infos'][] = $this->fehlendeInLinear($art, $schuljahr, $zeilen, $quelle);
            }
            // Je Zeile ein Schlüssel auf den Linear-Datensatz – für „nur diese Zeile übernehmen".
            foreach ($analyse['zeilen'] as &$r) {
                $roh = $zeilen[$r['zeile'] - 2] ?? null;
                $r['schluessel'] = $roh !== null ? $this->schluessel($roh) : null;
            }
            unset($r);
        } catch (ImportFehler $e) {
            return $this->zurueck($art, $schuljahr)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->zurueck($art, $schuljahr)->with('error', 'Linear ist gerade nicht erreichbar: '.$e->getMessage());
        }

        return view('schulzeugnis::linear.vorschau', [
            'art'          => $art,
            'meta'         => $meta,
            'schuljahr'    => $schuljahr,
            'analyse'      => $analyse,
            'zid'          => $wahl['zid'],
            'zeitraumName' => $wahl['name'],
            'verknuepfen'  => $wahl['verknuepfen'],
            'reihenfolge'  => self::REIHENFOLGE,
            'formUrl'      => $this->url($art, 'ausfuehren', $schuljahr),
            'zurueckUrl'   => $this->zurueckUrl($art, $schuljahr),
        ]);
    }

    private function ausfuehren(Request $request, string $art, ?Schuljahr $schuljahr)
    {
        $meta = self::ARTEN[$art];
        if ($fehler = $this->voraussetzungFehlt($schuljahr)) {
            return $this->zurueck($art, $schuljahr)->with('error', $fehler);
        }

        $data = $request->validate([
            'zeitraum' => ['required', 'integer', 'min:1'],
            'nur'      => ['nullable', 'string', 'size:32'], // Schlüssel EINER Zeile; leer = alle
        ]);
        $zid = (int) $data['zeitraum'];
        $nur = $data['nur'] ?? null;

        // Ein verknüpftes Schuljahr importiert nur aus seinem eigenen Zeitraum.
        if ($schuljahr->quell_id !== null && $schuljahr->quell_id !== '' && (int) $schuljahr->quell_id !== $zid) {
            return $this->zurueck($art, $schuljahr)->with('error', 'Das Schuljahr ist mit einem anderen Linear-Zeitraum verknüpft. Bitte erneut starten.');
        }

        try {
            $quelle = app(LinearQuelle::class);
            [$kopf, $zeilen] = $quelle->daten($art, $zid);
            if ($nur !== null) {
                $zeilen = array_values(array_filter($zeilen, fn (array $z) => $this->schluessel($z) === $nur));
                if ($zeilen === []) {
                    return redirect()->to($this->url($art, 'vorschau', $schuljahr, $zid))
                        ->with('error', 'Dieser Datensatz hat sich in Linear inzwischen geändert – die Vorschau ist neu geladen.');
                }
            }
            $ergebnis = $this->importer($art)->importiere($kopf, $zeilen, [
                'schuljahr_id' => $schuljahr->id,
                'quelle_label' => 'Linear',
            ]);
        } catch (ImportFehler $e) {
            return $this->zurueck($art, $schuljahr)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->zurueck($art, $schuljahr)->with('error', 'Linear ist gerade nicht erreichbar: '.$e->getMessage());
        }

        // Noch nicht verknüpftes Schuljahr: Zeitraum merken, damit es künftig (auch nachts) passt.
        if (($schuljahr->quell_id === null || $schuljahr->quell_id === '')
            && ! Schuljahr::where('quell_id', (string) $zid)->exists()) {
            $schuljahr->update(['quell_id' => (string) $zid]);
        }

        // Einzelne Zeile: zurück in die (neu geladene) Vorschau, mit Rückmeldung.
        if ($nur !== null) {
            $r = $ergebnis['zeilen'][0] ?? null;
            $z   = $r['zellen'] ?? [];
            $was = match ($art) {
                'schueler'      => $z[1] ?? '',
                'lehrer'        => trim(($z[1] ?? '').' '.($z[2] ?? '')),
                'lehrauftraege' => implode(' · ', array_slice($z, 0, 3)),
                default         => $z[0] ?? '',
            };
            $wie = match ($r['status'] ?? '') {
                'neu'          => 'angelegt',
                'aktualisiert' => 'aktualisiert',
                default        => 'unverändert (nichts zu tun)',
            };

            return redirect()->to($this->url($art, 'vorschau', $schuljahr))
                ->with('status', trim("{$was} {$wie}."));
        }

        return view('schulzeugnis::linear.ergebnis', [
            'meta'       => $meta,
            'schuljahr'  => $schuljahr,
            'ergebnis'   => $ergebnis,
            'zurueckUrl' => $this->zurueckUrl($art, $schuljahr),
        ]);
    }

    // ── Hilfen ──────────────────────────────────────────────────────────────

    private function voraussetzungFehlt(?Schuljahr $schuljahr): ?string
    {
        if (! $schuljahr) {
            return 'Kein aktives Schuljahr – bitte zuerst eines aktiv schalten.';
        }
        if (! app(LinearQuelle::class)->verfuegbar()) {
            return 'Keine Verbindung zu Linear eingerichtet (MSSQL-Zugangsdaten fehlen in der .env).';
        }

        return null;
    }

    /**
     * Linear-Zeitraum zum Schuljahr: Verknüpfung (quell_id) > ausdrückliche Wahl > gleicher Name.
     *
     * @return array{zid: ?int, name: string, verknuepfen: bool, auswahl: array<int,array<string,mixed>>}
     */
    private function zeitraum(Request $request, Schuljahr $schuljahr, LinearQuelle $quelle): array
    {
        $alle = collect($quelle->zeitraeume())->filter(fn ($z) => $z['schueler_zahl'] > 0)->sortByDesc('id')->values();

        if ($schuljahr->quell_id !== null && $schuljahr->quell_id !== '') {
            $z = $alle->firstWhere('id', (int) $schuljahr->quell_id);

            return ['zid' => (int) $schuljahr->quell_id, 'name' => $z ? LinearQuelle::zeitraumName($z) : 'Zeitraum '.$schuljahr->quell_id, 'verknuepfen' => false, 'auswahl' => []];
        }

        $gewaehlt = $request->filled('zeitraum') ? $alle->firstWhere('id', (int) $request->input('zeitraum')) : null;
        $gewaehlt ??= $alle->first(fn ($z) => mb_strtolower(LinearQuelle::zeitraumName($z)) === mb_strtolower($schuljahr->name));

        return $gewaehlt
            ? ['zid' => $gewaehlt['id'], 'name' => LinearQuelle::zeitraumName($gewaehlt), 'verknuepfen' => true, 'auswahl' => []]
            : ['zid' => null, 'name' => '', 'verknuepfen' => true, 'auswahl' => $alle->all()];
    }

    /**
     * Schüler bzw. Lehrer, die hier im Schuljahr stehen, im Linear-Zeitraum aber nicht:
     * was sagt Linear über sie (Adresse-Kennzeichen, letzter Eintrag)? Nur Anzeige.
     *
     * @param  array<int,array<string,string>>  $zeilen
     * @return array{label: string, items: array<int,string>, ton: string}
     */
    private function fehlendeInLinear(string $art, Schuljahr $schuljahr, array $zeilen, LinearQuelle $quelle): array
    {
        $inLinear = array_flip(array_column($zeilen, $art === 'lehrer' ? 'quellid' : 'schuelerid'));
        $bestand  = $art === 'lehrer' ? $schuljahr->lehrer() : $schuljahr->schueler();
        $fehlende = $bestand->orderBy('nachname')->orderBy('vorname')->get()
            ->reject(fn ($s) => filled($s->quell_id) && isset($inLinear[trim((string) $s->quell_id)]));

        $wer  = $art === 'lehrer' ? 'Lehrer' : 'Schüler';
        $info = ['label' => "{$wer} stehen hier, aber nicht im Linear-Zeitraum – was Linear über sie weiß", 'items' => [], 'ton' => 'amber'];
        if ($fehlende->isEmpty()) {
            return $info;
        }

        try {
            $status = $quelle->personenStatus($fehlende->pluck('quell_id')->filter()->map(fn ($q) => trim((string) $q))->all(), $art);
        } catch (\Throwable $e) {
            report($e);
            $status = null;
        }

        foreach ($fehlende as $s) {
            $id = trim((string) $s->quell_id);
            if ($id === '') {
                $text = 'ohne Linear-ID (von Hand angelegt?)';
            } elseif ($status === null) {
                $text = "Linear-ID {$id} – Abfrage fehlgeschlagen";
            } elseif (! isset($status[$id]) || ($status[$id]['felder'] === [] && $status[$id]['letzter'] === null)) {
                $text = "Linear-ID {$id} – in Linear nicht gefunden";
            } else {
                $teile = [];
                foreach ($status[$id]['felder'] as $feld => $wert) {
                    $teile[] = $feld.'='.($wert === '' ? '–' : $wert);
                }
                if ($l = $status[$id]['letzter']) {
                    $teile[] = 'zuletzt'.($l['klasse'] !== '' ? " {$l['klasse']}" : '')." in {$l['zeitraum']}".($l['klassenende'] !== '' ? ' (Klassenende '.\Carbon\Carbon::parse($l['klassenende'])->format('d.m.Y').')' : '');
                } else {
                    $teile[] = $art === 'lehrer' ? 'keiner Klasse und keinem Lehrauftrag zugeordnet' : 'in keiner Klasse geführt';
                }
                $text = "Linear-ID {$id}: ".implode(', ', $teile);
            }
            $info['items'][] = $s->fullName().' – '.$text;
        }

        return $info;
    }

    /** Fächer sind jahresübergreifend; gelesen wird der Zeitraum des aktiven (oder gewählten) Schuljahres. */
    private function schuljahrFuerFaecher(Request $request): ?Schuljahr
    {
        return $request->filled('schuljahr')
            ? Schuljahr::find((int) $request->input('schuljahr'))
            : Schuljahr::where('is_active', true)->first();
    }

    /** Stabiler Schlüssel eines Linear-Datensatzes (Vorschau ↔ Ausführen). @param array<string,string> $zeile */
    private function schluessel(array $zeile): string
    {
        return md5(json_encode($zeile, JSON_UNESCAPED_UNICODE));
    }

    private function importer(string $art): object
    {
        $klasse = self::ARTEN[$art]['importer'];

        return new $klasse();
    }

    private function url(string $art, string $schritt, ?Schuljahr $schuljahr, ?int $zeitraum = null): string
    {
        $name  = 'module.schulzeugnis.'.self::ARTEN[$art]['route'].'.linear.'.$schritt;
        $extra = $zeitraum ? ['zeitraum' => $zeitraum] : [];

        return $art === 'faecher'
            ? route($name, ($schuljahr ? ['schuljahr' => $schuljahr->id] : []) + $extra)
            : route($name, ['schuljahr' => $schuljahr] + $extra);
    }

    private function zurueckUrl(string $art, ?Schuljahr $schuljahr): string
    {
        if ($art === 'faecher') {
            return route(self::ARTEN['faecher']['zurueck']);
        }

        return $schuljahr
            ? route(self::ARTEN[$art]['zurueck'], $schuljahr)
            : route('module.schulzeugnis.schuljahre.index');
    }

    private function zurueck(string $art, ?Schuljahr $schuljahr)
    {
        return redirect()->to($this->zurueckUrl($art, $schuljahr));
    }
}
