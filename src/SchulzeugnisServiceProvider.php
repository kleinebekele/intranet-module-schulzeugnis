<?php

namespace Intranet\Modules\Schulzeugnis;

use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;

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
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('schulzeugnis', 'Schulzeugnis', icon: 'book')
            ->item('start', 'Übersicht', 'module.schulzeugnis.index', icon: 'book')
            ->item('schuljahre', 'Schuljahre', 'module.schulzeugnis.schuljahre.index', icon: 'calendar')
            ->item('klassen', 'Klassen', 'module.schulzeugnis.klassen.current', icon: 'users')
            ->item('lehrer', 'Lehrer', 'module.schulzeugnis.lehrer.current', icon: 'user')
            ->item('faecher', 'Fächer', 'module.schulzeugnis.faecher.index', icon: 'list')
            ->item('formate', 'Zeugnisformate', 'module.schulzeugnis.formate.index', icon: 'category');
    }
}
