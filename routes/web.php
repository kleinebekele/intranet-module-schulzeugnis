<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Schulzeugnis\Http\Controllers\DashboardController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\FachController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\FormatController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\KlasseController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\LehrerController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\SchuljahrController;

/*
 | Routen des Schulzeugnis-Moduls.
 |
 | Konvention (wie bei allen Modulen):
 |  - URL-Präfix:  modules/schulzeugnis
 |  - Namen:       module.schulzeugnis.*
 |  - Middleware:  'web' + 'auth'   (Session, CSRF, nur eingeloggt)
*/
Route::middleware(['web', 'auth'])
    ->prefix('modules/schulzeugnis')
    ->name('module.schulzeugnis.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        // Schuljahre – Anker des Moduls.
        Route::get('schuljahre', [SchuljahrController::class, 'index'])->name('schuljahre.index');
        Route::get('schuljahre/neu', [SchuljahrController::class, 'create'])->name('schuljahre.create');
        Route::post('schuljahre', [SchuljahrController::class, 'store'])->name('schuljahre.store');
        Route::get('schuljahre/{schuljahr}/bearbeiten', [SchuljahrController::class, 'edit'])->name('schuljahre.edit');
        Route::put('schuljahre/{schuljahr}', [SchuljahrController::class, 'update'])->name('schuljahre.update');
        Route::post('schuljahre/{schuljahr}/aktiv', [SchuljahrController::class, 'activate'])->name('schuljahre.activate');
        Route::delete('schuljahre/{schuljahr}', [SchuljahrController::class, 'destroy'])->name('schuljahre.destroy');

        // Klassen – immer im Kontext eines Schuljahres.
        Route::get('klassen', [KlasseController::class, 'current'])->name('klassen.current');
        Route::get('schuljahre/{schuljahr}/klassen', [KlasseController::class, 'index'])->name('klassen.index');
        Route::get('schuljahre/{schuljahr}/klassen/neu', [KlasseController::class, 'create'])->name('klassen.create');
        Route::post('schuljahre/{schuljahr}/klassen', [KlasseController::class, 'store'])->name('klassen.store');
        Route::get('klassen/{klasse}/bearbeiten', [KlasseController::class, 'edit'])->name('klassen.edit');
        Route::put('klassen/{klasse}', [KlasseController::class, 'update'])->name('klassen.update');
        Route::delete('klassen/{klasse}', [KlasseController::class, 'destroy'])->name('klassen.destroy');

        // Fächer – feste, jahresübergreifende Liste.
        Route::get('faecher', [FachController::class, 'index'])->name('faecher.index');
        Route::get('faecher/neu', [FachController::class, 'create'])->name('faecher.create');
        Route::post('faecher', [FachController::class, 'store'])->name('faecher.store');
        Route::get('faecher/{fach}/bearbeiten', [FachController::class, 'edit'])->name('faecher.edit');
        Route::put('faecher/{fach}', [FachController::class, 'update'])->name('faecher.update');
        Route::post('faecher/{fach}/archivieren', [FachController::class, 'toggle'])->name('faecher.toggle');
        Route::delete('faecher/{fach}', [FachController::class, 'destroy'])->name('faecher.destroy');

        // Zeugnisformate – Vorlagen (Text/Noten).
        Route::get('formate', [FormatController::class, 'index'])->name('formate.index');
        Route::get('formate/neu', [FormatController::class, 'create'])->name('formate.create');
        Route::post('formate', [FormatController::class, 'store'])->name('formate.store');
        Route::get('formate/{format}/bearbeiten', [FormatController::class, 'edit'])->name('formate.edit');
        Route::get('formate/{format}/vorschau', [FormatController::class, 'vorschau'])->name('formate.vorschau');
        Route::get('formate/{format}/pdf', [FormatController::class, 'pdf'])->name('formate.pdf');
        Route::get('formate/{format}/designer', [FormatController::class, 'designer'])->name('formate.designer');
        Route::put('formate/{format}/layout', [FormatController::class, 'saveLayout'])->name('formate.layout');
        Route::put('formate/{format}', [FormatController::class, 'update'])->name('formate.update');
        Route::post('formate/{format}/archivieren', [FormatController::class, 'toggle'])->name('formate.toggle');
        Route::delete('formate/{format}', [FormatController::class, 'destroy'])->name('formate.destroy');

        // Lehrer – je Schuljahr (Verknüpfung zum Core-Konto über core_user_id, kein FK).
        Route::get('lehrer', [LehrerController::class, 'current'])->name('lehrer.current');
        Route::get('schuljahre/{schuljahr}/lehrer', [LehrerController::class, 'index'])->name('lehrer.index');
        Route::get('schuljahre/{schuljahr}/lehrer/neu', [LehrerController::class, 'create'])->name('lehrer.create');
        Route::post('schuljahre/{schuljahr}/lehrer', [LehrerController::class, 'store'])->name('lehrer.store');
        Route::get('lehrer/{lehrer}/bearbeiten', [LehrerController::class, 'edit'])->name('lehrer.edit');
        Route::put('lehrer/{lehrer}', [LehrerController::class, 'update'])->name('lehrer.update');
        Route::delete('lehrer/{lehrer}', [LehrerController::class, 'destroy'])->name('lehrer.destroy');
    });
