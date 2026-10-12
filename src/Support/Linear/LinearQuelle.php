<?php

namespace Intranet\Modules\Schulzeugnis\Support\Linear;

use App\Ekkon\Ekkon;
use Illuminate\Support\Facades\DB;

/**
 * Liest die Schuljahres-Stammdaten aus Linear (MSSQL) in der Zeilenform, die die
 * Importer des Moduls erwarten (Kopf bereits normalisiert wie CsvLeser::normalisiere).
 *
 * Gemeinsame Quelle für den nächtlichen Task Linear/ZeugnisImport und die
 * „Aus Linear importieren"-Knöpfe auf den Verwaltungsseiten – damit beide genau
 * dieselben Daten sehen.
 *
 * Linears `WD_Zeitraum` führt die Schuljahre; `WD_Schl` (Schüler↔Klasse),
 * `WD_Lehrer` (Lehrer↔Klasse), `WD_KlFach` und `WD_LehrFach` (Fächer/Lehraufträge)
 * hängen über dessen ID am Jahr.
 */
class LinearQuelle
{
    /** Auffälligkeiten beim Lesen (unlesbare Daten, unbekannte Geschlechts-Werte, Ausgetretene). */
    public array $debug = [];

    public function verfuegbar(): bool
    {
        return Ekkon::mssqlKonfiguriert();
    }

    /**
     * Alle Zeiträume aus Linear, mit Schülerzahl als Relevanz-Signal.
     *
     * @return array<int,array<string,mixed>>
     */
    public function zeitraeume(): array
    {
        $zeilen = $this->db()->select(
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

    /** Anzeigename eines Zeitraums (Bezeichnung, sonst Jahrgang, sonst ID). @param array<string,mixed> $z */
    public static function zeitraumName(array $z): string
    {
        return $z['bezeichnung'] !== '' ? $z['bezeichnung']
            : ($z['jahrgang'] !== '' ? $z['jahrgang'] : 'Zeitraum '.$z['id']);
    }

    /** Daten einer Import-Art. @return array{0: array<int,string>, 1: array<int,array<string,string>>} */
    public function daten(string $art, int $zid): array
    {
        return match ($art) {
            'faecher'       => $this->faecher($zid),
            'lehrer'        => $this->lehrer($zid),
            'klassen'       => $this->klassen($zid),
            'schueler'      => $this->schueler($zid),
            'lehrauftraege' => $this->lehrauftraege($zid),
        };
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
    public function faecher(int $zid): array
    {
        $zeilen = $this->db()->select(
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
    public function lehrer(int $zid): array
    {
        $zeilen = $this->db()->select(
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
    public function klassen(int $zid): array
    {
        $zeilen = $this->db()->select(
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
     * Schüler des Jahres samt Personendaten und Austrittsdatum (AustrittKl). Ausgetretene
     * werden mitimportiert; ob sie ein Zeugnis bekommen, entscheidet der SchuelerImporter
     * am Schuljahresende (früher ausgetreten → gesperrt).
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public function schueler(int $zid): array
    {
        $zeilen = $this->db()->select(
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
                'austritt'     => $this->datum($w['AustrittKl'] ?? ''),
            ];

            $austritt = $ergebnis[array_key_last($ergebnis)]['austritt'];
            if ($austritt !== '') {
                $ausgetreten[] = trim("{$vorname} {$nachname}").' ('.($w['Klasse'] ?? '?').', Austritt '.$austritt.')';
            }
        }

        if ($ausgetreten !== []) {
            $this->debug['ausgetreten'][$zid] = $ausgetreten;
        }

        return [['schuelerid', 'klasse', 'vorname', 'nachname', 'geburtsdatum', 'geburtsort', 'geschlecht', 'austritt'], $ergebnis];
    }

    /**
     * Lehraufträge des Jahres. Fach über die Bezeichnung (konsistent zum
     * Fächer-Import ohne Kürzel), Lehrer über die AdrNr (= quell_id).
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public function lehrauftraege(int $zid): array
    {
        $zeilen = $this->db()->select(
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
    public function rohzahlen(int $zid): array
    {
        $db   = $this->db();
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

    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(Ekkon::mssqlConnection());
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
     * Rauschen im Importer) und wird im Debug gezählt.
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
