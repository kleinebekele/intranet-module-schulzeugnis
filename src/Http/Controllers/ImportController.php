<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Support\Import\CsvLeser;
use Intranet\Modules\Schulzeugnis\Support\Import\FaecherImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\ImportFehler;
use Intranet\Modules\Schulzeugnis\Support\Import\KlasseImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrerImporter;

/**
 * Stammdaten-Import aus dem Schulverwaltungsprogramm (Linear/MSSQL → CSV im storage).
 *
 * Ablauf je Import: Datei wählen (Upload ODER aus dem storage-Ordner) → Trockenlauf
 * mit Vorschau (nichts wird geschrieben) → Bestätigen → Schreiben in einer Transaktion
 * + Protokoll. Additiv, nie löschen. Erste Import-Art: Fächer (jahresübergreifend).
 */
class ImportController
{
    /** Verfügbare Import-Arten. Neue Arten hier registrieren (eigener Importer). */
    private const ARTEN = [
        'faecher' => [
            'titel'     => 'Fächer',
            'hinweis'   => 'Jahresübergreifender Fächerkatalog. Wiedererkennung über das Kürzel (sonst den Namen).',
            'schuljahr' => false,
            'importer'  => FaecherImporter::class,
            'weiter'    => ['route' => 'module.schulzeugnis.faecher.index', 'label' => 'Zu den Fächern'],
            'spalten'   => [
                ['name' => 'Name',        'pflicht' => true,  'info' => 'Fachname, z. B. „Mathematik"'],
                ['name' => 'Kuerzel',     'pflicht' => false, 'info' => 'z. B. „Ma" – dient als Wiedererkennungs-Schlüssel'],
                ['name' => 'Reihenfolge', 'pflicht' => false, 'info' => 'Zahl für die Sortierung (Standard 0)'],
                ['name' => 'Aktiv',       'pflicht' => false, 'info' => 'ja / nein (Standard ja)'],
            ],
            'beispiel'  => "Name;Kuerzel;Reihenfolge;Aktiv\nMathematik;Ma;10;ja\nDeutsch;De;20;ja\nEnglisch;En;30;ja",
        ],
        'lehrer' => [
            'titel'     => 'Lehrer',
            'hinweis'   => 'Lehrer je Schuljahr. Verknüpfung ans Intranet-Konto über die externe ID (Linear = users.externe_id); fehlt ein Konto, wird der Lehrer trotzdem angelegt und der tägliche Abgleich zieht die Verknüpfung nach.',
            'schuljahr' => true,
            'importer'  => LehrerImporter::class,
            'weiter'    => ['route' => 'module.schulzeugnis.lehrer.jahr', 'label' => 'Zu den Lehrern'],
            'spalten'   => [
                ['name' => 'ExterneID', 'pflicht' => false, 'info' => 'Stabile ID aus Linear – entspricht users.externe_id (Wiedererkennung + Konto-Verknüpfung)'],
                ['name' => 'Vorname',   'pflicht' => true,  'info' => 'Vorname der Lehrkraft'],
                ['name' => 'Nachname',  'pflicht' => true,  'info' => 'Nachname der Lehrkraft'],
            ],
            'beispiel'  => "ExterneID;Vorname;Nachname\nL-1042;Anna;Muster\nL-1043;Bob;Beispiel",
        ],
        'klassen' => [
            'titel'     => 'Klassen',
            'hinweis'   => 'Klassen je Schuljahr. Wiedererkennung über den Klassennamen. Stufe/Format per Name, Klassenlehrer per externe ID – jeweils nur, wenn schon vorhanden (Lehrer zuerst importieren).',
            'schuljahr' => true,
            'importer'  => KlasseImporter::class,
            'weiter'    => ['route' => 'module.schulzeugnis.klassen.jahr', 'label' => 'Zu den Klassen'],
            'spalten'   => [
                ['name' => 'Klasse',          'pflicht' => true,  'info' => 'Name der Klasse, z. B. „5a" (Wiedererkennung je Schuljahr)'],
                ['name' => 'Stufe',           'pflicht' => false, 'info' => 'Name einer vorhandenen Schulstufe (wird nicht neu angelegt)'],
                ['name' => 'Standardformat',  'pflicht' => false, 'info' => 'Name eines vorhandenen Zeugnisformats (Fachzeugnis-Vorlage)'],
                ['name' => 'KlassenlehrerID', 'pflicht' => false, 'info' => 'externe ID (Linear) der Lehrkraft – muss als Lehrer im Schuljahr existieren'],
            ],
            'beispiel'  => "Klasse;Stufe;Standardformat;KlassenlehrerID\n5a;Unterstufe;Textzeugnis 5-8;L-1042\n6b;Mittelstufe;Textzeugnis 5-8;L-1043",
        ],
    ];

    /** Fester Ablage-Ordner für per SFTP/SSH bereitgestellte CSV-Dateien. */
    private const STORAGE_UNTERORDNER = 'app/zeugnis-import';

    /** Kurzlebiger Ablageort zwischen Vorschau und Bestätigung. */
    private const TEMP_UNTERORDNER = 'app/schulzeugnis-import-tmp';

    public function index()
    {
        $dir = storage_path(self::STORAGE_UNTERORDNER);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $dateien = [];
        foreach (glob($dir . '/*.csv') ?: [] as $pfad) {
            $dateien[] = basename($pfad);
        }
        sort($dateien);

        $schuljahre = Schuljahr::orderByDesc('is_active')->orderByDesc('start_date')->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $aktivId = optional($schuljahre->firstWhere('is_active', true))->id ?? optional($schuljahre->first())->id;

        // Arten, die ein Ziel-Schuljahr brauchen (für das Ein-/Ausblenden im UI).
        $schuljahrArten = array_keys(array_filter(self::ARTEN, fn ($a) => $a['schuljahr']));

        return view('schulzeugnis::import.index', [
            'arten'          => self::ARTEN,
            'dateien'        => $dateien,
            'ordner'         => 'storage/' . self::STORAGE_UNTERORDNER,
            'schuljahre'     => $schuljahre,
            'aktivId'        => $aktivId,
            'schuljahrArten' => $schuljahrArten,
        ]);
    }

    public function vorschau(Request $request)
    {
        $art = (string) $request->input('art');
        if (! isset(self::ARTEN[$art])) {
            return redirect()->route('module.schulzeugnis.import.index')->with('error', 'Unbekannte Import-Art.');
        }

        $request->validate([
            'datei'         => ['nullable', 'file', 'max:10240'],
            'storage_datei' => ['nullable', 'string'],
        ], [], ['datei' => 'CSV-Datei']);

        $schuljahr = null;
        if (self::ARTEN[$art]['schuljahr']) {
            $schuljahr = Schuljahr::find((int) $request->input('schuljahr_id'));
            if (! $schuljahr) {
                return back()->with('error', 'Bitte ein gültiges Ziel-Schuljahr wählen.');
            }
        }

        try {
            $quelle  = $this->quelleNachTemp($request);
            $csv     = CsvLeser::lesen($quelle['pfad']);
            $analyse = $this->importer($art)->analysiere($csv['kopf'], $csv['zeilen'], $this->kontext($schuljahr));
        } catch (ImportFehler $e) {
            if (isset($quelle['pfad'])) {
                @unlink($quelle['pfad']);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($csv['zeilen'] === []) {
            @unlink($quelle['pfad']);

            return back()->with('error', 'Die Datei enthält keine Datenzeilen (nur Kopfzeile oder leer).');
        }

        return view('schulzeugnis::import.vorschau', [
            'art'       => $art,
            'meta'      => self::ARTEN[$art],
            'token'     => basename($quelle['pfad'], '.csv'),
            'analyse'   => $analyse,
            'dateiname' => $quelle['name'],
            'schuljahr' => $schuljahr,
        ]);
    }

    public function ausfuehren(Request $request)
    {
        $art = (string) $request->input('art');
        if (! isset(self::ARTEN[$art])) {
            return redirect()->route('module.schulzeugnis.import.index')->with('error', 'Unbekannte Import-Art.');
        }

        $token = (string) $request->input('token');
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return redirect()->route('module.schulzeugnis.import.index')
                ->with('error', 'Ungültiger Import-Vorgang. Bitte erneut starten.');
        }

        $schuljahr = null;
        if (self::ARTEN[$art]['schuljahr']) {
            $schuljahr = Schuljahr::find((int) $request->input('schuljahr_id'));
            if (! $schuljahr) {
                return redirect()->route('module.schulzeugnis.import.index')
                    ->with('error', 'Ungültiges Ziel-Schuljahr. Bitte erneut starten.');
            }
        }

        $pfad = storage_path(self::TEMP_UNTERORDNER . '/' . $token . '.csv');
        if (! is_file($pfad)) {
            return redirect()->route('module.schulzeugnis.import.index')
                ->with('error', 'Der Import-Vorgang ist abgelaufen. Bitte die Datei erneut hochladen bzw. wählen.');
        }

        try {
            $csv      = CsvLeser::lesen($pfad);
            $ergebnis = $this->importer($art)->importiere($csv['kopf'], $csv['zeilen'], $this->kontext($schuljahr));
        } catch (ImportFehler $e) {
            @unlink($pfad);

            return redirect()->route('module.schulzeugnis.import.index')->with('error', $e->getMessage());
        }

        @unlink($pfad);

        // Weiter-Link (art-abhängig; Lehrer springt ins Ziel-Schuljahr).
        $weiter = self::ARTEN[$art]['weiter'];
        $weiterUrl = $schuljahr
            ? route($weiter['route'], $schuljahr)
            : route($weiter['route']);

        return view('schulzeugnis::import.ergebnis', [
            'art'         => $art,
            'meta'        => self::ARTEN[$art],
            'ergebnis'    => $ergebnis,
            'dateiname'   => (string) $request->input('dateiname', 'Import'),
            'schuljahr'   => $schuljahr,
            'weiterUrl'   => $weiterUrl,
            'weiterLabel' => $weiter['label'],
        ]);
    }

    /** Einen Importer für die gewählte Art erzeugen. */
    private function importer(string $art): object
    {
        $klasse = self::ARTEN[$art]['importer'];

        return new $klasse();
    }

    /** Kontext für den Importer (Ziel-Schuljahr, falls die Art eines braucht). */
    private function kontext(?Schuljahr $schuljahr): array
    {
        return $schuljahr ? ['schuljahr_id' => $schuljahr->id] : [];
    }

    /**
     * Quelle (Upload oder storage-Auswahl) in eine kurzlebige Temp-Datei kopieren.
     *
     * @return array{pfad: string, name: string}
     */
    private function quelleNachTemp(Request $request): array
    {
        $dir = storage_path(self::TEMP_UNTERORDNER);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->tempAufraeumen($dir);

        $token = Str::random(40);
        $ziel  = $dir . '/' . $token . '.csv';

        if ($request->hasFile('datei')) {
            $datei = $request->file('datei');
            if (! $datei->isValid()) {
                throw new ImportFehler('Die hochgeladene Datei ist ungültig.');
            }
            $name = $datei->getClientOriginalName();
            $datei->move($dir, $token . '.csv');

            return ['pfad' => $ziel, 'name' => $name];
        }

        $auswahl = trim((string) $request->input('storage_datei'));
        if ($auswahl !== '') {
            $quelle = storage_path(self::STORAGE_UNTERORDNER . '/' . basename($auswahl));
            if (! is_file($quelle)) {
                throw new ImportFehler('Die gewählte Datei wurde im Import-Ordner nicht gefunden.');
            }
            if (! @copy($quelle, $ziel)) {
                throw new ImportFehler('Die gewählte Datei konnte nicht gelesen werden.');
            }

            return ['pfad' => $ziel, 'name' => basename($auswahl)];
        }

        throw new ImportFehler('Bitte eine CSV-Datei hochladen oder aus dem Import-Ordner wählen.');
    }

    /** Verwaiste Temp-Dateien (älter als eine Stunde) aufräumen. */
    private function tempAufraeumen(string $dir): void
    {
        foreach (glob($dir . '/*.csv') ?: [] as $pfad) {
            if (is_file($pfad) && filemtime($pfad) < time() - 3600) {
                @unlink($pfad);
            }
        }
    }
}
