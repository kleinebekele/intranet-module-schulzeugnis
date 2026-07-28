<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Schulzeugnis\Http\Controllers\AltFachzeugnisController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\AltUmwandelnController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\AltZeugnisController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\FachController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\FormatController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\ImportController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\KlasseController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\KlassenraumController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\LehrauftragController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\LehrerController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\SchuelerController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\SchuljahrController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\SpruchController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\StufeController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\TodoController;
use Intranet\Modules\Schulzeugnis\Http\Controllers\ZeugnisController;

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
        // Keine eigene Start-/Übersichtsseite: Wohin ein Klick auf das Modul führt,
        // bestimmt der Core (erster für den Benutzer sichtbarer Menüpunkt).

        // Klassenräume – Lehrer-Einstieg: Klassen des aktiven Schuljahres als Türen.
        // Zugleich das Dach für die Beurteilungen (Zeugnisse/Abschnitte/Klassentexte):
        // Deren Routen tragen bewusst das Namenspräfix `klassenraeume.*`, damit die
        // Zugriffssteuerung (EnsureModuleAccess) sie diesem Menüpunkt zuordnet – nur
        // wer „Klassenräume" sehen darf (z. B. Zeugnismoderator), kommt an die
        // Beurteilungen. Läge das unter „Klassen", wären es technische Endpunkte ohne
        // eigenen Menüpunkt und damit für jeden mit Modulzugang erreichbar.
        Route::get('klassenraeume', [KlassenraumController::class, 'index'])->name('klassenraeume.index');

        // Beurteilungen einer Klasse (Zeugnisliste) + klassenweiter Text je Fach.
        Route::get('klassenraeume/{klasse}/zeugnisse', [ZeugnisController::class, 'index'])->name('klassenraeume.zeugnisse.index');
        Route::get('klassenraeume/{klasse}/klassentext/{fach}', [ZeugnisController::class, 'klassentextEdit'])->name('klassenraeume.klassentexte.edit');
        Route::put('klassenraeume/{klasse}/klassentext/{fach}', [ZeugnisController::class, 'klassentextUpdate'])->name('klassenraeume.klassentexte.update');
        Route::post('klassenraeume/{klasse}/klassentext/{fach}/wiederherstellen', [ZeugnisController::class, 'klassentextWiederherstellen'])->name('klassenraeume.klassentexte.wiederherstellen');
        Route::post('klassenraeume/{klasse}/klassentext/{fach}/ablehnen', [ZeugnisController::class, 'klassentextAblehnen'])->name('klassenraeume.klassentexte.ablehnen');
        Route::post('klassenraeume/{klasse}/schueler/{schueler}/zeugnis', [ZeugnisController::class, 'store'])->name('klassenraeume.zeugnisse.store');

        // Sammel-Ausgabe: alle Zeugnisse eines Typs (fach|haupt) einer Klasse in einer Datei.
        Route::get('klassenraeume/{klasse}/sammel/{typ}/vorschau', [ZeugnisController::class, 'sammelVorschau'])->name('klassenraeume.sammel.vorschau');
        Route::get('klassenraeume/{klasse}/sammel/{typ}/pdf', [ZeugnisController::class, 'sammelPdf'])->name('klassenraeume.sammel.pdf');

        // Einzelnes Zeugnis eines Schülers.
        Route::get('klassenraeume/zeugnis/{zeugnis}/bearbeiten', [ZeugnisController::class, 'edit'])->name('klassenraeume.zeugnisse.edit');
        Route::get('klassenraeume/zeugnis/{zeugnis}/vorschau', [ZeugnisController::class, 'vorschau'])->name('klassenraeume.zeugnisse.vorschau');
        Route::get('klassenraeume/zeugnis/{zeugnis}/pdf', [ZeugnisController::class, 'pdf'])->name('klassenraeume.zeugnisse.pdf');
        Route::put('klassenraeume/zeugnis/{zeugnis}', [ZeugnisController::class, 'update'])->name('klassenraeume.zeugnisse.update');
        Route::post('klassenraeume/zeugnis/{zeugnis}/abschliessen', [ZeugnisController::class, 'abschliessen'])->name('klassenraeume.zeugnisse.abschliessen');
        Route::post('klassenraeume/zeugnis/{zeugnis}/wieder-oeffnen', [ZeugnisController::class, 'wiederOeffnen'])->name('klassenraeume.zeugnisse.wiederoeffnen');

        // Einzelner Abschnitt (Fachtext/Haupttext/Note) mit Änderungsverlauf.
        Route::get('klassenraeume/abschnitt/{abschnitt}/bearbeiten', [ZeugnisController::class, 'abschnittEdit'])->name('klassenraeume.abschnitte.edit');
        Route::put('klassenraeume/abschnitt/{abschnitt}', [ZeugnisController::class, 'abschnittUpdate'])->name('klassenraeume.abschnitte.update');
        Route::post('klassenraeume/abschnitt/{abschnitt}/wiederherstellen', [ZeugnisController::class, 'abschnittWiederherstellen'])->name('klassenraeume.abschnitte.wiederherstellen');
        Route::post('klassenraeume/abschnitt/{abschnitt}/ablehnen', [ZeugnisController::class, 'abschnittAblehnen'])->name('klassenraeume.abschnitte.ablehnen');

        // Meine ToDos – offene Aufgaben der Lehrkraft, gruppiert nach Klasse/Fach.
        Route::get('todo', [TodoController::class, 'index'])->name('todo.index');

        // Stammdaten-Import (CSV) – additive Übernahme aus dem Schulverwaltungsprogramm.
        // Menü-/Gating-Anker ist der paramlose `.index`; vorschau/ausfuehren tragen das
        // Namenspräfix `import.*`, damit sie derselben Zugriffssteuerung unterliegen.
        Route::get('import', [ImportController::class, 'index'])->name('import.index');
        Route::post('import/vorschau', [ImportController::class, 'vorschau'])->name('import.vorschau');
        Route::post('import/ausfuehren', [ImportController::class, 'ausfuehren'])->name('import.ausfuehren');

        // Werkzeuge für PDFs aus dem alten Zeugnisprogramm – EIN Einstieg mit
        // Format-Auswahl (Zeugnisse: je 4 A4-Seiten → A3-Broschüre umschießen;
        // Fachzeugnisse: Leerseite bei ungerader Seitenzahl für sauberen Duplexdruck).
        //
        // Namenspräfix `altumwandeln.*` mit dem paramlosen `altumwandeln.index` als
        // Anker: So ordnet EnsureModuleAccess auch die POST- und Download-Routen
        // diesem Menüpunkt zu. Vorher hießen sie `altzeugnisse.form` – ein Name ohne
        // `.index` deckt seine Unterrouten NICHT ab, wodurch Umwandeln und Download
        // nur unter die Modul-Auffangregel fielen (erreichbar für jeden mit Modulzugang).
        Route::get('alt-umwandeln', [AltUmwandelnController::class, 'index'])->name('altumwandeln.index');
        Route::post('alt-umwandeln/zeugnisse', [AltZeugnisController::class, 'umwandeln'])->name('altumwandeln.zeugnisse');
        Route::get('alt-umwandeln/zeugnisse/download/{token}', [AltZeugnisController::class, 'download'])->name('altumwandeln.zeugnisse.download');
        Route::post('alt-umwandeln/fachzeugnisse', [AltFachzeugnisController::class, 'umwandeln'])->name('altumwandeln.fachzeugnisse');
        Route::get('alt-umwandeln/fachzeugnisse/download/{token}', [AltFachzeugnisController::class, 'download'])->name('altumwandeln.fachzeugnisse.download');

        // Schuljahre – Anker des Moduls.
        Route::get('schuljahre', [SchuljahrController::class, 'index'])->name('schuljahre.index');
        Route::get('schuljahre/neu', [SchuljahrController::class, 'create'])->name('schuljahre.create');
        Route::post('schuljahre', [SchuljahrController::class, 'store'])->name('schuljahre.store');
        Route::get('schuljahre/{schuljahr}/bearbeiten', [SchuljahrController::class, 'edit'])->name('schuljahre.edit');
        Route::put('schuljahre/{schuljahr}', [SchuljahrController::class, 'update'])->name('schuljahre.update');
        Route::post('schuljahre/{schuljahr}/aktiv', [SchuljahrController::class, 'activate'])->name('schuljahre.activate');
        Route::delete('schuljahre/{schuljahr}', [SchuljahrController::class, 'destroy'])->name('schuljahre.destroy');

        // Klassen (Administration) – immer im Kontext eines Schuljahres.
        // Menü-/Gating-Anker ist der paramlose `.index` (leitet ins aktive Schuljahr);
        // die jahresbezogene Liste heißt `.jahr`. So deckt der Menüpunkt „Klassen"
        // (route …klassen.index) alle klassen.*-Unterseiten ab und gilt nur für die
        // Rolle, die „Klassen" sehen darf (Zeugnisadmin) – kein Durchgriff mehr.
        Route::get('klassen', [KlasseController::class, 'current'])->name('klassen.index');
        Route::get('schuljahre/{schuljahr}/klassen', [KlasseController::class, 'index'])->name('klassen.jahr');
        Route::get('schuljahre/{schuljahr}/klassen/neu', [KlasseController::class, 'create'])->name('klassen.create');
        Route::post('schuljahre/{schuljahr}/klassen', [KlasseController::class, 'store'])->name('klassen.store');
        Route::get('klassen/{klasse}/bearbeiten', [KlasseController::class, 'edit'])->name('klassen.edit');
        Route::put('klassen/{klasse}', [KlasseController::class, 'update'])->name('klassen.update');
        Route::delete('klassen/{klasse}', [KlasseController::class, 'destroy'])->name('klassen.destroy');

        // Lehraufträge einer Klasse (Fach × Lehrer, Team-Teaching möglich).
        // Namen unter `klassen.*`, damit sie derselben Klassen-Administration unterliegen.
        Route::get('klassen/{klasse}/lehrauftraege', [LehrauftragController::class, 'index'])->name('klassen.lehrauftraege.index');
        Route::post('klassen/{klasse}/lehrauftraege', [LehrauftragController::class, 'store'])->name('klassen.lehrauftraege.store');
        Route::delete('lehrauftraege/{lehrauftrag}', [LehrauftragController::class, 'destroy'])->name('klassen.lehrauftraege.destroy');

        // Schüler (Administration) – je Schuljahr. Anker analog zu Klassen: paramloser
        // `.index` fürs Menü/Gating, jahresbezogene Liste als `.jahr`.
        Route::get('schueler', [SchuelerController::class, 'current'])->name('schueler.index');
        Route::get('schuljahre/{schuljahr}/schueler', [SchuelerController::class, 'index'])->name('schueler.jahr');
        Route::get('schuljahre/{schuljahr}/schueler/neu', [SchuelerController::class, 'create'])->name('schueler.create');
        Route::post('schuljahre/{schuljahr}/schueler', [SchuelerController::class, 'store'])->name('schueler.store');
        Route::get('schueler/{schueler}/bearbeiten', [SchuelerController::class, 'edit'])->name('schueler.edit');
        Route::put('schueler/{schueler}', [SchuelerController::class, 'update'])->name('schueler.update');
        Route::delete('schueler/{schueler}', [SchuelerController::class, 'destroy'])->name('schueler.destroy');

        // Fächer – feste, jahresübergreifende Liste.
        Route::get('faecher', [FachController::class, 'index'])->name('faecher.index');
        Route::get('faecher/neu', [FachController::class, 'create'])->name('faecher.create');
        Route::post('faecher', [FachController::class, 'store'])->name('faecher.store');
        Route::get('faecher/{fach}/bearbeiten', [FachController::class, 'edit'])->name('faecher.edit');
        Route::put('faecher/{fach}', [FachController::class, 'update'])->name('faecher.update');
        Route::post('faecher/{fach}/archivieren', [FachController::class, 'toggle'])->name('faecher.toggle');
        Route::delete('faecher/{fach}', [FachController::class, 'destroy'])->name('faecher.destroy');

        // Zeugnisspruch-Katalog – feste, jahresübergreifende Liste zum Auswählen.
        Route::get('sprueche', [SpruchController::class, 'index'])->name('sprueche.index');
        Route::get('sprueche/neu', [SpruchController::class, 'create'])->name('sprueche.create');
        Route::post('sprueche', [SpruchController::class, 'store'])->name('sprueche.store');
        Route::get('sprueche/{spruch}/bearbeiten', [SpruchController::class, 'edit'])->name('sprueche.edit');
        Route::put('sprueche/{spruch}', [SpruchController::class, 'update'])->name('sprueche.update');
        Route::post('sprueche/{spruch}/archivieren', [SpruchController::class, 'toggle'])->name('sprueche.toggle');
        Route::delete('sprueche/{spruch}', [SpruchController::class, 'destroy'])->name('sprueche.destroy');

        // Schulstufen – feste, jahresübergreifende Liste mit Türfarbe.
        Route::get('stufen', [StufeController::class, 'index'])->name('stufen.index');
        Route::get('stufen/neu', [StufeController::class, 'create'])->name('stufen.create');
        Route::post('stufen', [StufeController::class, 'store'])->name('stufen.store');
        Route::get('stufen/{stufe}/bearbeiten', [StufeController::class, 'edit'])->name('stufen.edit');
        Route::put('stufen/{stufe}', [StufeController::class, 'update'])->name('stufen.update');
        Route::delete('stufen/{stufe}', [StufeController::class, 'destroy'])->name('stufen.destroy');

        // Zeugnisformate – Vorlagen (Text/Noten).
        Route::get('formate', [FormatController::class, 'index'])->name('formate.index');
        Route::get('formate/neu', [FormatController::class, 'create'])->name('formate.create');
        Route::post('formate', [FormatController::class, 'store'])->name('formate.store');
        Route::get('formate/{format}/bearbeiten', [FormatController::class, 'edit'])->name('formate.edit');
        Route::get('formate/{format}/vorschau', [FormatController::class, 'vorschau'])->name('formate.vorschau');
        Route::get('formate/{format}/pdf', [FormatController::class, 'pdf'])->name('formate.pdf');
        Route::get('formate/{format}/designer', [FormatController::class, 'designer'])->name('formate.designer');
        Route::put('formate/{format}/layout', [FormatController::class, 'saveLayout'])->name('formate.layout');
        Route::post('formate/{format}/bild', [FormatController::class, 'uploadBild'])->name('formate.bild');
        Route::put('formate/{format}', [FormatController::class, 'update'])->name('formate.update');
        Route::post('formate/{format}/archivieren', [FormatController::class, 'toggle'])->name('formate.toggle');
        Route::post('formate/{format}/duplizieren', [FormatController::class, 'duplicate'])->name('formate.duplicate');
        Route::delete('formate/{format}', [FormatController::class, 'destroy'])->name('formate.destroy');

        // Beispiel-Zeugnistexte für die Layout-Vorschau (modulweit, frei pflegbar).
        Route::put('beispieltexte', [FormatController::class, 'saveTextproben'])->name('beispieltexte.save');
        Route::delete('beispieltexte', [FormatController::class, 'resetTextproben'])->name('beispieltexte.reset');

        // Lehrer (Administration) – je Schuljahr (Verknüpfung zum Core-Konto über
        // core_user_id, kein FK). Anker analog zu Klassen: paramloser `.index`,
        // jahresbezogene Liste als `.jahr`.
        Route::get('lehrer', [LehrerController::class, 'current'])->name('lehrer.index');
        Route::get('schuljahre/{schuljahr}/lehrer', [LehrerController::class, 'index'])->name('lehrer.jahr');
        Route::get('schuljahre/{schuljahr}/lehrer/neu', [LehrerController::class, 'create'])->name('lehrer.create');
        Route::post('schuljahre/{schuljahr}/lehrer', [LehrerController::class, 'store'])->name('lehrer.store');
        Route::get('lehrer/{lehrer}/bearbeiten', [LehrerController::class, 'edit'])->name('lehrer.edit');
        Route::put('lehrer/{lehrer}', [LehrerController::class, 'update'])->name('lehrer.update');
        Route::delete('lehrer/{lehrer}', [LehrerController::class, 'destroy'])->name('lehrer.destroy');
    });
