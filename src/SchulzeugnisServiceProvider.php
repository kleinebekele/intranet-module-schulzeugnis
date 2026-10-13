<?php

namespace Intranet\Modules\Schulzeugnis;

use App\Ekkon\Support\TaskRegistry;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use App\Modules\Support\Zugriffsstufe;
use Illuminate\Console\Scheduling\Schedule;
use Intranet\Modules\Schulzeugnis\Console\Commands\LehrerKontenVerknuepfen;
use Intranet\Modules\Schulzeugnis\Console\Commands\SeedDemo;
use Intranet\Modules\Schulzeugnis\Http\Middleware\AlsLehrer;
use Intranet\Modules\Schulzeugnis\Support\LehrerKontenAbgleich;

/**
 * Anmelde-Klasse des Schulzeugnis-Moduls.
 *
 * Routen, Views und Migrationen lädt die Basisklasse automatisch anhand der
 * Ordnerstruktur – hier beschreiben wir nur das Manifest (Schlüssel, Name,
 * Icon und die Menü-Unterpunkte).
 *
 * Bewusst KEIN boot()-Eingriff am Core-User (kein resolveRelationUsing o. ä.):
 * Das Modul ist eine Insel und koppelt sich nur über lose ID-Werte an den Core.
 */
class SchulzeugnisServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Der nächtliche Import aus Linear (Task Linear/ZeugnisImport) – bis 2026-09
        // im Modul ekkon-linear. Ordner- und Klassenname sind geblieben, damit der
        // Task-Key und mit ihm Pause, Historie und Benachrichtigungs-Routen bleiben.
        $this->app->singletonIf(TaskRegistry::class);

        $this->app->make(TaskRegistry::class)->addSource(
            $this->moduleBasePath().'/src/Tasks',
            __NAMESPACE__.'\\Tasks',
            'do1emu/module-schulzeugnis',
            'schulzeugnis',
        );
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedDemo::class,
                LehrerKontenVerknuepfen::class,
            ]);

            // Täglicher Abgleich: importierte Lehrer ohne Konto per E-Mail mit ihrem
            // Intranet-Benutzer verknüpfen, sobald dieser existiert. Modul-lokal
            // angemeldet (Insel-Prinzip, kein Eingriff in den Core-Scheduler).
            // Voraussetzung am Server: ein Cron, der minütlich `artisan schedule:run` ruft.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('schulzeugnis:lehrer-verknuepfen')
                    ->dailyAt('03:00')
                    ->timezone('Europe/Berlin')
                    ->withoutOverlapping();
            });
        }

        // „Als Lehrer nutzen": roter Balken unter der Kopfleiste, solange aktiv – nur auf Zeugnisseiten.
        \App\Support\Kopfleiste::registrieren('schulzeugnis-als-lehrer', function (User $user) {
            if (! request()->routeIs('module.schulzeugnis.*')) {
                return null;
            }
            $person = AlsLehrer::person();

            return $person ? view('schulzeugnis::als-lehrer-banner', ['person' => $person]) : null;
        });
        \App\Support\Audit::benennen([
            'schulzeugnis.als_lehrer_start' => 'Zeugnis: als Lehrkraft nutzen – Beginn',
            'schulzeugnis.als_lehrer_ende' => 'Zeugnis: als Lehrkraft nutzen – Ende',
            'schulzeugnis.als_lehrer_aenderung' => 'Zeugnis: Änderung als andere Lehrkraft',
        ]);

        // Sofort-Verknüpfung (Insel-konform, kein Core-Eingriff): wird ein Intranet-
        // Benutzer angelegt oder seine externe ID gesetzt, verknüpfen wir passende
        // importierte Lehrer ohne Konto direkt – ohne auf den täglichen Abgleich zu
        // warten. Greift auch für den User des Benutzer-Import-Moduls.
        User::created(function (User $user): void {
            LehrerKontenAbgleich::fuerBenutzer($user);
        });
        User::updated(function (User $user): void {
            if ($user->wasChanged('externe_id')) {
                LehrerKontenAbgleich::fuerBenutzer($user);
            }
        });
    }

    public function manifest(): ModuleManifest
    {
        // Die Stammdaten-Pflege bündeln wir optisch unter „Zeugnisverwaltung“ – sie
        // wird selten gebraucht und drängte den täglichen Einstieg (Klassenräume,
        // ToDos) sonst nach unten. Rein Darstellung: jeder Punkt behält seinen
        // eigenen Eintrag, seine Rollen und seine Zugriffsregel.
        $verwaltung = 'Zeugnisverwaltung';

        // Jede Zeile ein eigenes Symbol – bei 11 Unterpunkten sind mehrfach
        // vergebene Icons als Orientierung wertlos.
        return ModuleManifest::make('schulzeugnis', 'Schulzeugnis', icon: 'book')
            ->rolle('zeugnis_admin', 'Zeugnisadmin')
            ->rolle('zeugnis_moderator', 'Zeugnismoderator')
            ->rolle('zeugnis_designer', 'Zeugnisdesigner')
            ->item('klassenraeume', 'Klassenräume', 'module.schulzeugnis.klassenraeume.index', icon: 'door')
            ->item('todo', 'Meine ToDos', 'module.schulzeugnis.todo.index', icon: 'list')
            ->item('schuljahre', 'Schuljahre', 'module.schulzeugnis.schuljahre.index', icon: 'calendar', group: $verwaltung)
            ->item('stufen', 'Schulstufen', 'module.schulzeugnis.stufen.index', icon: 'layers', group: $verwaltung)
            ->item('klassen', 'Klassen', 'module.schulzeugnis.klassen.index', icon: 'users', group: $verwaltung)
            ->item('faecher', 'Fächer', 'module.schulzeugnis.faecher.index', icon: 'book-content', group: $verwaltung)
            ->item('lehrer', 'Lehrer', 'module.schulzeugnis.lehrer.index', icon: 'chalkboard', group: $verwaltung)
            ->item('schueler', 'Schüler', 'module.schulzeugnis.schueler.index', icon: 'user', group: $verwaltung)
            ->item('sprueche', 'Zeugnissprüche', 'module.schulzeugnis.sprueche.index', icon: 'quote', group: $verwaltung)
            ->item('formate', 'Zeugnisformate', 'module.schulzeugnis.formate.index', icon: 'layout', group: $verwaltung)
            // CSV-Import nur, solange keine Linear-Anbindung da ist – sonst kommen die
            // Stammdaten über „Aus Linear importieren" (Seite bleibt per Adresse erreichbar).
            ->item('import', 'Stammdaten-Import', 'module.schulzeugnis.import.index', icon: 'import', group: $verwaltung,
                visibleWhen: fn () => ! app(\Intranet\Modules\Schulzeugnis\Support\Linear\LinearQuelle::class)->verfuegbar())
            ->item('altumwandeln', 'Alte Zeugnisse umwandeln', 'module.schulzeugnis.altumwandeln.index', icon: 'transfer')
            // Zugriffsstufen, wo die Regel aus Anfrageart/Routenname nicht passt
            // (MODULES.md im Core, „Zugriffsstufen").
            ->lesend('import.vorschau', 'altumwandeln.zeugnisse', 'altumwandeln.fachzeugnisse') // prüfen/umwandeln ohne Speichern
            ->stufe(Zugriffsstufe::Verwalten, 'import.ausfuehren', 'formate.duplicate', 'lehrer.als') // legen an / als Lehrkraft
            ->stufe(Zugriffsstufe::Bearbeiten, 'beispieltexte.reset')                            // setzt nur zurück
            // „Aus Linear importieren": schon die Vorschau liest Linear – nur für Verwaltende.
            ->stufe(Zugriffsstufe::Verwalten,
                'faecher.linear.vorschau', 'faecher.linear.ausfuehren',
                'lehrer.linear.vorschau', 'lehrer.linear.ausfuehren',
                'klassen.linear.vorschau', 'klassen.linear.ausfuehren',
                'klassen.lehrauftraege.linear.vorschau', 'klassen.lehrauftraege.linear.ausfuehren',
                'schueler.linear.vorschau', 'schueler.linear.ausfuehren',
                'lehrer.linear.verknuepfen', 'schueler.linear.verknuepfen');
    }
}
