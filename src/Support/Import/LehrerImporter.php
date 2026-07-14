<?php

namespace Intranet\Modules\Schulzeugnis\Support\Import;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Importiert die Lehrer eines Ziel-Schuljahres aus einer CSV.
 *
 * Lehrer sind je Schuljahr gespeichert (`zeugnis_schuljahr_lehrer`). Die Kopplung
 * an das Intranet-Konto läuft über die stabile externe ID aus dem Quellsystem
 * (Linear): `quell_id` ↔ Core-`users.externe_id`. Das ist robuster als die E-Mail
 * (die sich ändern kann). Gibt es (noch) kein Konto zu einer quell_id, wird der
 * Lehrer trotzdem angelegt (Klartext-Name + quell_id) und die Zeile gewarnt – der
 * tägliche Abgleich zieht die Verknüpfung nach, sobald das Konto existiert.
 *
 * Wiedererkennung (idempotent je Schuljahr): primär über quell_id, sonst über Name.
 *
 * Erwartete Spalten (Kopfzeile, Reihenfolge/Groß-Klein egal):
 *   ExterneID  (optional) stabile ID aus Linear = users.externe_id
 *   Vorname    (Pflicht)
 *   Nachname   (Pflicht)
 */
class LehrerImporter
{
    /** Akzeptierte (normalisierte) Kopf-Namen für die externe ID. */
    private const EXT_ALIASE = ['externeid', 'quellid', 'externid', 'linearid'];

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

        // Welche Spalte trägt die externe ID (falls überhaupt eine)?
        $extKey = null;
        foreach (self::EXT_ALIASE as $alias) {
            if (in_array($alias, $kopf, true)) {
                $extKey = $alias;
                break;
            }
        }

        // Bestehende Lehrer des Ziel-Schuljahres indizieren.
        $bestehende  = $schuljahr->lehrer()->get();
        $nachQuellId = [];
        $nachName    = [];
        foreach ($bestehende as $l) {
            if (filled($l->quell_id)) {
                $nachQuellId[$this->idKey($l->quell_id)] = $l;
            }
            $nachName[$this->nameKey($l->vorname, $l->nachname)] = $l;
        }

        // Alle vorkommenden externen IDs in einer Abfrage zu Core-Benutzern auflösen.
        $userNachExt = $this->benutzerZuExterneIds($extKey ? $zeilen : [], $extKey);

        $ergebnis      = [];
        $gesehen       = [];
        $getroffeneIds = [];
        $ohneKonto     = [];
        $zaehl = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'warnung' => 0, 'fehler' => 0];

        foreach ($zeilen as $index => $zeile) {
            $zeilenNr = $index + 2;
            $vorname  = trim($zeile['vorname'] ?? '');
            $nachname = trim($zeile['nachname'] ?? '');
            $ext      = $extKey ? trim($zeile[$extKey] ?? '') : '';

            if ($vorname === '' || $nachname === '') {
                $ergebnis[] = $this->row($zeilenNr, 'fehler', [$ext ?: '—', $vorname ?: '—', $nachname ?: '—', '—'],
                    'Vor- und Nachname sind Pflicht – Zeile übersprungen.');
                $zaehl['fehler']++;
                continue;
            }

            $user   = $ext !== '' ? ($userNachExt[$this->idKey($ext)] ?? null) : null;
            $userId = $user?->id;

            // Konto-Zelle + optionale Warnung.
            $kontoZelle = '—';
            $kontoWarn  = '';
            $keinKonto  = false;
            if ($ext !== '') {
                if ($user) {
                    $kontoZelle = '✓ ' . $user->name;
                } else {
                    $kontoZelle = '⚠ kein Konto';
                    $kontoWarn  = ' ⚠ Kein Intranet-Konto zu ExterneID ' . $ext . ' – ohne Verknüpfung angelegt.';
                    $keinKonto  = true;
                }
            }

            // Duplikat innerhalb der Datei.
            $dupKey = $ext !== '' ? 'e:' . $this->idKey($ext) : 'n:' . $this->nameKey($vorname, $nachname);
            if (isset($gesehen[$dupKey])) {
                $ergebnis[] = $this->row($zeilenNr, 'warnung', [$ext ?: '—', $vorname, $nachname, $kontoZelle],
                    'Doppelt in der Datei (schon in Zeile ' . $gesehen[$dupKey] . ') – übersprungen.');
                $zaehl['warnung']++;
                continue;
            }
            $gesehen[$dupKey] = $zeilenNr;

            // Wiedererkennung: externe ID bevorzugt, sonst Name.
            $vorhanden = null;
            if ($ext !== '' && isset($nachQuellId[$this->idKey($ext)])) {
                $vorhanden = $nachQuellId[$this->idKey($ext)];
            } elseif (isset($nachName[$this->nameKey($vorname, $nachname)])) {
                $vorhanden = $nachName[$this->nameKey($vorname, $nachname)];
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
                // Externe ID nachtragen/aktualisieren, wenn angegeben.
                if ($ext !== '' && (string) $vorhanden->quell_id !== $ext) {
                    $aenderungen['quell_id'] = [$vorhanden->quell_id, $ext];
                }
                // core_user_id nur setzen, wenn wir jetzt eins aufgelöst haben – eine
                // leere/unauflösbare ID entfernt eine bestehende Verknüpfung NICHT.
                if ($userId && (int) $vorhanden->core_user_id !== (int) $userId) {
                    $aenderungen['core_user_id'] = [$vorhanden->core_user_id, $userId];
                }

                if ($aenderungen === []) {
                    $ergebnis[] = $this->row($zeilenNr, 'unveraendert', [$ext ?: '—', $vorname, $nachname, $kontoZelle],
                        'Bereits vorhanden, keine Änderung.' . $kontoWarn, $vorhanden->id);
                    $zaehl['unveraendert']++;
                } else {
                    $apply = [];
                    foreach ($aenderungen as $feld => [, $neu]) {
                        $apply[$feld] = $neu;
                    }
                    $ergebnis[] = $this->row($zeilenNr, 'aktualisiert', [$ext ?: '—', $vorname, $nachname, $kontoZelle],
                        $this->diffText($aenderungen) . $kontoWarn, $vorhanden->id, $apply);
                    $zaehl['aktualisiert']++;
                    if ($keinKonto) {
                        $ohneKonto[] = trim("$vorname $nachname") . " (ExterneID $ext)";
                    }
                }
            } else {
                $apply = [
                    'schuljahr_id' => $schuljahr->id,
                    'vorname'      => $vorname,
                    'nachname'     => $nachname,
                    'core_user_id' => $userId,
                    'quell_id'     => $ext ?: null,
                ];
                $ergebnis[] = $this->row($zeilenNr, 'neu', [$ext ?: '—', $vorname, $nachname, $kontoZelle],
                    'Wird neu angelegt.' . $kontoWarn, null, $apply);
                $zaehl['neu']++;
                if ($keinKonto) {
                    $ohneKonto[] = trim("$vorname $nachname") . " (ExterneID $ext)";
                }
            }
        }

        // Bestehende Lehrer, die in der Datei nicht vorkommen (nur Info, unangetastet).
        $fehlen = [];
        foreach ($bestehende as $l) {
            if (! isset($getroffeneIds[$l->id])) {
                $fehlen[] = $l->fullName();
            }
        }

        return [
            'spalten_titel' => ['ExterneID', 'Vorname', 'Nachname', 'Konto'],
            'zeilen'        => $ergebnis,
            'zaehl'         => $zaehl,
            'infos'         => [
                ['label' => "Lehrer in {$schuljahr->name} stehen nicht in der Datei und bleiben unverändert", 'items' => $fehlen, 'ton' => 'grau', 'nur_ergebnis' => true],
                ['label' => 'ohne passendes Intranet-Konto angelegt (Abgleich verknüpft automatisch, sobald das Konto existiert)', 'items' => $ohneKonto, 'ton' => 'amber'],
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
                    Lehrer::create($r['apply']);
                } elseif ($r['status'] === 'aktualisiert' && $r['ziel_id'] !== null) {
                    Lehrer::whereKey($r['ziel_id'])->update($r['apply']);
                }
            }
        });

        $z = $analyse['zaehl'];
        Protokoll::log('importiert', [
            'schuljahr_id' => (int) ($kontext['schuljahr_id'] ?? 0) ?: null,
            'beschreibung' => "Lehrer-Import: {$z['neu']} neu, {$z['aktualisiert']} aktualisiert, "
                . "{$z['unveraendert']} unverändert, {$z['warnung']} übersprungen, {$z['fehler']} Fehler.",
        ]);

        return $analyse;
    }

    /**
     * Externe IDs aller Zeilen in einer Abfrage zu Core-Benutzern auflösen.
     *
     * @param  array<int,array<string,string>>  $zeilen
     * @return array<string,\App\Models\User>   Schlüssel = normalisierte externe ID
     */
    private function benutzerZuExterneIds(array $zeilen, ?string $extKey): array
    {
        if ($extKey === null) {
            return [];
        }

        $ids = [];
        foreach ($zeilen as $z) {
            $e = trim($z[$extKey] ?? '');
            if ($e !== '') {
                $ids[$e] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $treffer = [];
        User::whereIn('externe_id', array_keys($ids))
            ->get(['id', 'name', 'externe_id'])
            ->each(function (User $u) use (&$treffer): void {
                $treffer[$this->idKey($u->externe_id)] = $u;
            });

        return $treffer;
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

    /** Normalisierter Schlüssel für den ID-Abgleich (getrimmt). */
    private function idKey(?string $s): string
    {
        return trim((string) $s);
    }

    private function nameKey(?string $vorname, ?string $nachname): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(mb_strtolower(trim((string) $vorname) . ' ' . trim((string) $nachname))));
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
            'vorname'      => 'Vorname',
            'nachname'     => 'Nachname',
            'quell_id'     => 'ExterneID',
            'core_user_id' => 'Konto',
        ][$feld] ?? $feld;
    }

    private function wert(string $feld, mixed $wert): string
    {
        if ($feld === 'core_user_id') {
            return ($wert === null || $wert === '') ? 'ohne' : 'Konto #' . $wert;
        }

        return ($wert === null || $wert === '') ? '—' : (string) $wert;
    }
}
