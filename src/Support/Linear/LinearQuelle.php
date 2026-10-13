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
     * Alle Lehrkräfte: Kennzeichen `Adresse.Lehrer = 'J'` – auch ohne Lehrauftrag im Jahr,
     * der fehlt in Linear manchmal nur – plus alle, die im Jahr einer Klasse zugeordnet
     * sind oder einen Lehrauftrag haben (falls dort das Kennzeichen fehlt).
     * AdrNr wird quell_id – dieselbe ID, die der BenutzerImport als
     * users.externe_id setzt; darüber löst der LehrerImporter das Konto auf.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public function lehrer(int $zid): array
    {
        $zeilen = $this->db()->select(
            "SELECT a.AdrNr, a.Vorname, a.Nachname
               FROM Adresse a
              WHERE a.Lehrer = 'J'
                 OR a.AdrNr IN (SELECT l.AdrNr FROM WD_Lehrer l WHERE l.ID = ?)
                 OR a.AdrNr IN (SELECT lf.AdrNr FROM WD_LehrFach lf WHERE lf.ID = ?)",
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
     * Schüler des Jahres samt Personendaten und – nur wenn es eins gibt – Austritt.
     *
     * `WD_Schl.AustrittKl` ist das Ende der Klassenzugehörigkeit im Zeitraum: Bei fast
     * jedem Kind steht dort der reguläre letzte Schultag (Fehlgriff v1.6.0: alle galten
     * als ausgetreten). Als Austritt zählt deshalb nur ein Datum VOR diesem regulären
     * Klassenende – das häufigste AustrittKl des Zeitraums. Alles andere bleibt leer.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public function schueler(int $zid): array
    {
        $zeilen = $this->db()->select(
            'SELECT s.Klasse, s.AdrNr, CONVERT(varchar(10), s.AustrittKl, 120) AS AustrittKl,
                    a.Vorname, a.Nachname, a.Geburtsdatum, a.Geburtsort, a.Geschlecht, a.MW
               FROM WD_Schl s
               JOIN Adresse a ON a.AdrNr = s.AdrNr
              WHERE s.ID = ?',
            [$zid],
        );

        $ergebnis = [];
        foreach ($zeilen as $zeile) {
            $w        = $this->normalisiere($zeile);
            $vorname  = (string) ($w['Vorname'] ?? '');
            $nachname = (string) ($w['Nachname'] ?? '');
            $ende     = $this->datum($w['AustrittKl'] ?? '');

            $ergebnis[] = [
                'schuelerid'   => (string) (int) $w['AdrNr'],
                'klasse'       => (string) ($w['Klasse'] ?? ''),
                'vorname'      => $vorname,
                'nachname'     => $nachname,
                'geburtsdatum' => $this->datum($w['Geburtsdatum'] ?? ''),
                'geburtsort'   => (string) ($w['Geburtsort'] ?? ''),
                'geschlecht'   => $this->geschlecht($w),
                'austritt'     => $ende, // unten auf echte Austritte reduziert
            ];
        }

        [$ergebnis, $this->debug['klassenende'][$zid]] = self::nurEchteAustritte($ergebnis);

        return [['schuelerid', 'klasse', 'vorname', 'nachname', 'geburtsdatum', 'geburtsort', 'geschlecht', 'austritt'], $ergebnis];
    }

    /**
     * Was Linear über Personen weiß, die im Zeitraum NICHT (mehr) als Schüler bzw. Lehrer
     * geführt werden: Kennzeichen aus `Adresse` (Schuler/Lehrer + alles, was nach
     * ehemalig/Austritt/Abgang klingt) und der letzte Eintrag in `WD_Schl` bzw.
     * `WD_Lehrer`/`WD_LehrFach`.
     *
     * Die Spalten kommen aus INFORMATION_SCHEMA statt `SELECT *` – lange Text-/LOB-Spalten
     * würden über PDO_ODBC die folgenden Werte zerstören; Datumswerte serverseitig als Text.
     *
     * @param  array<int,string>  $adrNrs
     * @return array<string,array{felder: array<string,string>, letzter: ?array<string,string>}>
     */
    public function personenStatus(array $adrNrs, string $rolle = 'schueler'): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($a) => is_numeric($a) ? (int) $a : null, $adrNrs))));
        if ($ids === []) {
            return [];
        }

        $spalten = $this->db()->select(
            "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH AS LEN
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = 'Adresse'"
        );
        $auswahl = [];
        foreach ($spalten as $sp) {
            $name = trim((string) $sp->COLUMN_NAME);
            $typ  = strtolower(trim((string) $sp->DATA_TYPE));
            $len  = (int) $sp->LEN;
            $passt = preg_match('/^schuler$|^schueler$|^lehrer$|mitarb|ehem|austr|abg|entl|abmeld|verlass|aktiv|status/i', $name);
            $kurz  = ! in_array($typ, ['text', 'ntext', 'image', 'xml', 'varbinary', 'binary'], true) && $len !== -1 && $len <= 255;
            if ($passt && $kurz && preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                $auswahl[$name] = in_array($typ, ['datetime', 'datetime2', 'date', 'smalldatetime'], true)
                    ? "CONVERT(varchar(10), a.[{$name}], 120) AS [{$name}]"
                    : "CAST(a.[{$name}] AS nvarchar(255)) AS [{$name}]";
            }
        }

        $ergebnis = [];
        $platz    = implode(',', array_fill(0, count($ids), '?'));
        if ($auswahl !== []) {
            $zeilen = $this->db()->select(
                'SELECT a.AdrNr, '.implode(', ', $auswahl)." FROM Adresse a WHERE a.AdrNr IN ({$platz})",
                $ids,
            );
            foreach ($zeilen as $z) {
                $w = $this->normalisiere($z);
                $id = (string) (int) $w['AdrNr'];
                unset($w['AdrNr']);
                $ergebnis[$id]['felder'] = array_map(fn ($v) => (string) $v, $w);
            }
        }

        if ($rolle === 'lehrer') {
            return $this->letzterLehrerEintrag($ergebnis, $ids, $platz);
        }

        // Letzter Eintrag je Person in WD_Schl (höchste Zeitraum-ID).
        $letzte = $this->db()->select(
            "SELECT s.AdrNr, s.ID, s.Klasse, CONVERT(varchar(10), s.AustrittKl, 120) AS AustrittKl, z.Bezeichnung
               FROM WD_Schl s
               JOIN WD_Zeitraum z ON z.ID = s.ID
              WHERE s.AdrNr IN ({$platz})
                AND s.ID = (SELECT MAX(s2.ID) FROM WD_Schl s2 WHERE s2.AdrNr = s.AdrNr)",
            $ids,
        );
        foreach ($letzte as $z) {
            $w  = $this->normalisiere($z);
            $id = (string) (int) $w['AdrNr'];
            $ergebnis[$id]['letzter'] = [
                'zeitraum'  => (string) ($w['Bezeichnung'] ?? '') ?: 'Zeitraum '.$w['ID'],
                'klasse'    => (string) ($w['Klasse'] ?? ''),
                'klassenende' => $this->datum($w['AustrittKl'] ?? ''),
            ];
        }

        foreach ($ids as $id) {
            $ergebnis[(string) $id] = ($ergebnis[(string) $id] ?? []) + ['felder' => [], 'letzter' => null];
        }

        return $ergebnis;
    }

    /**
     * Letzter Zeitraum, in dem eine Lehrkraft einer Klasse zugeordnet war oder einen
     * Lehrauftrag hatte – samt der Klassen dieses Zeitraums.
     *
     * @param  array<string,array<string,mixed>>  $ergebnis
     * @param  array<int,int>  $ids
     * @return array<string,array{felder: array<string,string>, letzter: ?array<string,string>}>
     */
    private function letzterLehrerEintrag(array $ergebnis, array $ids, string $platz): array
    {
        $alle = "(SELECT AdrNr, ID, Klasse FROM WD_Lehrer UNION ALL SELECT AdrNr, ID, Klasse FROM WD_LehrFach)";
        $zeilen = $this->db()->select(
            "SELECT x.AdrNr, x.ID, x.Klasse, z.Bezeichnung
               FROM {$alle} x
               JOIN WD_Zeitraum z ON z.ID = x.ID
              WHERE x.AdrNr IN ({$platz})
                AND x.ID = (SELECT MAX(y.ID) FROM {$alle} y WHERE y.AdrNr = x.AdrNr)",
            $ids,
        );

        $klassen = [];
        foreach ($zeilen as $z) {
            $w  = $this->normalisiere($z);
            $id = (string) (int) $w['AdrNr'];
            $ergebnis[$id]['letzter'] = [
                'zeitraum'    => (string) ($w['Bezeichnung'] ?? '') ?: 'Zeitraum '.$w['ID'],
                'klasse'      => '',
                'klassenende' => '',
            ];
            if (($w['Klasse'] ?? '') !== '') {
                $klassen[$id][(string) $w['Klasse']] = true;
            }
        }
        foreach ($klassen as $id => $liste) {
            $namen = array_keys($liste);
            natcasesort($namen);
            $ergebnis[$id]['letzter']['klasse'] = 'Klassen '.implode(', ', $namen);
        }

        foreach ($ids as $id) {
            $ergebnis[(string) $id] = ($ergebnis[(string) $id] ?? []) + ['felder' => [], 'letzter' => null];
        }

        return $ergebnis;
    }

    /**
     * Personen in Linear mit ähnlichem Namen (für „ohne Linear-ID" in der Vorschau):
     * gleicher Nachname, oder Vorname + Anfang des Nachnamens. Höchstens 8 Treffer.
     *
     * @return array<int,array{adrnr:string, vorname:string, nachname:string}>
     */
    public function namenSuchen(string $vorname, string $nachname): array
    {
        $esc = fn (string $s) => str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], trim($s));
        $nach = $esc($nachname);
        if ($nach === '') {
            return [];
        }
        $zeilen = $this->db()->select(
            'SELECT TOP 8 a.AdrNr, a.Vorname, a.Nachname
               FROM Adresse a
              WHERE a.Nachname LIKE ?
                 OR (a.Vorname LIKE ? AND a.Nachname LIKE ?)
              ORDER BY a.Nachname, a.Vorname',
            ['%'.$nach.'%', '%'.$esc($vorname).'%', mb_substr($nach, 0, 3).'%'],
        );

        return array_map(function (object $z): array {
            $w = $this->normalisiere($z);

            return ['adrnr' => (string) (int) $w['AdrNr'], 'vorname' => (string) ($w['Vorname'] ?? ''), 'nachname' => (string) ($w['Nachname'] ?? '')];
        }, $zeilen);
    }

    /** Gibt es diese Adresse in Linear? */
    public function adresseVorhanden(int $adrNr): bool
    {
        return (int) ($this->db()->select('SELECT COUNT(*) AS n FROM Adresse WHERE AdrNr = ?', [$adrNr])[0]->n ?? 0) > 0;
    }

    /**
     * Reguläres Klassenende = häufigstes AustrittKl; nur frühere Daten bleiben als Austritt.
     *
     * @param  array<int,array<string,string>>  $zeilen
     * @return array{0: array<int,array<string,string>>, 1: ?string}  [Zeilen, reguläres Ende]
     */
    public static function nurEchteAustritte(array $zeilen): array
    {
        $haeufigkeit = array_count_values(array_filter(array_column($zeilen, 'austritt'), fn ($d) => $d !== ''));
        arsort($haeufigkeit);
        $regulaer = array_key_first($haeufigkeit);

        foreach ($zeilen as &$r) {
            if ($r['austritt'] !== '' && $regulaer !== null && $r['austritt'] >= $regulaer) {
                $r['austritt'] = '';
            }
        }
        unset($r);

        return [$zeilen, $regulaer === null ? null : (string) $regulaer];
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
                "SELECT COUNT(*) AS n FROM Adresse a
                  WHERE a.Lehrer = 'J'
                     OR a.AdrNr IN (SELECT l.AdrNr FROM WD_Lehrer l WHERE l.ID = ?)
                     OR a.AdrNr IN (SELECT lf.AdrNr FROM WD_LehrFach lf WHERE lf.ID = ?)",
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
