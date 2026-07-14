<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Importiert den (jahresübergreifenden) Fächerkatalog aus einer CSV.
 *
 * Grundhaltung wie im ganzen Modul: additiv, nie löschen. Ein Fach wird über sein
 * Kürzel (falls angegeben, sonst über den Namen) wiedererkannt – so ist ein
 * erneuter Import derselben Datei idempotent (keine Duplikate). Fächer, die in der
 * DB stehen, aber in der Datei fehlen, bleiben unangetastet (nur Hinweis).
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   Name         (Pflicht)  Fachname, z. B. "Mathematik"
 *   Kuerzel      (optional) z. B. "Ma" – zugleich Wiedererkennungs-Schlüssel
 *   Reihenfolge  (optional) Zahl für die Sortierung (neu: Default 0)
 *   Aktiv        (optional) ja/nein (neu: Default ja)
 */
class FaecherImporter
{
    /**
     * Trockenlauf: bewertet jede Zeile, ohne zu schreiben.
     *
     * @param  array<int,string>                $kopf     normalisierte Spaltennamen
     * @param  array<int,array<string,string>>  $zeilen   Datenzeilen
     * @param  array<string,mixed>              $kontext  (ungenutzt – Fächer sind jahresübergreifend)
     * @return array{spalten_titel: array<int,string>, zeilen: array<int,array<string,mixed>>,
     *               zaehl: array<string,int>, infos: array<int,array<string,mixed>>}
     */
    public function analysiere(array $kopf, array $zeilen, array $kontext = []): array
    {
        if (! in_array('name', $kopf, true)) {
            throw new ImportFehler('Die Pflichtspalte „Name" fehlt in der Kopfzeile.');
        }

        $hatKuerzel     = in_array('kuerzel', $kopf, true);
        $hatReihenfolge = in_array('reihenfolge', $kopf, true);
        $hatAktiv       = in_array('aktiv', $kopf, true);

        // Bestehende Fächer für den Abgleich indizieren.
        $bestehende  = Fach::all();
        $nachKuerzel = [];
        $nachName    = [];
        foreach ($bestehende as $fach) {
            if (filled($fach->kuerzel)) {
                $nachKuerzel[$this->schluessel($fach->kuerzel)] = $fach;
            }
            $nachName[$this->schluessel($fach->name)] = $fach;
        }

        $ergebnis      = [];
        $gesehen       = [];  // Doppelte innerhalb der Datei erkennen
        $getroffeneIds = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr   = $index + 2; // +1 Kopfzeile, +1 auf 1-basiert
            $name       = trim($zeile['name'] ?? '');
            $kuerzel    = $hatKuerzel ? trim($zeile['kuerzel'] ?? '') : '';
            $kuerzelNeu = $kuerzel === '' ? null : $kuerzel;

            if ($name === '') {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', ['—', $kuerzel ?: '—'], 'Kein Fachname angegeben – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            // Wiedererkennung: Kürzel bevorzugt, sonst Name.
            $matchKey = ($kuerzel !== '') ? 'k:' . $this->schluessel($kuerzel) : 'n:' . $this->schluessel($name);
            if (isset($gesehen[$matchKey])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', [$name, $kuerzel ?: '—'],
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$matchKey] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$matchKey] = $zeilenNr;

            $vorhanden = null;
            if ($kuerzel !== '' && isset($nachKuerzel[$this->schluessel($kuerzel)])) {
                $vorhanden = $nachKuerzel[$this->schluessel($kuerzel)];
            } elseif (isset($nachName[$this->schluessel($name)])) {
                $vorhanden = $nachName[$this->schluessel($name)];
            }

            $reihenfolge = $hatReihenfolge ? $this->intOderNull($zeile['reihenfolge'] ?? '') : null;
            $aktiv       = $hatAktiv ? $this->boolOderNull($zeile['aktiv'] ?? '') : null;

            if ($vorhanden) {
                $getroffeneIds[$vorhanden->id] = true;
                $aenderungen = [];

                if ($vorhanden->name !== $name) {
                    $aenderungen['name'] = [$vorhanden->name, $name];
                }
                // Leeres Kürzel im Import = „nicht angegeben": ein bereits gesetztes
                // Kürzel wird NICHT geleert (nur ein tatsächlich angegebenes ändert es).
                if ($hatKuerzel && $kuerzelNeu !== null && (string) $vorhanden->kuerzel !== (string) $kuerzelNeu) {
                    $aenderungen['kuerzel'] = [$vorhanden->kuerzel, $kuerzelNeu];
                }
                if ($hatReihenfolge && $reihenfolge !== null && (int) $vorhanden->reihenfolge !== $reihenfolge) {
                    $aenderungen['reihenfolge'] = [$vorhanden->reihenfolge, $reihenfolge];
                }
                if ($hatAktiv && $aktiv !== null && (bool) $vorhanden->aktiv !== $aktiv) {
                    $aenderungen['aktiv'] = [$vorhanden->aktiv, $aktiv];
                }

                if ($aenderungen === []) {
                    $ergebnis[] = $this->row($zeilenNr, 'unveraendert', [$name, $kuerzel ?: '—'],
                        'Bereits vorhanden, keine Änderung.', $vorhanden->id);
                    $zaehl['unveraendert']++;
                } else {
                    $apply = [];
                    foreach ($aenderungen as $feld => [, $neu]) {
                        $apply[$feld] = $neu;
                    }
                    $ergebnis[] = $this->row($zeilenNr, 'aktualisiert', [$name, $kuerzel ?: '—'],
                        $this->diffText($aenderungen), $vorhanden->id, $apply);
                    $zaehl['aktualisiert']++;
                }
            } else {
                $apply = [
                    'name'        => $name,
                    'kuerzel'     => $kuerzelNeu,
                    'reihenfolge' => $reihenfolge ?? 0,
                    'aktiv'       => $aktiv ?? true,
                ];
                $ergebnis[] = $this->row($zeilenNr, 'neu', [$name, $kuerzel ?: '—'], 'Wird neu angelegt.', null, $apply);
                $zaehl['neu']++;
            }
        }

        // Bestehende Fächer, die in der Datei nicht vorkommen (nur Info, unangetastet).
        $fehlen = [];
        foreach ($bestehende as $fach) {
            if (! isset($getroffeneIds[$fach->id])) {
                $fehlen[] = $fach->name . (filled($fach->kuerzel) ? " ({$fach->kuerzel})" : '');
            }
        }

        return [
            'spalten_titel' => ['Name', 'Kürzel'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [
                ['label' => 'vorhandene Fächer stehen nicht in der Datei und bleiben unverändert', 'items' => $fehlen, 'ton' => 'grau', 'nur_ergebnis' => true],
            ],
        ];
    }

    /**
     * Echter Import: analysiert frisch (maßgeblicher DB-Stand) und schreibt in einer
     * Transaktion. Gibt dieselbe Analyse-Struktur zurück – jetzt als Tatsachen-Report.
     *
     * @param  array<int,string>                $kopf
     * @param  array<int,array<string,string>>  $zeilen
     * @param  array<string,mixed>              $kontext
     * @return array<string,mixed>
     */
    public function importiere(array $kopf, array $zeilen, array $kontext = []): array
    {
        $analyse = $this->analysiere($kopf, $zeilen, $kontext);

        DB::transaction(function () use ($analyse): void {
            foreach ($analyse['zeilen'] as $r) {
                if ($r['status'] === 'neu') {
                    Fach::create($r['apply']);
                } elseif ($r['status'] === 'aktualisiert' && $r['ziel_id'] !== null) {
                    Fach::whereKey($r['ziel_id'])->update($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        Protokoll::log('importiert', [
            'beschreibung' => "Fächer-Import: {$z['neu']} neu, {$z['aktualisiert']} aktualisiert, "
                . "{$z['unveraendert']} unverändert, {$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ]);

        return $analyse;
    }

    /**
     * @param  array<int,string>    $zellen  Anzeigewerte je Datenspalte
     * @param  array<string,mixed>  $apply
     * @return array<string,mixed>
     */
    private function row(int $zeile, string $status, array $zellen, string $hinweis, ?int $zielId = null, array $apply = []): array
    {
        return [
            'zeile'   => $zeile,
            'status'  => $status,
            'zellen'  => $zellen,
            'hinweis' => $hinweis,
            'ziel_id' => $zielId,
            'apply'   => $apply,
        ];
    }

    /** Match-Schlüssel: getrimmt, klein, Mehrfach-Leerzeichen zusammengezogen. */
    private function schluessel(?string $s): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $s)));
    }

    private function intOderNull(string $s): ?int
    {
        $s = trim($s);

        return ($s !== '' && is_numeric($s)) ? (int) $s : null;
    }

    private function boolOderNull(string $s): ?bool
    {
        $v = mb_strtolower(trim($s));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['ja', 'j', '1', 'wahr', 'true', 'x', 'aktiv', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($v, ['nein', 'n', '0', 'falsch', 'false', 'inaktiv', 'archiviert', 'no'], true)) {
            return false;
        }

        return null; // unklar → nicht setzen
    }

    /** @param array<string,array{0:mixed,1:mixed}> $aenderungen */
    private function diffText(array $aenderungen): string
    {
        $teile = [];
        foreach ($aenderungen as $feld => [$alt, $neu]) {
            $teile[] = $this->feldLabel($feld) . ': ' . $this->wert($feld, $alt) . ' → ' . $this->wert($feld, $neu);
        }

        return implode('; ', $teile);
    }

    private function feldLabel(string $feld): string
    {
        return [
            'name'        => 'Name',
            'kuerzel'     => 'Kürzel',
            'reihenfolge' => 'Reihenfolge',
            'aktiv'       => 'Aktiv',
        ][$feld] ?? $feld;
    }

    private function wert(string $feld, mixed $wert): string
    {
        if ($feld === 'aktiv') {
            return $wert ? 'ja' : 'nein';
        }

        return ($wert === null || $wert === '') ? '—' : (string) $wert;
    }
}
