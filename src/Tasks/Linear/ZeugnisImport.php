<?php

namespace Intranet\Modules\Schulzeugnis\Tasks\Linear;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Ekkon\Ekkon;
use App\Ekkon\Tasks\EkkonTask;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Support\Import\FaecherImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\ImportFehler;
use Intranet\Modules\Schulzeugnis\Support\Import\KlasseImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrauftragImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrerImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\SchuelerImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\SchuljahrImporter;

/**
 * Überträgt die Schuljahres-Stammdaten aus Linear ins Zeugnis-Modul.
 *
 * ── Was der Task tut ─────────────────────────────────────────────────────────
 *
 * Linears `WD_Zeitraum` führt die Schuljahre (2025/2026 = ID 28); `WD_Schl`
 * (Schüler↔Klasse), `WD_Lehrer` (Lehrer↔Klasse), `WD_KlFach` und `WD_LehrFach`
 * (Fächer/Lehraufträge) hängen über diese ID am Jahr. Der Task erkennt
 * Zeiträume, die es im Zeugnis-Modul noch nicht gibt, legt sie an (INAKTIV –
 * aktiv setzt ein Mensch, dazu geht eine Benachrichtigung raus) und zieht
 * sie danach jede Nacht additiv nach: neue Schüler, Klassenwechsel, neue
 * Lehraufträge. Gelöscht oder geleert wird NIE – das garantieren die Importer
 * des Zeugnis-Moduls, die dieser Task füttert (dieselben, die auch der
 * manuelle CSV-Import benutzt; Reihenfolge Fächer → Lehrer → Klassen →
 * Schüler → Lehraufträge, weil spätere Importer Bezüge auf frühere auflösen).
 *
 * Standardmäßig wird AUSSCHLIESSLICH der neueste Jahrgang abgeglichen (höchste
 * WD_Zeitraum-ID mit Schülern): `Art`, `Von` und `Bis` sind in Linear nicht
 * gepflegt (Art durchweg NULL), taugen also nicht, um laufende von alten
 * Jahren zu trennen – die ID-Reihenfolge schon. Schaltet man die Einstellung
 * aus, werden alle Zeiträume mit Schülern abgeglichen, deren `Bis` nicht in
 * der Vergangenheit liegt.
 *
 * Der Klassenlehrer wird bewusst NICHT importiert: `WD_Lehrer.Funktion` ist
 * in Linear nicht gepflegt. Das Feld bleibt Handpflege im Zeugnis-Modul –
 * der KlasseImporter lässt es unangetastet, weil die Spalte im Datensatz
 * schlicht fehlt.
 *
 * ── Weiche Kopplung ──────────────────────────────────────────────────────────
 *
 * Das Zeugnis-Modul ist bewusst KEINE composer-Abhängigkeit dieses Pakets:
 * Der Task prüft zur Laufzeit, ob es (in ausreichender Version, samt
 * quell_id-Migration) installiert ist, und meldet sich sonst sauber ab.
 *
 * ── Zeitpunkt ────────────────────────────────────────────────────────────────
 *
 * 04:00, eine halbe Stunde nach Linear/BenutzerImport (03:30): Der legt die
 * Intranet-Konten mit `users.externe_id` an, sodass der LehrerImporter hier
 * die Konto-Verknüpfung (`core_user_id`) noch in derselben Nacht auflöst.
 */
class ZeugnisImport extends EkkonTask
{
    public string $category = 'Linear';

    /** Liest die Linear-Datenbank: bei Ausfall nicht starten, sondern nachholen (Core, 2026-10-01). */
    public bool $brauchtWawi = true;

    /**
     * Lehrer werden über users.externe_id zugeordnet, die Linear/BenutzerImport setzt: erst danach laufen.
     *
     * @var list<string>
     */
    public array $folgtAuf = ['Linear/BenutzerImport'];

    public string $description = 'Schuljahre samt Klassen, Fächern, Lehrern, Schülern und Lehraufträgen '
        .'aus Linear ins Zeugnis-Modul übernehmen (additiv, nie löschen).';

    public array $meldungsarten = [
        'linear-zeugnis-schuljahr-neu' => 'Linear: Neues Schuljahr im Zeugnis-Modul angelegt',
        'linear-zeugnis-import-fehler' => 'Linear: Zeugnis-Abgleich mit Fehlern',
    ];

    public array $einstellungen = [
        'probelauf' => [
            'typ' => 'ja_nein',
            'label' => 'Probelauf (nichts schreiben)',
            'standard' => true,
            'hilfe' => 'Liest aus Linear und berichtet je Schuljahr, was er täte – schreibt aber '
                .'nichts ins Zeugnis-Modul. Zum Scharfschalten das Häkchen entfernen.',
        ],
        'nur_neuester_jahrgang' => [
            'typ' => 'ja_nein',
            'label' => 'Ausschließlich neuesten Jahrgang',
            'standard' => true,
            'hilfe' => 'Abgeglichen wird nur der neueste Zeitraum mit Schülern (höchste ID in '
                .'WD_Zeitraum). Ausschalten, um alle Zeiträume mit Schülern abzugleichen, deren '
                .'Bis-Datum nicht in der Vergangenheit liegt – Vorsicht: Von/Bis sind in Linear '
                .'kaum gepflegt, das kann viele Altjahre hereinlassen.',
        ],
    ];

    /** Reihenfolge ist Pflicht: spätere Importer lösen Bezüge auf frühere auf. */
    private const IMPORTER = [
        'faecher'       => FaecherImporter::class,
        'lehrer'        => LehrerImporter::class,
        'klassen'       => KlasseImporter::class,
        'schueler'      => SchuelerImporter::class,
        'lehrauftraege' => LehrauftragImporter::class,
    ];

    private const ART_LABEL = [
        'faecher'       => 'Fächer',
        'lehrer'        => 'Lehrer',
        'klassen'       => 'Klassen',
        'schueler'      => 'Schüler',
        'lehrauftraege' => 'Lehraufträge',
    ];

    /** Akteur-Schnappschuss für das Zeugnis-Protokoll (Cron hat keinen auth()-User). */
    private const AKTEUR = 'System (Linear-Abgleich)';

    public function schedule(): string
    {
        return '0 4 * * *'; // nach Linear/BenutzerImport (03:30), s. Klassen-Docblock
    }

    public function run(): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine MSSQL-Zugangsdaten – siehe .env (MSSQL_*).');
        }

        // Weiche Kopplung: Der Check auf die Importer-Klasse (statt nur des Moduls)
        // fängt auch eine zu alte Modul-Version ab; `::class` löst kein Autoloading
        // aus, instanziiert wird erst nach dem Guard.
        if (! class_exists(SchuljahrImporter::class)) {
            $this->msg('Zeugnis-Modul nicht installiert (oder zu alt – SchuljahrImporter fehlt) – übersprungen.');

            return ['hinweis' => 'Zeugnis-Modul nicht installiert'];
        }
        if (! Schema::hasColumn('zeugnis_schuljahre', 'quell_id')) {
            $this->msg('Spalte zeugnis_schuljahre.quell_id fehlt – Migration des Zeugnis-Moduls noch nicht gelaufen? Übersprungen.');

            return ['hinweis' => 'Migration fehlt'];
        }

        $probelauf = (bool) $this->einstellung('probelauf');
        if ($probelauf) {
            $this->msg('PROBELAUF – es wird nichts geschrieben. Zum Scharfschalten das Häkchen oben unter „Einstellungen" entfernen.');
        }

        $zeitraeume = $this->zeitraeumeAusLinear();
        $this->debug['zeitraeume'] = $zeitraeume;

        $kandidaten = $this->kandidaten($zeitraeume);
        $this->msg(count($zeitraeume).' Zeiträume in Linear gelesen, davon '.count($kandidaten)
            .' im Sync-Umfang '.((bool) $this->einstellung('nur_neuester_jahrgang')
                ? '(nur neuester Jahrgang)' : '(mit Schülern, nicht abgelaufen)').'.');

        if ($kandidaten === []) {
            return ['probelauf' => $probelauf, 'zeitraeume_gelesen' => count($zeitraeume), 'im_sync_umfang' => 0];
        }

        // 1) Schuljahre anlegen bzw. mit ihrer Quell-ID verknüpfen.
        $importer   = new SchuljahrImporter();
        $kopf       = ['quellid', 'name', 'von', 'bis']; // bereits in normalisierter Form (CsvLeser::normalisiere)
        $sjZeilen   = array_map(fn (array $z): array => [
            'quellid' => (string) $z['id'],
            'name'    => $this->schuljahrName($z),
            'von'     => $z['von'],
            'bis'     => $z['bis'],
        ], $kandidaten);
        $sjKontext  = ['akteur_name' => self::AKTEUR];
        $sjErgebnis = $probelauf
            ? $importer->analysiere($kopf, $sjZeilen, $sjKontext)
            : $importer->importiere($kopf, $sjZeilen, $sjKontext);

        $sz = $sjErgebnis['zaehl'];
        $this->msg("Schuljahre: {$sz['neu']} neu, {$sz['aktualisiert']} aktualisiert/verknüpft, "
            ."{$sz['unveraendert']} unverändert, {$sz['warnung']} Warnungen, {$sz['fehler']} Fehler.");

        $gesamt = $sz;
        $jeSchuljahr = [];
        $neueSchuljahre = [];

        // Ergebniszeilen laufen 1:1 parallel zu den Eingabezeilen (zeile = index + 2).
        $statusJeKandidat = [];
        foreach ($sjErgebnis['zeilen'] as $r) {
            $statusJeKandidat[$r['zeile'] - 2] = $r;
        }

        // 2) Je Schuljahr die fünf Stammdaten-Importe.
        foreach ($kandidaten as $i => $z) {
            $name   = $this->schuljahrName($z);
            $status = $statusJeKandidat[$i]['status'] ?? 'fehler';

            if (in_array($status, ['warnung', 'fehler'], true)) {
                $this->msg("„{$name}“: übersprungen – ".($statusJeKandidat[$i]['hinweis'] ?? 'siehe Schuljahr-Bericht'));
                continue;
            }

            if ($status === 'neu') {
                $neueSchuljahre[] = ['quell_id' => (string) $z['id'], 'name' => $name];
            }

            // Im Probelauf existiert ein neues Jahr noch nicht – die Detail-Analyse
            // der Importer braucht aber eine Schuljahr-ID. Dann nur Rohzahlen melden.
            $sj = Schuljahr::where('quell_id', (string) $z['id'])->first()
                ?? Schuljahr::where('name', $name)->first();

            if (! $sj) {
                $roh = $this->rohzahlen($z['id']);
                $this->msg("„{$name}“ würde neu angelegt (inaktiv) – Rohdaten aus Linear: "
                    ."{$roh['faecher']} Fächer, {$roh['lehrer']} Lehrer, {$roh['klassen']} Klassen, "
                    ."{$roh['schueler']} Schüler, {$roh['lehrauftraege']} Lehraufträge. "
                    .'Detail-Analyse erst nach der Anlage (Probelauf ausschalten).');
                $jeSchuljahr[$name] = ['rohdaten' => $roh];
                continue;
            }

            try {
                $zaehlJahr = $this->stammdatenAbgleichen($sj, $z['id'], $probelauf);
            } catch (ImportFehler $e) {
                $this->msg("„{$name}“: Import-Fehler – {$e->getMessage()}");
                $gesamt['fehler']++;
                continue;
            }

            $jeSchuljahr[$name] = $zaehlJahr;
            foreach ($zaehlJahr as $zaehl) {
                foreach ($zaehl as $schluessel => $wert) {
                    $gesamt[$schluessel] += $wert;
                }
            }

            $this->msg("„{$name}“: ".implode(' · ', array_map(
                fn (string $art, array $za) => self::ART_LABEL[$art]." {$za['neu']} neu/{$za['aktualisiert']} akt./{$za['unveraendert']} unv."
                    .($za['warnung'] > 0 ? "/{$za['warnung']} Warn." : '')
                    .($za['fehler'] > 0 ? "/{$za['fehler']} FEHLER" : ''),
                array_keys($zaehlJahr),
                $zaehlJahr,
            )));
        }

        // 3) Benachrichtigungen – WER informiert wird, entscheiden die Routen.
        if (! $probelauf) {
            foreach ($neueSchuljahre as $neu) {
                $link = Route::has('module.schulzeugnis.schuljahre.index')
                    ? ":\n".route('module.schulzeugnis.schuljahre.index')
                    : '.';
                $this->benachrichtige(
                    'linear-zeugnis-schuljahr-neu',
                    "Neues Schuljahr {$neu['name']} aus Linear angelegt",
                    "Das Schuljahr wurde im Zeugnis-Modul angelegt und mit Stammdaten aus Linear befüllt. "
                        ."Es ist NICHT aktiv – bei Bedarf aktivieren unter Zeugnisse → Schuljahre{$link}",
                    ['quell_id' => $neu['quell_id']],
                    // Je Linear-Jahr genau einmal, für immer – auch bei mehrfachem Lauf.
                    'linear-zeugnis-schuljahr-'.$neu['quell_id'],
                );
            }

            // Nächtlicher Lauf ohne Zuschauer: stumme Fehler würden sonst monatelang faulen.
            if ($gesamt['fehler'] > 0) {
                $this->benachrichtige(
                    'linear-zeugnis-import-fehler',
                    "Zeugnis-Abgleich: {$gesamt['fehler']} Fehler",
                    "Der Linear-Abgleich hat {$gesamt['fehler']} fehlerhafte Zeilen übersprungen. "
                        .'Details in der Lauf-Historie des Tasks Linear/ZeugnisImport.',
                    ['gesamt' => $gesamt],
                    'linear-zeugnis-fehler-'.now()->toDateString().'-'.$gesamt['fehler'],
                );
            }
        }

        return [
            'probelauf'          => $probelauf,
            'zeitraeume_gelesen' => count($zeitraeume),
            'im_sync_umfang'     => count($kandidaten),
            'schuljahre_neu'     => array_column($neueSchuljahre, 'name'),
            'gesamt'             => $gesamt,
            'je_schuljahr'       => $jeSchuljahr,
        ];
    }

    /**
     * Alle Zeiträume aus Linear, mit Schülerzahl als Relevanz-Signal.
     *
     * @return array<int,array<string,mixed>>
     */
    private function zeitraeumeAusLinear(): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT z.ID, z.Bezeichnung, z.Art, z.Von, z.Bis, z.Jahrgang,
                    (SELECT COUNT(*) FROM WD_Schl s WHERE s.ID = z.ID) AS SchuelerZahl
               FROM WD_Zeitraum z
              ORDER BY z.ID'
        );

        return array_map(function (object $zeile): array {
            $w = $this->normalisiere($zeile);

            return [
                'id'            => (int) $w['ID'],
                'bezeichnung'   => (string) ($w['Bezeichnung'] ?? ''),
                'art'           => (string) ($w['Art'] ?? ''),
                'von'           => $this->datum($w['Von'] ?? ''),
                'bis'           => $this->datum($w['Bis'] ?? ''),
                'jahrgang'      => (string) ($w['Jahrgang'] ?? ''),
                'schueler_zahl' => (int) ($w['SchuelerZahl'] ?? 0),
            ];
        }, $zeilen);
    }

    /**
     * Sync-Umfang. Standard: nur der neueste Zeitraum mit Schülern (höchste ID)
     * – Art/Von/Bis sind in Linear nicht gepflegt, die ID-Reihenfolge ist das
     * einzig verlässliche Signal für „aktuell". Ohne die Einstellung gelten
     * alle Zeiträume mit Schülern, deren Bis nicht in der Vergangenheit liegt.
     *
     * @param  array<int,array<string,mixed>>  $zeitraeume
     * @return array<int,array<string,mixed>>
     */
    private function kandidaten(array $zeitraeume): array
    {
        $mitSchuelern = array_values(array_filter(
            $zeitraeume,
            fn (array $z): bool => $z['schueler_zahl'] > 0, // Verwaltungsreste ohne Schüler raus
        ));

        if ((bool) $this->einstellung('nur_neuester_jahrgang')) {
            // Höchste ID gewinnt; die Liste kommt bereits nach ID sortiert.
            return $mitSchuelern === [] ? [] : [end($mitSchuelern)];
        }

        $heute = now()->toDateString();

        return array_values(array_filter(
            $mitSchuelern,
            fn (array $z): bool => $z['bis'] === '' || $z['bis'] >= $heute, // abgelaufen = Altbestand
        ));
    }

    /** @param array<string,mixed> $z */
    private function schuljahrName(array $z): string
    {
        return $z['bezeichnung'] !== '' ? $z['bezeichnung']
            : ($z['jahrgang'] !== '' ? $z['jahrgang'] : 'Zeitraum '.$z['id']);
    }

    /**
     * Die fünf Stammdaten-Importe für ein (existierendes) Schuljahr.
     *
     * @return array<string,array<string,int>>  zaehl je Import-Art
     */
    private function stammdatenAbgleichen(Schuljahr $sj, int $zid, bool $probelauf): array
    {
        $daten = [
            'faecher'       => $this->faecherAusLinear($zid),
            'lehrer'        => $this->lehrerAusLinear($zid),
            'klassen'       => $this->klassenAusLinear($zid),
            'schueler'      => $this->schuelerAusLinear($zid),
            'lehrauftraege' => $this->lehrauftraegeAusLinear($zid),
        ];

        $kontext = ['schuljahr_id' => $sj->id, 'akteur_name' => self::AKTEUR];
        $zaehl   = [];

        foreach (self::IMPORTER as $art => $klasse) {
            [$kopf, $zeilen] = $daten[$art];
            $importer = new $klasse();
            $ergebnis = $probelauf
                ? $importer->analysiere($kopf, $zeilen, $kontext)
                : $importer->importiere($kopf, $zeilen, $kontext);
            $zaehl[$art] = $ergebnis['zaehl'];

            // Zahlen allein verraten nicht, ob Namen/Klassen richtig zusammengesetzt
            // wurden – daran scheitert ein Import aus 20 Jahren Datenbestand zuerst.
            $this->debug['beispiele'][$sj->name][$art] = array_slice($zeilen, 0, 3);
        }

        return $zaehl;
    }

    /**
     * Nur die im Jahr tatsächlich referenzierten Fächer – WD_Fach komplett zu
     * importieren würde den gepflegten Fächerkatalog mit Altlast fluten.
     * Bewusst ohne Kürzel: Der FaecherImporter matcht bevorzugt über das Kürzel,
     * ein numerischer Linear-Schlüssel würde gepflegte Kürzel („Ma") überschreiben.
     * Der Name ist zugleich der Schlüssel, über den der LehrauftragImporter das
     * Fach auflöst – die Kette bleibt in sich konsistent.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    private function faecherAusLinear(int $zid): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT f.Fach, f.Bezeichnung
               FROM WD_Fach f
              WHERE f.Fach IN (SELECT kf.Fach FROM WD_KlFach kf WHERE kf.ID = ?)
                 OR f.Fach IN (SELECT lf.Fach FROM WD_LehrFach lf WHERE lf.ID = ?)',
            [$zid, $zid],
        );

        $ergebnis = [];
        $gesehen  = [];
        foreach ($zeilen as $zeile) {
            $w    = $this->normalisiere($zeile);
            $name = (string) ($w['Bezeichnung'] ?? '');
            // Ohne Bezeichnung unbrauchbar; zwei Fach-Nummern mit gleichem Namen
            // wären für den Importer ein Datei-Duplikat – vorab zusammenziehen.
            if ($name === '' || isset($gesehen[mb_strtolower($name)])) {
                continue;
            }
            $gesehen[mb_strtolower($name)] = true;
            $ergebnis[] = ['name' => $name];
        }

        return [['name'], $ergebnis];
    }

    /**
     * Alle im Jahr vorkommenden Lehrkräfte (Klassenzuordnung ∪ Lehraufträge).
     * AdrNr wird quell_id – dieselbe ID, die der BenutzerImport als
     * users.externe_id setzt; darüber löst der LehrerImporter das Konto auf.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    private function lehrerAusLinear(int $zid): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT a.AdrNr, a.Vorname, a.Nachname
               FROM Adresse a
              WHERE a.AdrNr IN (SELECT l.AdrNr FROM WD_Lehrer l WHERE l.ID = ?)
                 OR a.AdrNr IN (SELECT lf.AdrNr FROM WD_LehrFach lf WHERE lf.ID = ?)',
            [$zid, $zid],
        );

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $w = $this->normalisiere($zeile);
            // Zeilen ohne Namen laufen durch und zählen im Importer als Fehler –
            // solche Adressen sollen auffallen, nicht verschwinden.
            $ergebnis[] = [
                'quellid'  => (string) (int) $w['AdrNr'],
                'vorname'  => (string) ($w['Vorname'] ?? ''),
                'nachname' => (string) ($w['Nachname'] ?? ''),
            ];
        }

        return [['quellid', 'vorname', 'nachname'], $ergebnis];
    }

    /**
     * Klassen = Schülerbestand des Jahres (WD_Schl gruppiert); Stufe aus WD_Schl.
     * Bewusst ohne Format- und Klassenlehrer-Spalte: Fehlt eine Spalte, lässt
     * der KlasseImporter das Feld unangetastet – genau das gewünschte „nie
     * leeren". Der Klassenlehrer bleibt Handpflege im Zeugnis-Modul, weil
     * WD_Lehrer.Funktion in Linear nicht gepflegt ist.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    private function klassenAusLinear(int $zid): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT s.Klasse, MIN(s.Stufe) AS Stufe, COUNT(*) AS SchuelerZahl
               FROM WD_Schl s
              WHERE s.ID = ?
              GROUP BY s.Klasse',
            [$zid],
        );

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $w      = $this->normalisiere($zeile);
            $klasse = (string) ($w['Klasse'] ?? '');
            if ($klasse === '') {
                continue;
            }
            $ergebnis[] = [
                'klasse' => $klasse,
                'stufe'  => (string) ($w['Stufe'] ?? ''),
            ];
        }

        return [['klasse', 'stufe'], $ergebnis];
    }

    /**
     * Schüler des Jahres samt Personendaten. Zeilen mit AustrittKl werden
     * MITimportiert (additiv, nie ausdünnen), aber im Debug gelistet – ob
     * Ausgetretene stören, entscheidet sich nach dem ersten Probelauf.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    private function schuelerAusLinear(int $zid): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT s.Klasse, s.AdrNr, s.AustrittKl,
                    a.Vorname, a.Nachname, a.Geburtsdatum, a.Geburtsort, a.Geschlecht, a.MW
               FROM WD_Schl s
               JOIN Adresse a ON a.AdrNr = s.AdrNr
              WHERE s.ID = ?',
            [$zid],
        );

        $ergebnis    = [];
        $ausgetreten = [];
        foreach ($zeilen as $zeile) {
            $w        = $this->normalisiere($zeile);
            $vorname  = (string) ($w['Vorname'] ?? '');
            $nachname = (string) ($w['Nachname'] ?? '');

            $ergebnis[] = [
                'schuelerid'   => (string) (int) $w['AdrNr'],
                'klasse'       => (string) ($w['Klasse'] ?? ''),
                'vorname'      => $vorname,
                'nachname'     => $nachname,
                'geburtsdatum' => $this->datum($w['Geburtsdatum'] ?? ''),
                'geburtsort'   => (string) ($w['Geburtsort'] ?? ''),
                'geschlecht'   => $this->geschlecht($w),
            ];

            $austritt = $this->datum($w['AustrittKl'] ?? '');
            if ($austritt !== '' && count($ausgetreten) < 50) {
                $ausgetreten[] = trim("{$vorname} {$nachname}").' ('.($w['Klasse'] ?? '?').', Austritt '.$austritt.')';
            }
        }

        if ($ausgetreten !== []) {
            $this->debug['ausgetreten'][$zid] = $ausgetreten;
        }

        return [['schuelerid', 'klasse', 'vorname', 'nachname', 'geburtsdatum', 'geburtsort', 'geschlecht'], $ergebnis];
    }

    /**
     * Lehraufträge des Jahres. Fach über die Bezeichnung (konsistent zum
     * Fächer-Import ohne Kürzel), Lehrer über die AdrNr (= quell_id).
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    private function lehrauftraegeAusLinear(int $zid): array
    {
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            'SELECT lf.Klasse, lf.AdrNr, f.Bezeichnung
               FROM WD_LehrFach lf
               JOIN WD_Fach f ON f.Fach = lf.Fach
              WHERE lf.ID = ?',
            [$zid],
        );

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $w = $this->normalisiere($zeile);
            $ergebnis[] = [
                'klasse'   => (string) ($w['Klasse'] ?? ''),
                'fach'     => (string) ($w['Bezeichnung'] ?? ''),
                'lehrerid' => (string) (int) $w['AdrNr'],
            ];
        }

        return [['klasse', 'fach', 'lehrerid'], $ergebnis];
    }

    /**
     * Rohzahlen für ein Jahr, das erst noch angelegt würde (Probelauf).
     *
     * @return array<string,int>
     */
    private function rohzahlen(int $zid): array
    {
        $db   = DB::connection(Ekkon::mssqlConnection());
        $eins = fn (string $sql, array $bindings): int => (int) ($db->select($sql, $bindings)[0]->n ?? 0);

        return [
            'faecher' => $eins(
                'SELECT COUNT(DISTINCT f.Bezeichnung) AS n FROM WD_Fach f
                  WHERE f.Fach IN (SELECT kf.Fach FROM WD_KlFach kf WHERE kf.ID = ?)
                     OR f.Fach IN (SELECT lf.Fach FROM WD_LehrFach lf WHERE lf.ID = ?)',
                [$zid, $zid],
            ),
            'lehrer' => $eins(
                'SELECT COUNT(*) AS n FROM Adresse a
                  WHERE a.AdrNr IN (SELECT l.AdrNr FROM WD_Lehrer l WHERE l.ID = ?)
                     OR a.AdrNr IN (SELECT lf.AdrNr FROM WD_LehrFach lf WHERE lf.ID = ?)',
                [$zid, $zid],
            ),
            'klassen'       => $eins('SELECT COUNT(DISTINCT s.Klasse) AS n FROM WD_Schl s WHERE s.ID = ?', [$zid]),
            'schueler'      => $eins('SELECT COUNT(*) AS n FROM WD_Schl s WHERE s.ID = ?', [$zid]),
            'lehrauftraege' => $eins('SELECT COUNT(*) AS n FROM WD_LehrFach lf WHERE lf.ID = ?', [$zid]),
        ];
    }

    /**
     * Rohzeile in saubere Werte übersetzen.
     *
     * ODBC liefert SQL-NULL als leeren String, Zahlen als String und char(n)-
     * Spalten Blank-gepolstert – deshalb wird hier einmal an der Grenze
     * aufgeräumt, statt später überall zu raten.
     *
     * @return array<string,mixed>
     */
    private function normalisiere(object $zeile): array
    {
        return array_map(
            fn ($wert) => is_string($wert) ? trim($wert) : $wert,
            (array) $zeile,
        );
    }

    /**
     * Datetime-Rohwert (z. B. "2025-08-01 00:00:00.000") → "Y-m-d" oder ''.
     * Unlesbares wird gezählt statt geraten – ein Falschdatum wäre schlimmer
     * als ein leeres Feld.
     */
    private function datum(mixed $roh): string
    {
        if ($roh instanceof \DateTimeInterface) {
            return $roh->format('Y-m-d');
        }

        $roh = is_string($roh) ? trim($roh) : '';
        if ($roh === '') {
            return '';
        }

        $kurz = substr($roh, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $kurz)) {
            return $kurz;
        }

        try {
            return (new \DateTimeImmutable($roh))->format('Y-m-d');
        } catch (\Exception) {
            $this->debug['datum_unlesbar'][$roh] = ($this->debug['datum_unlesbar'][$roh] ?? 0) + 1;

            return '';
        }
    }

    /**
     * Geschlecht aus den zwei konkurrierenden Linear-Spalten (Geschlecht, MW).
     * Der erste mappbare Wert gewinnt; Unmappbares bleibt leer (kein Warnungs-
     * Rauschen im Importer) und wird im Debug gezählt – die Semantik der beiden
     * Spalten lernt man aus dem ersten Probelauf.
     *
     * @param array<string,mixed> $werte
     */
    private function geschlecht(array $werte): string
    {
        foreach (['Geschlecht', 'MW'] as $spalte) {
            $roh = mb_strtolower((string) ($werte[$spalte] ?? ''));
            if ($roh === '') {
                continue;
            }

            $wert = match ($roh) {
                'm', 'männlich', 'maennlich', 'male', 'junge' => 'm',
                'w', 'f', 'weiblich', 'female', 'mädchen', 'maedchen' => 'w',
                'd', 'divers', 'diverse', 'x' => 'd',
                default => null,
            };
            if ($wert !== null) {
                return $wert;
            }

            $key = "{$spalte}={$roh}";
            $this->debug['geschlecht_unbekannt'][$key] = ($this->debug['geschlecht_unbekannt'][$key] ?? 0) + 1;
        }

        return '';
    }
}
