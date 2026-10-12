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
            'modus'    => ['required', 'in:neu,alles'],
        ]);
        $zid    = (int) $data['zeitraum'];
        $nurNeu = $data['modus'] === 'neu';

        // Ein verknüpftes Schuljahr importiert nur aus seinem eigenen Zeitraum.
        if ($schuljahr->quell_id !== null && $schuljahr->quell_id !== '' && (int) $schuljahr->quell_id !== $zid) {
            return $this->zurueck($art, $schuljahr)->with('error', 'Das Schuljahr ist mit einem anderen Linear-Zeitraum verknüpft. Bitte erneut starten.');
        }

        try {
            $quelle = app(LinearQuelle::class);
            [$kopf, $zeilen] = $quelle->daten($art, $zid);
            $ergebnis = $this->importer($art)->importiere($kopf, $zeilen, [
                'schuljahr_id' => $schuljahr->id,
                'nur_neu'      => $nurNeu,
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

        if ($nurNeu) {
            $ergebnis = $this->aenderungenAlsNichtUebernommen($ergebnis);
        }

        return view('schulzeugnis::linear.ergebnis', [
            'meta'       => $meta,
            'schuljahr'  => $schuljahr,
            'ergebnis'   => $ergebnis,
            'nurNeu'     => $nurNeu,
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

    /** Fächer sind jahresübergreifend; gelesen wird der Zeitraum des aktiven (oder gewählten) Schuljahres. */
    private function schuljahrFuerFaecher(Request $request): ?Schuljahr
    {
        return $request->filled('schuljahr')
            ? Schuljahr::find((int) $request->input('schuljahr'))
            : Schuljahr::where('is_active', true)->first();
    }

    /** Bei „nur neue": nicht übernommene Änderungen im Ergebnis als unverändert zeigen. */
    private function aenderungenAlsNichtUebernommen(array $ergebnis): array
    {
        $anzahl = 0;
        foreach ($ergebnis['zeilen'] as &$r) {
            if ($r['status'] === 'aktualisiert') {
                $r['status']  = 'unveraendert';
                $r['hinweis'] = 'Änderung nicht übernommen: '.$r['hinweis'];
                $anzahl++;
            }
        }
        unset($r);
        $ergebnis['zaehl']['aktualisiert'] -= $anzahl;
        $ergebnis['zaehl']['unveraendert'] += $anzahl;

        return $ergebnis;
    }

    private function importer(string $art): object
    {
        $klasse = self::ARTEN[$art]['importer'];

        return new $klasse();
    }

    private function url(string $art, string $schritt, ?Schuljahr $schuljahr): string
    {
        $name = 'module.schulzeugnis.'.self::ARTEN[$art]['route'].'.linear.'.$schritt;

        return $art === 'faecher'
            ? route($name, $schuljahr ? ['schuljahr' => $schuljahr->id] : [])
            : route($name, $schuljahr);
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
