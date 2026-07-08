<?php

namespace Intranet\Modules\Schulzeugnis\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Schueler;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;

/**
 * Legt eine komplette Test-Struktur in einem Schuljahr an: 15 Fächer, Klassen
 * 1–13, reichlich Lehrer (mit echten Core-Benutzern), ≥20 Schüler je Klasse,
 * Lehraufträge (Fach→Lehrer→Klasse) und je Schüler ein befülltes Zeugnis mit
 * gemischten Bearbeitungs-Status.
 *
 * Idempotent bezogen auf das Ziel-Schuljahr: dessen Klassen/Lehrer/Schüler/
 * Zeugnisse werden vor dem Seeden geleert. Fächer sind global (updateOrCreate).
 * Nur für Test-/Demo-Umgebungen gedacht.
 */
class SeedDemo extends Command
{
    protected $signature = 'schulzeugnis:seed-demo
        {--schuljahr= : ID des Ziel-Schuljahres (sonst wird ein Demo-Schuljahr angelegt/verwendet)}
        {--schueler=22 : Anzahl Schüler je Klasse}
        {--password=test1234 : Passwort für die angelegten Lehrer-Konten}';

    protected $description = 'Seedet eine komplette Test-Struktur (Fächer, Klassen, Lehrer+User, Schüler, Lehraufträge, Zeugnisse).';

    private const FAECHER = [
        ['Deutsch', 'D'], ['Mathematik', 'M'], ['Englisch', 'E'], ['Französisch', 'F'],
        ['Geschichte', 'Ge'], ['Erdkunde', 'Ek'], ['Biologie', 'Bio'], ['Physik', 'Ph'],
        ['Chemie', 'Ch'], ['Musik', 'Mu'], ['Kunst', 'Ku'], ['Eurythmie', 'Eu'],
        ['Handarbeit', 'Ha'], ['Sport', 'Sp'], ['Gartenbau', 'Gb'],
    ];

    private const VORNAMEN = [
        'Lina', 'Ben', 'Emma', 'Paul', 'Mia', 'Jonas', 'Hannah', 'Luis', 'Sofia', 'Finn',
        'Lea', 'Noah', 'Marie', 'Elias', 'Clara', 'Leon', 'Ida', 'Felix', 'Ella', 'Max',
        'Frieda', 'Anton', 'Greta', 'Moritz', 'Johanna', 'Theo', 'Nele', 'Jakob', 'Pia', 'Oskar',
    ];

    private const NACHNAMEN = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker',
        'Hoffmann', 'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder',
        'Neumann', 'Braun', 'Zimmermann', 'Krüger', 'Hartmann', 'Lange', 'Werner', 'Krause',
    ];

    private const STATI = [
        'unbearbeitet', 'in_arbeit', 'frei_zur_korrektur', 'in_korrektur',
        'korrektur_noetig', 'korrektur_durchgefuehrt', 'in_ueberarbeitung', 'vollstaendig',
    ];

    public function handle(): int
    {
        $proKlasse = max(1, (int) $this->option('schueler'));
        $password  = (string) $this->option('password');

        $schuljahr = $this->resolveSchuljahr();
        $this->info("Ziel-Schuljahr: {$schuljahr->name} (ID {$schuljahr->id})");

        $this->reset($schuljahr);

        $faecher = $this->seedFaecher();
        $this->line('  '.count($faecher).' Fächer angelegt/aktualisiert.');

        [$textFormat, $notenFormat] = $this->formate();

        $lehrer = $this->seedLehrer($schuljahr, $password, 30);
        $this->line('  '.count($lehrer).' Lehrer (+ Core-User) angelegt.');

        DB::transaction(function () use ($schuljahr, $faecher, $lehrer, $textFormat, $notenFormat, $proKlasse) {
            $lehrerIdx = 0;
            $zeugnisSum = 0;

            for ($stufe = 1; $stufe <= 13; $stufe++) {
                $format = $stufe >= 11 && $notenFormat ? $notenFormat : $textFormat;

                $klasse = Klasse::create([
                    'schuljahr_id'       => $schuljahr->id,
                    'name'               => (string) $stufe,
                    'standard_format_id' => $format?->id,
                    'klassenlehrer_id'   => $lehrer[$lehrerIdx % count($lehrer)]->id,
                ]);
                $lehrerIdx++;

                // Lehraufträge: jedes Fach bekommt 1–2 Lehrer (Team-Teaching bei jedem 4.).
                foreach ($faecher as $fi => $fach) {
                    $anzahl = ($fi % 4 === 0) ? 2 : 1;
                    for ($k = 0; $k < $anzahl; $k++) {
                        Lehrauftrag::create([
                            'klasse_id' => $klasse->id,
                            'fach_id'   => $fach->id,
                            'lehrer_id' => $lehrer[($fi + $k + $stufe) % count($lehrer)]->id,
                        ]);
                    }
                }

                // Autoren je Fach (für die Zeugnis-Abschnitte) einmal vorberechnen.
                $autorProFach = [];
                foreach ($klasse->lehrauftraege()->with('lehrer')->get()->groupBy('fach_id') as $fachId => $gruppe) {
                    $autorProFach[$fachId] = $gruppe->map(fn ($la) => $la->lehrer?->fullName())->filter()->unique()->implode(', ');
                }

                $klassenlehrerName = $lehrer[($stufe - 1) % count($lehrer)]->fullName();
                $istNoten = $format && $format->typ === 'noten';

                for ($n = 0; $n < $proKlasse; $n++) {
                    $vorname  = self::VORNAMEN[($n + $stufe) % count(self::VORNAMEN)];
                    $nachname = self::NACHNAMEN[($n * 2 + $stufe) % count(self::NACHNAMEN)];

                    $schueler = Schueler::create([
                        'schuljahr_id' => $schuljahr->id,
                        'klasse_id'    => $klasse->id,
                        'vorname'      => $vorname,
                        'nachname'     => $nachname,
                        'geburtsdatum' => sprintf('%04d-%02d-%02d', 2018 - $stufe, ($n % 12) + 1, ($n % 27) + 1),
                        'geburtsort'   => 'Gütersloh',
                        'geschlecht'   => $n % 2 === 0 ? 'w' : 'm',
                        'quell_id'     => sprintf('DEMO-%02d-%03d', $stufe, $n + 1),
                    ]);

                    $zeugnis = Zeugnis::create([
                        'schueler_id' => $schueler->id,
                        'format_id'   => $format?->id,
                        'status'      => Zeugnis::STATUS_ENTWURF,
                    ]);

                    $rows = [];
                    // Haupttext
                    $rows[] = $this->abschnittRow($zeugnis->id, Abschnitt::TYP_HAUPTTEXT, null, $klassenlehrerName, 0, ($n + $stufe));
                    // Fachtexte / Noten je Fach mit Lehrauftrag
                    foreach ($faecher as $fi => $fach) {
                        if (! isset($autorProFach[$fach->id])) {
                            continue;
                        }
                        $rows[] = $this->abschnittRow(
                            $zeugnis->id,
                            $istNoten ? Abschnitt::TYP_NOTE : Abschnitt::TYP_FACHTEXT,
                            $fach->id,
                            $autorProFach[$fach->id],
                            $fi + 1,
                            ($n + $fi + $stufe),
                            $istNoten
                        );
                    }
                    Abschnitt::insert($rows);
                    $zeugnisSum++;
                }
            }

            $this->line("  {$zeugnisSum} Zeugnisse mit Abschnitten angelegt.");
        });

        $this->newLine();
        $this->info('Demo-Struktur fertig. Lehrer-Login: lehrer01@zeugnis.test … lehrer30@zeugnis.test · Passwort: '.$password);
        $this->line('Zeugnis-Tabelle z. B. unter: /modules/schulzeugnis/klassen/{id}/zeugnisse');

        return self::SUCCESS;
    }

    /** Eine Abschnitt-Zeile (für bulk insert) mit gemischtem Status/Inhalt bauen. */
    private function abschnittRow(int $zeugnisId, string $typ, ?int $fachId, ?string $autor, int $reihenfolge, int $variation, bool $istNoten = false): array
    {
        $status = self::STATI[$variation % count(self::STATI)];
        $fortgeschritten = ! in_array($status, ['unbearbeitet', 'in_arbeit'], true);

        $inhalt = null;
        $note = null;
        if ($istNoten) {
            $note = $fortgeschritten ? (string) (($variation % 6) + 1) : null;
        } elseif ($fortgeschritten) {
            $inhalt = 'Zeigt im Unterricht Interesse und Ausdauer, arbeitet zuverlässig mit und '
                . 'entwickelt die eigenen Fähigkeiten mit Freude weiter. (Demo-Text)';
        }

        return [
            'zeugnis_id'      => $zeugnisId,
            'typ'             => $typ,
            'fach_id'         => $fachId,
            'autor_lehrer_id' => null,
            'autor_name'      => $autor,
            'inhalt'          => $inhalt,
            'note'            => $note,
            'status'          => $status,
            'reihenfolge'     => $reihenfolge,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    private function resolveSchuljahr(): Schuljahr
    {
        if ($id = $this->option('schuljahr')) {
            return Schuljahr::findOrFail((int) $id);
        }

        return Schuljahr::firstOrCreate(
            ['name' => 'Demo 2026/2027'],
            ['start_date' => '2026-08-01', 'end_date' => '2027-07-31', 'is_active' => false],
        );
    }

    /** Klassen/Lehrer/Schüler/Zeugnisse des Schuljahres leeren (Kaskaden erledigen den Rest). */
    private function reset(Schuljahr $schuljahr): void
    {
        $this->warn("Leere vorhandene Modul-Daten in Schuljahr {$schuljahr->id} …");
        Klasse::where('schuljahr_id', $schuljahr->id)->delete();       // → Schüler → Zeugnisse → Abschnitte, Lehraufträge
        Schueler::where('schuljahr_id', $schuljahr->id)->delete();     // Sicherheit: klassenlose Schüler
        Lehrer::where('schuljahr_id', $schuljahr->id)->delete();
    }

    /** @return array<int,Fach> */
    private function seedFaecher(): array
    {
        $faecher = [];
        foreach (self::FAECHER as $i => [$name, $kuerzel]) {
            $faecher[] = Fach::updateOrCreate(
                ['name' => $name],
                ['kuerzel' => $kuerzel, 'reihenfolge' => $i + 1, 'aktiv' => true],
            );
        }

        return $faecher;
    }

    /** @return array{0:?Format,1:?Format} [Textformat, Notenformat] */
    private function formate(): array
    {
        return [
            Format::where('typ', 'text')->orderBy('id')->first(),
            Format::where('typ', 'noten')->orderBy('id')->first(),
        ];
    }

    /**
     * Lehrer je Schuljahr + zugehörige Core-Benutzer anlegen (idempotent über E-Mail).
     *
     * @return array<int,Lehrer>
     */
    private function seedLehrer(Schuljahr $schuljahr, string $password, int $anzahl): array
    {
        $lehrer = [];
        for ($i = 1; $i <= $anzahl; $i++) {
            $vorname  = self::VORNAMEN[$i % count(self::VORNAMEN)];
            $nachname = self::NACHNAMEN[$i % count(self::NACHNAMEN)];
            $email    = sprintf('lehrer%02d@zeugnis.test', $i);

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => "{$vorname} {$nachname}", 'password' => Hash::make($password)],
            );

            $lehrer[] = Lehrer::create([
                'schuljahr_id' => $schuljahr->id,
                'core_user_id' => $user->id,
                'vorname'      => $vorname,
                'nachname'     => $nachname,
            ]);
        }

        return $lehrer;
    }
}
