<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Importiert die Lehraufträge (Fach × Lehrer je Klasse) eines Ziel-Schuljahres.
 *
 * Jede Zeile ordnet einer Klasse ein Fach und eine Lehrkraft zu. Team-Teaching =
 * mehrere Zeilen je Klasse/Fach. Alle drei Bezüge sind Pflicht und müssen im
 * Schuljahr existieren (Klasse per Name, Fach per Name/Kürzel, Lehrer per externe
 * ID = quell_id). Additiv: fehlende Zuordnungen werden angelegt, vorhandene bleiben;
 * es wird nichts entfernt. Idempotent über das Tripel (Klasse, Fach, Lehrer).
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   Klasse    (Pflicht) Name der Klasse im Ziel-Schuljahr
 *   Fach      (Pflicht) Name oder Kürzel eines vorhandenen Fachs
 *   LehrerID  (Pflicht) externe ID (Linear) der Lehrkraft
 */
class LehrauftragImporter
{
    private const KLASSE_ALIASE = ['klasse', 'klassenname'];
    private const FACH_ALIASE   = ['fach', 'fachname', 'kuerzel'];
    private const LEHRER_ALIASE = ['lehrerid', 'lehrerexterneid', 'externeid', 'id'];

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
        $fachKey   = $this->spalte($kopf, self::FACH_ALIASE);
        $lehrerKey = $this->spalte($kopf, self::LEHRER_ALIASE);
        if ($klasseKey === null || $fachKey === null || $lehrerKey === null) {
            throw new ImportFehler('Die Spalten „Klasse", „Fach" und „LehrerID" müssen vorhanden sein.');
        }

        // Register aufbauen.
        $klassen = [];
        foreach ($schuljahr->klassen()->get() as $k) {
            $klassen[$this->key($k->name)] = $k;
        }
        $faecherNachName    = [];
        $faecherNachKuerzel = [];
        foreach (Fach::all() as $f) {
            $faecherNachName[$this->key($f->name)] = $f;
            if (filled($f->kuerzel)) {
                $faecherNachKuerzel[$this->key($f->kuerzel)] = $f;
            }
        }
        $lehrer = [];
        foreach ($schuljahr->lehrer()->whereNotNull('quell_id')->get() as $l) {
            $lehrer[trim((string) $l->quell_id)] = $l;
        }

        // Bestehende Lehraufträge des Schuljahres als Tripel-Set.
        $klasseIds = array_map(fn ($k) => $k->id, $klassen);
        $vorhanden = [];
        if ($klasseIds !== []) {
            foreach (Lehrauftrag::whereIn('klasse_id', $klasseIds)->get() as $la) {
                $vorhanden[$la->klasse_id . '|' . $la->fach_id . '|' . $la->lehrer_id] = true;
            }
        }

        $ergebnis = [];
        $gesehen  = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr = $index + 2;
            $kn = trim($zeile[$klasseKey] ?? '');
            $fn = trim($zeile[$fachKey] ?? '');
            $ln = trim($zeile[$lehrerKey] ?? '');

            $fehler = [];

            // Klasse.
            $klasse = $kn !== '' ? ($klassen[$this->key($kn)] ?? null) : null;
            if ($kn === '') {
                $fehler[] = 'Klasse fehlt';
                $kZelle = '—';
            } elseif ($klasse === null) {
                $fehler[] = "Klasse „{$kn}“ nicht gefunden";
                $kZelle = "⚠ {$kn}?";
            } else {
                $kZelle = $klasse->name;
            }

            // Fach (Name oder Kürzel).
            $fach = null;
            if ($fn !== '') {
                $fach = $faecherNachName[$this->key($fn)] ?? $faecherNachKuerzel[$this->key($fn)] ?? null;
            }
            if ($fn === '') {
                $fehler[] = 'Fach fehlt';
                $fZelle = '—';
            } elseif ($fach === null) {
                $fehler[] = "Fach „{$fn}“ nicht gefunden";
                $fZelle = "⚠ {$fn}?";
            } else {
                $fZelle = $fach->name;
            }

            // Lehrer (externe ID).
            $lk = $ln !== '' ? ($lehrer[$ln] ?? null) : null;
            if ($ln === '') {
                $fehler[] = 'LehrerID fehlt';
                $lZelle = '—';
            } elseif ($lk === null) {
                $fehler[] = "Lehrer-ID „{$ln}“ nicht gefunden";
                $lZelle = "⚠ {$ln}?";
            } else {
                $lZelle = $lk->fullName();
            }

            $zellen = [$kZelle, $fZelle, $lZelle];

            if ($fehler !== []) {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', $zellen, implode('; ', $fehler) . ' – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            $tripel = $klasse->id . '|' . $fach->id . '|' . $lk->id;

            if (isset($gesehen[$tripel])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', $zellen,
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$tripel] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$tripel] = $zeilenNr;

            if (isset($vorhanden[$tripel])) {
                $ergebnis[] = $this->row($zeilenNr, 'unveraendert', $zellen, 'Lehrauftrag besteht bereits.');
                $zaehl['unveraendert']++;
            } else {
                $ergebnis[] = $this->row($zeilenNr, 'neu', $zellen, 'Wird neu angelegt.', [
                    'klasse_id' => $klasse->id,
                    'fach_id'   => $fach->id,
                    'lehrer_id' => $lk->id,
                ]);
                $zaehl['neu']++;
            }
        }

        return [
            'spalten_titel' => ['Klasse', 'Fach', 'Lehrer'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [],
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
                    Lehrauftrag::create($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        Protokoll::log('importiert', [
            'schuljahr_id' => (int) ($kontext['schuljahr_id'] ?? 0) ?: null,
            'beschreibung' => "Lehrauftrag-Import: {$z['neu']} neu, {$z['unveraendert']} bereits vorhanden, "
                . "{$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ]);

        return $analyse;
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
    private function row(int $zeile, string $status, array $zellen, string $hinweis, array $apply = []): array
    {
        return [
            'zeile'   => $zeile,
            'status'  => $status,
            'zellen'  => $zellen,
            'hinweis' => $hinweis,
            'ziel_id' => null,
            'apply'   => $apply,
        ];
    }

    private function key(?string $s): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $s)));
    }
}
