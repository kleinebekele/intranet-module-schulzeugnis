<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Models\Stufe;

/**
 * Importiert die Klassen eines Ziel-Schuljahres aus einer CSV.
 *
 * Klassen sind je Schuljahr (`zeugnis_klassen`). Wiedererkennung/Idempotenz über den
 * Klassennamen je Schuljahr (additiv, nie löschen). Optionale Zuordnungen werden über
 * Namen bzw. IDs aufgelöst, aber NICHT automatisch angelegt:
 *   - Stufe          → vorhandene zeugnis_stufen per Name
 *   - Standardformat → vorhandenes zeugnis_formate per Name
 *   - Klassenlehrer  → Lehrer im selben Schuljahr per externe ID (quell_id)
 *
 * Regeln: Eine leere Zelle lässt ein bestehendes Feld unangetastet. Ein angegebener,
 * aber unbekannter Wert (Stufe/Format/Lehrer-ID) erzeugt eine Warnung und lässt das
 * Feld ebenfalls unangetastet – so wird nie versehentlich eine Zuordnung geleert.
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   Klasse          (Pflicht)  Name, z. B. "5a"
 *   Stufe           (optional) Name einer vorhandenen Schulstufe
 *   Standardformat  (optional) Name eines vorhandenen Zeugnisformats
 *   KlassenlehrerID (optional) externe ID (Linear) der Lehrkraft
 */
class KlasseImporter
{
    private const KLASSE_ALIASE  = ['klasse', 'klassenname', 'name'];
    private const STUFE_ALIASE   = ['stufe', 'schulstufe'];
    private const FORMAT_ALIASE  = ['standardformat', 'format', 'zeugnisformat'];
    private const LEHRER_ALIASE  = ['klassenlehrerid', 'klassenlehrerexterneid', 'klassenlehrer'];

    /**
     * @param  array<int,string>                $kopf
     * @param  array<int,array<string,string>>  $zeilen
     * @param  array<string,mixed>              $kontext  erwartet ['schuljahr_id' => int]
     * @return array<string,mixed>
     */
    public function analysiere(array $kopf, array $zeilen, array $kontext = []): array
    {
        $schuljahr = Schuljahr::find((int) ($kontext['schuljahr_id'] ?? 0));
        if (! $schuljahr) {
            throw new ImportFehler('Kein gültiges Ziel-Schuljahr gewählt.');
        }

        $klasseKey = $this->spalte($kopf, self::KLASSE_ALIASE);
        if ($klasseKey === null) {
            throw new ImportFehler('Die Pflichtspalte „Klasse" fehlt in der Kopfzeile.');
        }
        $stufeKey  = $this->spalte($kopf, self::STUFE_ALIASE);
        $formatKey = $this->spalte($kopf, self::FORMAT_ALIASE);
        $lehrerKey = $this->spalte($kopf, self::LEHRER_ALIASE);

        // Nachschlage-Register aufbauen.
        $bestehende = $schuljahr->klassen()->get();
        $nachName   = [];
        foreach ($bestehende as $k) {
            $nachName[$this->key($k->name)] = $k;
        }

        $stufen = [];
        foreach (Stufe::all() as $s) {
            $stufen[$this->key($s->name)] = $s;
        }
        $formate = [];
        foreach (Format::all() as $f) {
            $formate[$this->key($f->name)] = $f;
        }
        $lehrer = [];
        foreach ($schuljahr->lehrer()->whereNotNull('quell_id')->get() as $l) {
            $lehrer[trim((string) $l->quell_id)] = $l;
        }

        $ergebnis      = [];
        $gesehen       = [];
        $getroffeneIds = [];
        $probleme      = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr = $index + 2;
            $name     = trim($zeile[$klasseKey] ?? '');

            if ($name === '') {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', ['—', '—', '—', '—'], 'Kein Klassenname angegeben – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            if (isset($gesehen[$this->key($name)])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', [$name, '—', '—', '—'],
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$this->key($name)] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$this->key($name)] = $zeilenNr;

            // Optionale Zuordnungen auflösen. Rückgabe je: [wert-oder-null, zelle, warnungOderLeer, angegeben]
            [$stufeId, $stufeZelle, $stufeWarn]     = $this->aufloesen($stufeKey, $zeile, $stufen, 'Stufe', fn ($s) => $s->id, fn ($s) => $s->name);
            [$formatId, $formatZelle, $formatWarn]  = $this->aufloesen($formatKey, $zeile, $formate, 'Format', fn ($f) => $f->id, fn ($f) => $f->name);
            [$lehrerId, $lehrerZelle, $lehrerWarn]  = $this->aufloesen($lehrerKey, $zeile, $lehrer, 'Klassenlehrer-ID', fn ($l) => $l->id, fn ($l) => $l->fullName(), true);

            $warnungen = array_filter([$stufeWarn, $formatWarn, $lehrerWarn]);
            if ($warnungen !== []) {
                $probleme[] = "{$name}: " . implode('; ', $warnungen);
            }

            $zellen = [$name, $stufeZelle, $formatZelle, $lehrerZelle];

            $vorhanden = $nachName[$this->key($name)] ?? null;

            if ($vorhanden) {
                $getroffeneIds[$vorhanden->id] = true;
                $aenderungen = [];

                // Nur ändern, wenn ein Wert angegeben UND auflösbar war (Id !== null).
                if ($stufeKey !== null && $stufeId !== null && (int) $vorhanden->stufe_id !== $stufeId) {
                    $aenderungen['stufe_id'] = [$vorhanden->stufe_id, $stufeId];
                }
                if ($formatKey !== null && $formatId !== null && (int) $vorhanden->standard_format_id !== $formatId) {
                    $aenderungen['standard_format_id'] = [$vorhanden->standard_format_id, $formatId];
                }
                if ($lehrerKey !== null && $lehrerId !== null && (int) $vorhanden->klassenlehrer_id !== $lehrerId) {
                    $aenderungen['klassenlehrer_id'] = [$vorhanden->klassenlehrer_id, $lehrerId];
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
                    'schuljahr_id'       => $schuljahr->id,
                    'name'               => $name,
                    'stufe_id'           => $stufeId,
                    'standard_format_id' => $formatId,
                    'klassenlehrer_id'   => $lehrerId,
                ];
                $hinweis = implode('; ', array_filter(['Wird neu angelegt.', ...$warnungen]));
                $ergebnis[] = $this->row($zeilenNr, 'neu', $zellen, $hinweis, null, $apply);
                $zaehl['neu']++;
            }
        }

        $fehlen = [];
        foreach ($bestehende as $k) {
            if (! isset($getroffeneIds[$k->id])) {
                $fehlen[] = $k->name;
            }
        }

        return [
            'spalten_titel' => ['Klasse', 'Stufe', 'Format', 'Klassenlehrer'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [
                ['label' => "Klassen in {$schuljahr->name} stehen nicht in der Datei und bleiben unverändert", 'items' => $fehlen, 'ton' => 'grau', 'nur_ergebnis' => true],
                ['label' => 'Zeilen mit unbekannter Zuordnung (Feld bleibt leer, bitte prüfen)', 'items' => $probleme, 'ton' => 'amber'],
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
                    Klasse::create($r['apply']);
                } elseif ($r['status'] === 'aktualisiert' && $r['ziel_id'] !== null) {
                    Klasse::whereKey($r['ziel_id'])->update($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        Protokoll::log('importiert', [
            'schuljahr_id' => (int) ($kontext['schuljahr_id'] ?? 0) ?: null,
            'beschreibung' => "Klassen-Import: {$z['neu']} neu, {$z['aktualisiert']} aktualisiert, "
                . "{$z['unveraendert']} unverändert, {$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ]);

        return $analyse;
    }

    /**
     * Optionale Zuordnung auflösen.
     *
     * @param  array<string,string>       $zeile
     * @param  array<string,object>       $register  Schlüssel → Model
     * @param  callable(object):int       $idFn
     * @param  callable(object):string    $labelFn
     * @return array{0:?int,1:string,2:string}  [id-oder-null, anzeige-zelle, warnung-oder-leer]
     */
    private function aufloesen(?string $key, array $zeile, array $register, string $bezeichnung, callable $idFn, callable $labelFn, bool $exakt = false): array
    {
        if ($key === null) {
            return [null, '—', ''];
        }
        $wert = trim($zeile[$key] ?? '');
        if ($wert === '') {
            return [null, '—', ''];
        }

        $treffer = $register[$exakt ? $wert : $this->key($wert)] ?? null;
        if ($treffer) {
            return [(int) $idFn($treffer), $labelFn($treffer), ''];
        }

        return [null, "⚠ {$wert}?", "{$bezeichnung} „{$wert}“ nicht gefunden"];
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

    private function key(?string $s): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $s)));
    }

    /** @param array<string,array{0:mixed,1:mixed}> $aenderungen */
    private function diffText(array $aenderungen): string
    {
        $label = ['stufe_id' => 'Stufe', 'standard_format_id' => 'Format', 'klassenlehrer_id' => 'Klassenlehrer'];
        $teile = [];
        foreach ($aenderungen as $feld => [$alt, $neu]) {
            $teile[] = ($label[$feld] ?? $feld) . ' gesetzt';
        }

        return implode('; ', $teile);
    }
}
