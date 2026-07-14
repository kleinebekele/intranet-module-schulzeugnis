<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

/**
 * Liest eine CSV-Datei robust ein und liefert normalisierte Zeilen.
 *
 * Ausgelegt auf Exporte aus dem Schulverwaltungsprogramm (Linear/MSSQL), die als
 * CSV im storage abgelegt werden:
 *  - Trennzeichen automatisch (Semikolon bevorzugt, sonst Komma/Tab),
 *  - BOM wird entfernt, Windows-1252 wird nach UTF-8 gewandelt (deutsche Umlaute),
 *  - Spaltennamen werden vereinheitlicht (klein, ohne Umlaute/Sonderzeichen),
 *    sodass "Kürzel", "kuerzel" und "KUERZEL" denselben Schlüssel ergeben.
 */
class CsvLeser
{
    /**
     * @return array{kopf: array<int,string>, zeilen: array<int,array<string,string>>}
     */
    public static function lesen(string $pfad): array
    {
        $inhalt = @file_get_contents($pfad);
        if ($inhalt === false) {
            throw new ImportFehler('Die Datei konnte nicht gelesen werden.');
        }

        // Byte-Order-Mark (UTF-8) entfernen.
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt);

        // Encoding angleichen: was nicht sauber UTF-8 ist, kommt meist als
        // Windows-1252 (typische Excel-/MSSQL-Ausgabe) – dann umwandeln.
        if (! mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }

        // Zeilenenden vereinheitlichen und Leerzeilen entfernen.
        $inhalt = str_replace(["\r\n", "\r"], "\n", $inhalt);
        $roh = array_values(array_filter(
            explode("\n", $inhalt),
            static fn (string $z): bool => trim($z) !== ''
        ));

        if ($roh === []) {
            return ['kopf' => [], 'zeilen' => []];
        }

        $trenner = self::trennerErkennen($roh[0]);

        $kopf = array_map(
            static fn (string $s): string => self::normalisiere($s),
            str_getcsv((string) array_shift($roh), $trenner)
        );

        $zeilen = [];
        foreach ($roh as $zeile) {
            $werte = str_getcsv($zeile, $trenner);
            $assoc = [];
            foreach ($kopf as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $assoc[$name] = isset($werte[$i]) ? trim((string) $werte[$i]) : '';
            }
            $zeilen[] = $assoc;
        }

        return ['kopf' => $kopf, 'zeilen' => $zeilen];
    }

    /** Häufigstes Trennzeichen der Kopfzeile bestimmen (Default Semikolon). */
    private static function trennerErkennen(string $kopfzeile): string
    {
        $anzahl = [
            ';'  => substr_count($kopfzeile, ';'),
            ','  => substr_count($kopfzeile, ','),
            "\t" => substr_count($kopfzeile, "\t"),
        ];
        arsort($anzahl);
        $bester = array_key_first($anzahl);

        return $anzahl[$bester] > 0 ? $bester : ';';
    }

    /** Spaltenname vereinheitlichen: trimmen, klein, Umlaute vereinfachen, Rest weg. */
    public static function normalisiere(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }
}
