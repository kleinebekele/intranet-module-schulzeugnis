<?php

namespace Intranet\Modules\Schulzeugnis;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Intranet\Modules\Schulzeugnis\Console\Commands\LehrerKontenVerknuepfen;
use Intranet\Modules\Schulzeugnis\Console\Commands\SeedDemo;
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
        return ModuleManifest::make('schulzeugnis', 'Schulzeugnis', icon: 'book')
            ->item('klassenraeume', 'Klassenräume', 'module.schulzeugnis.klassenraeume.index', icon: 'home')
            ->item('todo', 'Meine ToDos', 'module.schulzeugnis.todo.index', icon: 'list')
            ->item('schuljahre', 'Schuljahre', 'module.schulzeugnis.schuljahre.index', icon: 'calendar')
            ->item('klassen', 'Klassen', 'module.schulzeugnis.klassen.index', icon: 'users')
            ->item('stufen', 'Schulstufen', 'module.schulzeugnis.stufen.index', icon: 'category')
            ->item('schueler', 'Schüler', 'module.schulzeugnis.schueler.index', icon: 'user')
            ->item('lehrer', 'Lehrer', 'module.schulzeugnis.lehrer.index', icon: 'user')
            ->item('faecher', 'Fächer', 'module.schulzeugnis.faecher.index', icon: 'list')
            ->item('sprueche', 'Zeugnissprüche', 'module.schulzeugnis.sprueche.index', icon: 'book')
            ->item('formate', 'Zeugnisformate', 'module.schulzeugnis.formate.index', icon: 'category')
            ->item('import', 'Stammdaten-Import', 'module.schulzeugnis.import.index', icon: 'category')
            ->item('altzeugnisse', 'Alte Zeugnisse umwandeln', 'module.schulzeugnis.altzeugnisse.form', icon: 'category')
            ->item('altfachzeugnisse', 'Alte Fachzeugnisse umwandeln', 'module.schulzeugnis.altfachzeugnisse.form', icon: 'category');
    }
}
