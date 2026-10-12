<?php

namespace Intranet\Modules\Schulzeugnis\Tasks\Linear;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Ekkon\Ekkon;
use App\Ekkon\Tasks\EkkonTask;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Support\Import\FaecherImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\ImportFehler;
use Intranet\Modules\Schulzeugnis\Support\Import\KlasseImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrauftragImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\LehrerImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\SchuelerImporter;
use Intranet\Modules\Schulzeugnis\Support\Import\SchuljahrImporter;
use Intranet\Modules\Schulzeugnis\Support\Linear\LinearQuelle;

/**
 * Überträgt die Schuljahres-Stammdaten aus Linear ins Zeugnis-Modul.
 *
 * ── Was der Task tut ─────────────────────────────────────────────────────────
 *
 * Linears `WD_Zeitraum` führt die Schuljahre (2025/2026 = ID 28); `WD_Schl`
 * (Schüler↔Klasse), `WD_Lehrer` (Lehrer↔Klasse), `WD_KlFach` und `WD_LehrFach`
 * (Fächer/Lehraufträge) hängen über diese ID am Jahr. Der Task erkennt
 * Zeiträume, die es im Zeugnis-Modul noch nicht gibt, legt sie an (INAKTIV –
 * aktiv setzt ein Mensch, dazu geht eine Benachrichtigung raus) und zieht
 * sie danach jede Nacht additiv nach: neue Schüler, Klassenwechsel, neue
 * Lehraufträge. Gelöscht oder geleert wird NIE – das garantieren die Importer
 * des Zeugnis-Moduls, die dieser Task füttert (dieselben, die auch der
 * manuelle CSV-Import benutzt; Reihenfolge Fächer → Lehrer → Klassen →
 * Schüler → Lehraufträge, weil spätere Importer Bezüge auf frühere auflösen).
 *
 * Standardmäßig wird AUSSCHLIESSLICH der neueste Jahrgang abgeglichen (höchste
 * WD_Zeitraum-ID mit Schülern): `Art`, `Von` und `Bis` sind in Linear nicht
 * gepflegt (Art durchweg NULL), taugen also nicht, um laufende von alten
 * Jahren zu trennen – die ID-Reihenfolge schon. Schaltet man die Einstellung
 * aus, werden alle Zeiträume mit Schülern abgeglichen, deren `Bis` nicht in
 * der Vergangenheit liegt.
 *
 * Der Klassenlehrer wird bewusst NICHT importiert: `WD_Lehrer.Funktion` ist
 * in Linear nicht gepflegt. Das Feld bleibt Handpflege im Zeugnis-Modul –
 * der KlasseImporter lässt es unangetastet, weil die Spalte im Datensatz
 * schlicht fehlt.
 *
 * ── Weiche Kopplung ──────────────────────────────────────────────────────────
 *
 * Das Zeugnis-Modul ist bewusst KEINE composer-Abhängigkeit dieses Pakets:
 * Der Task prüft zur Laufzeit, ob es (in ausreichender Version, samt
 * quell_id-Migration) installiert ist, und meldet sich sonst sauber ab.
 *
 * ── Zeitpunkt ────────────────────────────────────────────────────────────────
 *
 * 04:00, eine halbe Stunde nach Linear/BenutzerImport (03:30): Der legt die
 * Intranet-Konten mit `users.externe_id` an, sodass der LehrerImporter hier
 * die Konto-Verknüpfung (`core_user_id`) noch in derselben Nacht auflöst.
 */
class ZeugnisImport extends EkkonTask
{
    public string $category = 'Linear';

    /** Liest die Linear-Datenbank: bei Ausfall nicht starten, sondern nachholen (Core, 2026-10-01). */
    public bool $brauchtWawi = true;

    /**
     * Lehrer werden über users.externe_id zugeordnet, die Linear/BenutzerImport setzt: erst danach laufen.
     *
     * @var list<string>
     */
    public array $folgtAuf = ['Linear/BenutzerImport'];

    public string $description = 'Schuljahre samt Klassen, Fächern, Lehrern, Schülern und Lehraufträgen '
        .'aus Linear ins Zeugnis-Modul übernehmen (additiv, nie löschen).';

    public array $meldungsarten = [
        'linear-zeugnis-schuljahr-neu' => 'Linear: Neues Schuljahr im Zeugnis-Modul angelegt',
        'linear-zeugnis-import-fehler' => 'Linear: Zeugnis-Abgleich mit Fehlern',
    ];

    public array $einstellungen = [
        'probelauf' => [
            'typ' => 'ja_nein',
            'label' => 'Probelauf (nichts schreiben)',
            'standard' => true,
            'hilfe' => 'Liest aus Linear und berichtet je Schuljahr, was er täte – schreibt aber '
                .'nichts ins Zeugnis-Modul. Zum Scharfschalten das Häkchen entfernen.',
        ],
        'nur_neuester_jahrgang' => [
            'typ' => 'ja_nein',
            'label' => 'Ausschließlich neuesten Jahrgang',
            'standard' => true,
            'hilfe' => 'Abgeglichen wird nur der neueste Zeitraum mit Schülern (höchste ID in '
                .'WD_Zeitraum). Ausschalten, um alle Zeiträume mit Schülern abzugleichen, deren '
                .'Bis-Datum nicht in der Vergangenheit liegt – Vorsicht: Von/Bis sind in Linear '
                .'kaum gepflegt, das kann viele Altjahre hereinlassen.',
        ],
    ];

    /** Reihenfolge ist Pflicht: spätere Importer lösen Bezüge auf frühere auf. */
    private const IMPORTER = [
        'faecher'       => FaecherImporter::class,
        'lehrer'        => LehrerImporter::class,
        'klassen'       => KlasseImporter::class,
        'schueler'      => SchuelerImporter::class,
        'lehrauftraege' => LehrauftragImporter::class,
    ];

    private const ART_LABEL = [
        'faecher'       => 'Fächer',
        'lehrer'        => 'Lehrer',
        'klassen'       => 'Klassen',
        'schueler'      => 'Schüler',
        'lehrauftraege' => 'Lehraufträge',
    ];

    /** Akteur-Schnappschuss für das Zeugnis-Protokoll (Cron hat keinen auth()-User). */
    private const AKTEUR = 'System (Linear-Abgleich)';

    /** Gemeinsame Linear-Leser (auch für die Import-Knöpfe der Verwaltungsseiten). */
    private ?LinearQuelle $quelle = null;

    public function schedule(): string
    {
        return '0 4 * * *'; // nach Linear/BenutzerImport (03:30), s. Klassen-Docblock
    }

    public function run(): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine MSSQL-Zugangsdaten – siehe .env (MSSQL_*).');
        }

        // Weiche Kopplung: Der Check auf die Importer-Klasse (statt nur des Moduls)
        // fängt auch eine zu alte Modul-Version ab; `::class` löst kein Autoloading
        // aus, instanziiert wird erst nach dem Guard.
        if (! class_exists(SchuljahrImporter::class)) {
            $this->msg('Zeugnis-Modul nicht installiert (oder zu alt – SchuljahrImporter fehlt) – übersprungen.');

            return ['hinweis' => 'Zeugnis-Modul nicht installiert'];
        }
        if (! Schema::hasColumn('zeugnis_schuljahre', 'quell_id')) {
            $this->msg('Spalte zeugnis_schuljahre.quell_id fehlt – Migration des Zeugnis-Moduls noch nicht gelaufen? Übersprungen.');

            return ['hinweis' => 'Migration fehlt'];
        }

        $probelauf = (bool) $this->einstellung('probelauf');
        if ($probelauf) {
            $this->msg('PROBELAUF – es wird nichts geschrieben. Zum Scharfschalten das Häkchen oben unter „Einstellungen" entfernen.');
        }

        $zeitraeume = $this->quelle()->zeitraeume();
        $this->debug['zeitraeume'] = $zeitraeume;

        $kandidaten = $this->kandidaten($zeitraeume);
        $this->msg(count($zeitraeume).' Zeiträume in Linear gelesen, davon '.count($kandidaten)
            .' im Sync-Umfang '.((bool) $this->einstellung('nur_neuester_jahrgang')
                ? '(nur neuester Jahrgang)' : '(mit Schülern, nicht abgelaufen)').'.');

        if ($kandidaten === []) {
            return ['probelauf' => $probelauf, 'zeitraeume_gelesen' => count($zeitraeume), 'im_sync_umfang' => 0];
        }

        // 1) Schuljahre anlegen bzw. mit ihrer Quell-ID verknüpfen.
        $importer   = new SchuljahrImporter();
        $kopf       = ['quellid', 'name', 'von', 'bis']; // bereits in normalisierter Form (CsvLeser::normalisiere)
        $sjZeilen   = array_map(fn (array $z): array => [
            'quellid' => (string) $z['id'],
            'name'    => $this->schuljahrName($z),
            'von'     => $z['von'],
            'bis'     => $z['bis'],
        ], $kandidaten);
        $sjKontext  = ['akteur_name' => self::AKTEUR];
        $sjErgebnis = $probelauf
            ? $importer->analysiere($kopf, $sjZeilen, $sjKontext)
            : $importer->importiere($kopf, $sjZeilen, $sjKontext);

        $sz = $sjErgebnis['zaehl'];
        $this->msg("Schuljahre: {$sz['neu']} neu, {$sz['aktualisiert']} aktualisiert/verknüpft, "
            ."{$sz['unveraendert']} unverändert, {$sz['warnung']} Warnungen, {$sz['fehler']} Fehler.");

        $gesamt = $sz;
        $jeSchuljahr = [];
        $neueSchuljahre = [];

        // Ergebniszeilen laufen 1:1 parallel zu den Eingabezeilen (zeile = index + 2).
        $statusJeKandidat = [];
        foreach ($sjErgebnis['zeilen'] as $r) {
            $statusJeKandidat[$r['zeile'] - 2] = $r;
        }

        // 2) Je Schuljahr die fünf Stammdaten-Importe.
        foreach ($kandidaten as $i => $z) {
            $name   = $this->schuljahrName($z);
            $status = $statusJeKandidat[$i]['status'] ?? 'fehler';

            if (in_array($status, ['warnung', 'fehler'], true)) {
                $this->msg("„{$name}“: übersprungen – ".($statusJeKandidat[$i]['hinweis'] ?? 'siehe Schuljahr-Bericht'));
                continue;
            }

            if ($status === 'neu') {
                $neueSchuljahre[] = ['quell_id' => (string) $z['id'], 'name' => $name];
            }

            // Im Probelauf existiert ein neues Jahr noch nicht – die Detail-Analyse
            // der Importer braucht aber eine Schuljahr-ID. Dann nur Rohzahlen melden.
            $sj = Schuljahr::where('quell_id', (string) $z['id'])->first()
                ?? Schuljahr::where('name', $name)->first();

            if (! $sj) {
                $roh = $this->quelle()->rohzahlen($z['id']);
                $this->msg("„{$name}“ würde neu angelegt (inaktiv) – Rohdaten aus Linear: "
                    ."{$roh['faecher']} Fächer, {$roh['lehrer']} Lehrer, {$roh['klassen']} Klassen, "
                    ."{$roh['schueler']} Schüler, {$roh['lehrauftraege']} Lehraufträge. "
                    .'Detail-Analyse erst nach der Anlage (Probelauf ausschalten).');
                $jeSchuljahr[$name] = ['rohdaten' => $roh];
                continue;
            }

            try {
                $zaehlJahr = $this->stammdatenAbgleichen($sj, $z['id'], $probelauf);
            } catch (ImportFehler $e) {
                $this->msg("„{$name}“: Import-Fehler – {$e->getMessage()}");
                $gesamt['fehler']++;
                continue;
            }

            $jeSchuljahr[$name] = $zaehlJahr;
            foreach ($zaehlJahr as $zaehl) {
                foreach ($zaehl as $schluessel => $wert) {
                    $gesamt[$schluessel] += $wert;
                }
            }

            $this->msg("„{$name}“: ".implode(' · ', array_map(
                fn (string $art, array $za) => self::ART_LABEL[$art]." {$za['neu']} neu/{$za['aktualisiert']} akt./{$za['unveraendert']} unv."
                    .($za['warnung'] > 0 ? "/{$za['warnung']} Warn." : '')
                    .($za['fehler'] > 0 ? "/{$za['fehler']} FEHLER" : ''),
                array_keys($zaehlJahr),
                $zaehlJahr,
            )));
        }

        // 3) Benachrichtigungen – WER informiert wird, entscheiden die Routen.
        if (! $probelauf) {
            foreach ($neueSchuljahre as $neu) {
                $link = Route::has('module.schulzeugnis.schuljahre.index')
                    ? ":\n".route('module.schulzeugnis.schuljahre.index')
                    : '.';
                $this->benachrichtige(
                    'linear-zeugnis-schuljahr-neu',
                    "Neues Schuljahr {$neu['name']} aus Linear angelegt",
                    "Das Schuljahr wurde im Zeugnis-Modul angelegt und mit Stammdaten aus Linear befüllt. "
                        ."Es ist NICHT aktiv – bei Bedarf aktivieren unter Zeugnisse → Schuljahre{$link}",
                    ['quell_id' => $neu['quell_id']],
                    // Je Linear-Jahr genau einmal, für immer – auch bei mehrfachem Lauf.
                    'linear-zeugnis-schuljahr-'.$neu['quell_id'],
                );
            }

            // Nächtlicher Lauf ohne Zuschauer: stumme Fehler würden sonst monatelang faulen.
            if ($gesamt['fehler'] > 0) {
                $this->benachrichtige(
                    'linear-zeugnis-import-fehler',
                    "Zeugnis-Abgleich: {$gesamt['fehler']} Fehler",
                    "Der Linear-Abgleich hat {$gesamt['fehler']} fehlerhafte Zeilen übersprungen. "
                        .'Details in der Lauf-Historie des Tasks Linear/ZeugnisImport.',
                    ['gesamt' => $gesamt],
                    'linear-zeugnis-fehler-'.now()->toDateString().'-'.$gesamt['fehler'],
                );
            }
        }

        // Auffälligkeiten der Leser (Ausgetretene, unlesbare Daten …) in die Lauf-Historie.
        $this->debug = array_replace($this->debug, $this->quelle()->debug);

        return [
            'probelauf'          => $probelauf,
            'zeitraeume_gelesen' => count($zeitraeume),
            'im_sync_umfang'     => count($kandidaten),
            'schuljahre_neu'     => array_column($neueSchuljahre, 'name'),
            'gesamt'             => $gesamt,
            'je_schuljahr'       => $jeSchuljahr,
        ];
    }

    /**
     * Sync-Umfang. Standard: nur der neueste Zeitraum mit Schülern (höchste ID)
     * – Art/Von/Bis sind in Linear nicht gepflegt, die ID-Reihenfolge ist das
     * einzig verlässliche Signal für „aktuell". Ohne die Einstellung gelten
     * alle Zeiträume mit Schülern, deren Bis nicht in der Vergangenheit liegt.
     *
     * @param  array<int,array<string,mixed>>  $zeitraeume
     * @return array<int,array<string,mixed>>
     */
    private function kandidaten(array $zeitraeume): array
    {
        $mitSchuelern = array_values(array_filter(
            $zeitraeume,
            fn (array $z): bool => $z['schueler_zahl'] > 0, // Verwaltungsreste ohne Schüler raus
        ));

        if ((bool) $this->einstellung('nur_neuester_jahrgang')) {
            // Höchste ID gewinnt; die Liste kommt bereits nach ID sortiert.
            return $mitSchuelern === [] ? [] : [end($mitSchuelern)];
        }

        $heute = now()->toDateString();

        return array_values(array_filter(
            $mitSchuelern,
            fn (array $z): bool => $z['bis'] === '' || $z['bis'] >= $heute, // abgelaufen = Altbestand
        ));
    }

    /** @param array<string,mixed> $z */
    private function schuljahrName(array $z): string
    {
        return LinearQuelle::zeitraumName($z);
    }

    /**
     * Die fünf Stammdaten-Importe für ein (existierendes) Schuljahr.
     *
     * @return array<string,array<string,int>>  zaehl je Import-Art
     */
    private function stammdatenAbgleichen(Schuljahr $sj, int $zid, bool $probelauf): array
    {
        $daten = [
            'faecher'       => $this->quelle()->faecher($zid),
            'lehrer'        => $this->quelle()->lehrer($zid),
            'klassen'       => $this->quelle()->klassen($zid),
            'schueler'      => $this->quelle()->schueler($zid),
            'lehrauftraege' => $this->quelle()->lehrauftraege($zid),
        ];

        $kontext = ['schuljahr_id' => $sj->id, 'akteur_name' => self::AKTEUR];
        $zaehl   = [];

        foreach (self::IMPORTER as $art => $klasse) {
            [$kopf, $zeilen] = $daten[$art];
            $importer = new $klasse();
            $ergebnis = $probelauf
                ? $importer->analysiere($kopf, $zeilen, $kontext)
                : $importer->importiere($kopf, $zeilen, $kontext);
            $zaehl[$art] = $ergebnis['zaehl'];

            // Zahlen allein verraten nicht, ob Namen/Klassen richtig zusammengesetzt
            // wurden – daran scheitert ein Import aus 20 Jahren Datenbestand zuerst.
            $this->debug['beispiele'][$sj->name][$art] = array_slice($zeilen, 0, 3);
        }

        return $zaehl;
    }

    private function quelle(): LinearQuelle
    {
        return $this->quelle ??= new LinearQuelle();
    }
}
