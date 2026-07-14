<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schueler;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Importiert die Schüler eines Ziel-Schuljahres aus einer CSV.
 *
 * Schüler sind je Schuljahr (`zeugnis_schuljahr_schueler`). Wiedererkennung/Idempotenz
 * primär über die stabile externe ID (quell_id = Linear), sonst über Name +
 * Geburtsdatum. Additiv, nie löschen. Die Klasse wird per Name im Ziel-Schuljahr
 * aufgelöst; ist sie nicht vorhanden, wird der Schüler trotzdem angelegt (ohne
 * Klasse) und die Zeile gewarnt – Klassen also vorher importieren.
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   SchuelerID    (optional) stabile ID aus Linear = quell_id (Match-Schlüssel)
 *   Klasse        (optional) Name der Klasse im Ziel-Schuljahr
 *   Vorname       (Pflicht)
 *   Nachname      (Pflicht)
 *   Geburtsdatum  (optional) TT.MM.JJJJ oder JJJJ-MM-TT
 *   Geburtsort    (optional)
 *   Geschlecht    (optional) m / w / d (auch „männlich/weiblich/divers")
 */
class SchuelerImporter
{
    private const ID_ALIASE     = ['schuelerid', 'quellid', 'externeid', 'id'];
    private const KLASSE_ALIASE = ['klasse', 'klassenname'];
    private const GEBDAT_ALIASE = ['geburtsdatum', 'geburtstag', 'gebdatum', 'gebdat'];
    private const GEBORT_ALIASE = ['geburtsort', 'gebort'];
    private const GESCHL_ALIASE = ['geschlecht', 'sex'];

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
        if (! in_array('vorname', $kopf, true) || ! in_array('nachname', $kopf, true)) {
            throw new ImportFehler('Die Pflichtspalten „Vorname" und „Nachname" müssen vorhanden sein.');
        }

        $idKey     = $this->spalte($kopf, self::ID_ALIASE);
        $klasseKey = $this->spalte($kopf, self::KLASSE_ALIASE);
        $gebDatKey = $this->spalte($kopf, self::GEBDAT_ALIASE);
        $gebOrtKey = $this->spalte($kopf, self::GEBORT_ALIASE);
        $geschlKey = $this->spalte($kopf, self::GESCHL_ALIASE);

        // Register: bestehende Schüler (per quell_id + per Name|Geburtsdatum), Klassen per Name.
        $bestehende  = $schuljahr->schueler()->get();
        $nachQuellId = [];
        $nachName    = [];
        foreach ($bestehende as $s) {
            if (filled($s->quell_id)) {
                $nachQuellId[trim((string) $s->quell_id)] = $s;
            }
            $nachName[$this->personKey($s->vorname, $s->nachname, $this->datumStr($s->geburtsdatum))] = $s;
        }
        $klassen = [];
        foreach ($schuljahr->klassen()->get() as $k) {
            $klassen[$this->key($k->name)] = $k;
        }

        $ergebnis      = [];
        $gesehen       = [];
        $getroffeneIds = [];
        $probleme      = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr = $index + 2;
            $vorname  = trim($zeile['vorname'] ?? '');
            $nachname = trim($zeile['nachname'] ?? '');
            $ext      = $idKey ? trim($zeile[$idKey] ?? '') : '';

            if ($vorname === '' || $nachname === '') {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', [$ext ?: '—', '—', '—', '—', '—'],
                    'Vor- und Nachname sind Pflicht – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            $warnungen = [];

            // Klasse auflösen (per Name; unbekannt → Warnung, bleibt leer).
            $klasseId = null;
            $klasseZelle = '—';
            if ($klasseKey !== null && ($kn = trim($zeile[$klasseKey] ?? '')) !== '') {
                if (isset($klassen[$this->key($kn)])) {
                    $klasseId = (int) $klassen[$this->key($kn)]->id;
                    $klasseZelle = $klassen[$this->key($kn)]->name;
                } else {
                    $klasseZelle = "⚠ {$kn}?";
                    $warnungen[] = "Klasse „{$kn}“ nicht gefunden";
                }
            }

            // Geburtsdatum parsen.
            [$gebDat, $gebDatZelle, $gebDatWarn] = $this->datumAufloesen($gebDatKey, $zeile);
            if ($gebDatWarn !== '') {
                $warnungen[] = $gebDatWarn;
            }

            // Geschlecht normalisieren.
            [$geschlecht, $geschlZelle, $geschlWarn] = $this->geschlechtAufloesen($geschlKey, $zeile);
            if ($geschlWarn !== '') {
                $warnungen[] = $geschlWarn;
            }

            $gebOrt = ($gebOrtKey !== null) ? trim($zeile[$gebOrtKey] ?? '') : '';

            if ($warnungen !== []) {
                $probleme[] = trim("$vorname $nachname") . ': ' . implode('; ', $warnungen);
            }

            $zellen = [$ext ?: '—', trim("$vorname $nachname"), $klasseZelle, $gebDatZelle, $geschlZelle];

            // Duplikat in der Datei.
            $dupKey = $ext !== '' ? 'e:' . $ext : 'n:' . $this->personKey($vorname, $nachname, $gebDat);
            if (isset($gesehen[$dupKey])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', $zellen,
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$dupKey] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$dupKey] = $zeilenNr;

            // Wiedererkennung: quell_id bevorzugt, sonst Name + Geburtsdatum.
            $vorhanden = null;
            if ($ext !== '' && isset($nachQuellId[$ext])) {
                $vorhanden = $nachQuellId[$ext];
            } elseif (isset($nachName[$this->personKey($vorname, $nachname, $gebDat)])) {
                $vorhanden = $nachName[$this->personKey($vorname, $nachname, $gebDat)];
            }

            if ($vorhanden) {
                $getroffeneIds[$vorhanden->id] = true;
                $aenderungen = [];

                if ($vorhanden->vorname !== $vorname) {
                    $aenderungen['vorname'] = [$vorhanden->vorname, $vorname];
                }
                if ($vorhanden->nachname !== $nachname) {
                    $aenderungen['nachname'] = [$vorhanden->nachname, $nachname];
                }
                if ($ext !== '' && (string) $vorhanden->quell_id !== $ext) {
                    $aenderungen['quell_id'] = [$vorhanden->quell_id, $ext];
                }
                if ($klasseId !== null && (int) $vorhanden->klasse_id !== $klasseId) {
                    $aenderungen['klasse_id'] = [$vorhanden->klasse_id, $klasseId];
                }
                if ($gebDat !== null && $this->datumStr($vorhanden->geburtsdatum) !== $gebDat) {
                    $aenderungen['geburtsdatum'] = [$this->datumStr($vorhanden->geburtsdatum), $gebDat];
                }
                if ($gebOrtKey !== null && $gebOrt !== '' && (string) $vorhanden->geburtsort !== $gebOrt) {
                    $aenderungen['geburtsort'] = [$vorhanden->geburtsort, $gebOrt];
                }
                if ($geschlecht !== null && (string) $vorhanden->geschlecht !== $geschlecht) {
                    $aenderungen['geschlecht'] = [$vorhanden->geschlecht, $geschlecht];
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
                    'schuljahr_id' => $schuljahr->id,
                    'quell_id'     => $ext ?: null,
                    'klasse_id'    => $klasseId,
                    'vorname'      => $vorname,
                    'nachname'     => $nachname,
                    'geburtsdatum' => $gebDat,
                    'geburtsort'   => $gebOrt !== '' ? $gebOrt : null,
                    'geschlecht'   => $geschlecht,
                ];
                $hinweis = implode('; ', array_filter(['Wird neu angelegt.', ...$warnungen]));
                $ergebnis[] = $this->row($zeilenNr, 'neu', $zellen, $hinweis, null, $apply);
                $zaehl['neu']++;
            }
        }

        $fehlen = [];
        foreach ($bestehende as $s) {
            if (! isset($getroffeneIds[$s->id])) {
                $fehlen[] = $s->fullName();
            }
        }

        return [
            'spalten_titel' => ['SchülerID', 'Name', 'Klasse', 'Geburtsdatum', 'Geschl.'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [
                ['label' => "Schüler in {$schuljahr->name} stehen nicht in der Datei und bleiben unverändert", 'items' => $fehlen, 'ton' => 'grau', 'nur_ergebnis' => true],
                ['label' => 'Zeilen mit unbekannter Klasse / ungültigem Datum / Geschlecht (Feld bleibt leer, bitte prüfen)', 'items' => $probleme, 'ton' => 'amber'],
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
                    Schueler::create($r['apply']);
                } elseif ($r['status'] === 'aktualisiert' && $r['ziel_id'] !== null) {
                    Schueler::whereKey($r['ziel_id'])->update($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        Protokoll::log('importiert', [
            'schuljahr_id' => (int) ($kontext['schuljahr_id'] ?? 0) ?: null,
            'beschreibung' => "Schüler-Import: {$z['neu']} neu, {$z['aktualisiert']} aktualisiert, "
                . "{$z['unveraendert']} unverändert, {$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ]);

        return $analyse;
    }

    /**
     * Geburtsdatum auflösen. @return array{0:?string,1:string,2:string} [Y-m-d|null, zelle, warnung]
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

        return [null, "⚠ {$wert}?", "Geburtsdatum „{$wert}“ nicht lesbar (TT.MM.JJJJ)"];
    }

    /**
     * Geschlecht normalisieren. @return array{0:?string,1:string,2:string} [m|w|d|null, zelle, warnung]
     *
     * @param  array<string,string>  $zeile
     */
    private function geschlechtAufloesen(?string $key, array $zeile): array
    {
        if ($key === null) {
            return [null, '—', ''];
        }
        $v = mb_strtolower(trim($zeile[$key] ?? ''));
        if ($v === '') {
            return [null, '—', ''];
        }
        if (in_array($v, ['m', 'männlich', 'maennlich', 'male', 'junge'], true)) {
            return ['m', 'm', ''];
        }
        if (in_array($v, ['w', 'f', 'weiblich', 'female', 'mädchen', 'maedchen'], true)) {
            return ['w', 'w', ''];
        }
        if (in_array($v, ['d', 'divers', 'diverse', 'x'], true)) {
            return ['d', 'd', ''];
        }

        return [null, "⚠ {$v}?", "Geschlecht „{$v}“ unbekannt (m/w/d)"];
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

    private function personKey(?string $vorname, ?string $nachname, ?string $gebDat): string
    {
        return $this->key("$vorname $nachname") . '|' . ($gebDat ?? '');
    }

    /** Geburtsdatum eines Models als Y-m-d (oder null). */
    private function datumStr(mixed $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }

        return $wert instanceof \DateTimeInterface ? $wert->format('Y-m-d') : (string) $wert;
    }

    /** @param array<string,array{0:mixed,1:mixed}> $aenderungen */
    private function diffText(array $aenderungen): string
    {
        $label = [
            'vorname' => 'Vorname', 'nachname' => 'Nachname', 'quell_id' => 'ID',
            'klasse_id' => 'Klasse', 'geburtsdatum' => 'Geburtsdatum',
            'geburtsort' => 'Geburtsort', 'geschlecht' => 'Geschlecht',
        ];
        $teile = [];
        foreach ($aenderungen as $feld => $_) {
            $teile[] = ($label[$feld] ?? $feld) . ' geändert';
        }

        return implode('; ', array_unique($teile));
    }
}
