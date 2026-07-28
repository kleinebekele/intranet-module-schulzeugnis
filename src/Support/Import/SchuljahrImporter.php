<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Importiert Schuljahre (z. B. aus dem Quellsystem Linear).
 *
 * Schuljahre sind der Anker des Moduls und wurden bisher ausschließlich manuell
 * gepflegt. Dieser Importer macht sie automatisch befüllbar, ohne die manuelle
 * Pflege zu entwerten: Wiedererkennung primär über die stabile externe ID
 * (`quell_id`, in Linear `WD_Zeitraum.ID`), sonst über den Namen – so wird ein
 * bereits von Hand angelegtes Jahr beim ersten Lauf mit seiner Quell-ID
 * verknüpft statt dupliziert.
 *
 * Regeln (bewusst konservativer als bei den Geschwister-Importern):
 *  - `quell_id` wird nur NACHGETRAGEN (leer → Wert), nie geändert. Trifft der
 *    Name ein Jahr mit anderer quell_id, wird gewarnt und nichts geschrieben.
 *  - Der Name wird NIE geändert – er ist der menschliche Anker im ganzen Modul
 *    und zugleich Match-Schlüssel. Eine abweichende Bezeichnung in der Quelle
 *    wird nur als Info gemeldet.
 *  - `start_date`/`end_date` werden nur nachgetragen, wenn lokal leer –
 *    manuell gesetzte Termine sind fachliche Entscheidungen und bleiben.
 *  - `is_active` wird NIE berührt: Neue Jahre entstehen INAKTIV; aktiv setzt
 *    ein Mensch auf der Schuljahre-Seite, nie ein Import.
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   QuellID  (optional) stabile ID aus dem Quellsystem (Linear: WD_Zeitraum.ID)
 *   Name     (Pflicht)  z. B. "2026/2027"
 *   Von      (optional) Beginn, TT.MM.JJJJ oder JJJJ-MM-TT
 *   Bis      (optional) Ende, TT.MM.JJJJ oder JJJJ-MM-TT
 */
class SchuljahrImporter
{
    private const ID_ALIASE   = ['quellid', 'externeid', 'id'];
    private const NAME_ALIASE = ['name', 'bezeichnung', 'schuljahr'];
    private const VON_ALIASE  = ['von', 'startdatum', 'start'];
    private const BIS_ALIASE  = ['bis', 'enddatum', 'ende'];

    /**
     * @param  array<int,string>                $kopf     normalisierte Spaltennamen
     * @param  array<int,array<string,string>>  $zeilen   Datenzeilen
     * @param  array<string,mixed>              $kontext  optional ['akteur_name' => string]
     * @return array{spalten_titel: array<int,string>, zeilen: array<int,array<string,mixed>>,
     *               zaehl: array<string,int>, infos: array<int,array<string,mixed>>}
     */
    public function analysiere(array $kopf, array $zeilen, array $kontext = []): array
    {
        $nameKey = $this->spalte($kopf, self::NAME_ALIASE);
        if ($nameKey === null) {
            throw new ImportFehler('Die Pflichtspalte „Name" fehlt in der Kopfzeile.');
        }
        $idKey  = $this->spalte($kopf, self::ID_ALIASE);
        $vonKey = $this->spalte($kopf, self::VON_ALIASE);
        $bisKey = $this->spalte($kopf, self::BIS_ALIASE);

        // Bestehende Schuljahre für den Abgleich indizieren.
        $bestehende  = Schuljahr::all();
        $nachQuellId = [];
        $nachName    = [];
        foreach ($bestehende as $sj) {
            if (filled($sj->quell_id)) {
                $nachQuellId[trim((string) $sj->quell_id)] = $sj;
            }
            $nachName[$this->key($sj->name)] = $sj;
        }

        $ergebnis      = [];
        $gesehen       = [];
        $getroffeneIds = [];
        $abweichende   = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr = $index + 2;
            $name     = trim($zeile[$nameKey] ?? '');
            $ext      = $idKey ? trim($zeile[$idKey] ?? '') : '';

            if ($name === '') {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', [$ext ?: '—', '—', '—', '—'],
                    'Kein Name angegeben – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            // Termine parsen (unlesbar → Warnhinweis, Feld bleibt leer).
            [$von, $vonZelle, $vonWarn] = $this->datumAufloesen($vonKey, $zeile);
            [$bis, $bisZelle, $bisWarn] = $this->datumAufloesen($bisKey, $zeile);
            $warnungen = array_filter([$vonWarn, $bisWarn]);

            $zellen = [$ext ?: '—', $name, $vonZelle, $bisZelle];

            // Duplikat innerhalb der Datei.
            $dupKey = $ext !== '' ? 'e:' . $ext : 'n:' . $this->key($name);
            if (isset($gesehen[$dupKey])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', $zellen,
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$dupKey] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$dupKey] = $zeilenNr;

            // Wiedererkennung: quell_id bevorzugt, sonst Name.
            $vorhanden = null;
            if ($ext !== '' && isset($nachQuellId[$ext])) {
                $vorhanden = $nachQuellId[$ext];
            } elseif (isset($nachName[$this->key($name)])) {
                $vorhanden = $nachName[$this->key($name)];

                // Über den Namen getroffen, aber bereits mit einer ANDEREN quell_id
                // verknüpft: Das wäre eine zweite Quelle für dasselbe Jahr – nicht
                // anfassen, nur warnen.
                if ($ext !== '' && filled($vorhanden->quell_id) && trim((string) $vorhanden->quell_id) !== $ext) {
                    $getroffeneIds[$vorhanden->id] = true;
                    $ergebnis[] = $this->row($zeilenNr, 'warnung', $zellen,
                        "Name vorhanden, aber mit anderer QuellID ({$vorhanden->quell_id}) verknüpft – übersprungen.",
                        $vorhanden->id);
                    $zaehl['warnung']++;
                    continue;
                }
            }

            if ($vorhanden) {
                $getroffeneIds[$vorhanden->id] = true;
                $aenderungen = [];

                // quell_id nur nachtragen, nie ändern (Abweichung fängt der Block oben).
                if ($ext !== '' && blank($vorhanden->quell_id)) {
                    $aenderungen['quell_id'] = [$vorhanden->quell_id, $ext];
                }
                // Termine nur nachtragen, wenn lokal leer.
                if ($von !== null && $vorhanden->start_date === null) {
                    $aenderungen['start_date'] = [null, $von];
                }
                if ($bis !== null && $vorhanden->end_date === null) {
                    $aenderungen['end_date'] = [null, $bis];
                }
                // Abweichender Quell-Name: nur melden, nie umbenennen.
                if ($this->key($name) !== $this->key($vorhanden->name)) {
                    $abweichende[] = "{$vorhanden->name}: Quelle nennt es „{$name}“";
                }

                $hinweis = implode('; ', array_filter([
                    $aenderungen === [] ? 'Bereits vorhanden, keine Änderung.' : $this->diffText($aenderungen),
                    ...$warnungen,
                ]));

                if ($aenderungen === []) {
                    $ergebnis[] = $this->row($zeilenNr, 'unveraendert', $zellen, $hinweis, $vorhanden->id);
                    $zaehl['unveraendert']++;
                } else {
                    $apply = [];
                    foreach ($aenderungen as $feld => [, $neu]) {
                        $apply[$feld] = $neu;
                    }
                    $ergebnis[] = $this->row($zeilenNr, 'aktualisiert', $zellen, $hinweis, $vorhanden->id, $apply);
                    $zaehl['aktualisiert']++;
                }
            } else {
                $apply = [
                    'name'       => $name,
                    'quell_id'   => $ext ?: null,
                    'start_date' => $von,
                    'end_date'   => $bis,
                    'is_active'  => false,
                ];
                $hinweis = implode('; ', array_filter(['Wird neu angelegt (inaktiv).', ...$warnungen]));
                $ergebnis[] = $this->row($zeilenNr, 'neu', $zellen, $hinweis, null, $apply);
                $zaehl['neu']++;
            }
        }

        // Bestehende Schuljahre, die in der Datei nicht vorkommen (nur Info, unangetastet).
        $fehlen = [];
        foreach ($bestehende as $sj) {
            if (! isset($getroffeneIds[$sj->id])) {
                $fehlen[] = $sj->name;
            }
        }

        return [
            'spalten_titel' => ['QuellID', 'Name', 'Von', 'Bis'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [
                ['label' => 'vorhandene Schuljahre stehen nicht in der Datei und bleiben unverändert', 'items' => $fehlen, 'ton' => 'grau', 'nur_ergebnis' => true],
                ['label' => 'abweichende Bezeichnung in der Quelle (der Name bleibt, wie lokal gepflegt)', 'items' => $abweichende, 'ton' => 'grau'],
            ],
        ];
    }

    /**
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
                    Schuljahr::create($r['apply']);
                } elseif ($r['status'] === 'aktualisiert' && $r['ziel_id'] !== null) {
                    Schuljahr::whereKey($r['ziel_id'])->update($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        $attrs = [
            'beschreibung' => "Schuljahr-Import: {$z['neu']} neu, {$z['aktualisiert']} aktualisiert, "
                . "{$z['unveraendert']} unverändert, {$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ];
        // Läufe ohne eingeloggten Benutzer (Task/Cron) geben ihren Akteur selbst an.
        if (filled($kontext['akteur_name'] ?? null)) {
            $attrs['akteur_name'] = (string) $kontext['akteur_name'];
        }
        Protokoll::log('importiert', $attrs);

        return $analyse;
    }

    /**
     * Datum auflösen. @return array{0:?string,1:string,2:string} [Y-m-d|null, zelle, warnung]
     *
     * @param  array<string,string>  $zeile
     */
    private function datumAufloesen(?string $key, array $zeile): array
    {
        if ($key === null) {
            return [null, '—', ''];
        }
        $wert = trim($zeile[$key] ?? '');
        if ($wert === '') {
            return [null, '—', ''];
        }

        foreach (['d.m.Y', 'j.n.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTime::createFromFormat('!' . $format, $wert);
            $fehler = \DateTime::getLastErrors();
            if ($dt !== false && (! $fehler || ($fehler['warning_count'] === 0 && $fehler['error_count'] === 0))) {
                return [$dt->format('Y-m-d'), $dt->format('d.m.Y'), ''];
            }
        }

        return [null, "⚠ {$wert}?", "Datum „{$wert}“ nicht lesbar (TT.MM.JJJJ)"];
    }

    private function spalte(array $kopf, array $aliase): ?string
    {
        foreach ($aliase as $a) {
            if (in_array($a, $kopf, true)) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @param  array<int,string>    $zellen
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
    private function key(?string $s): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $s)));
    }

    /** @param array<string,array{0:mixed,1:mixed}> $aenderungen */
    private function diffText(array $aenderungen): string
    {
        $label = ['quell_id' => 'QuellID', 'start_date' => 'Von', 'end_date' => 'Bis'];
        $teile = [];
        foreach ($aenderungen as $feld => [$alt, $neu]) {
            $teile[] = ($label[$feld] ?? $feld) . ': ' . (($alt === null || $alt === '') ? '—' : $alt) . ' → ' . $neu;
        }

        return implode('; ', $teile);
    }
}
