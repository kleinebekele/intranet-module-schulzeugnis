<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

/**
 * Gemeinsamer Einstieg für die beiden Umwandel-Werkzeuge: erst das Format
 * wählen (Zeugnisse oder Fachzeugnisse), dann die PDF hochladen.
 *
 * Die Umwandlung selbst bleibt in AltZeugnisController bzw.
 * AltFachzeugnisController – diese Seite ist nur die Auswahl davor.
 */
class AltUmwandelnController
{
    public function index()
    {
        return view('schulzeugnis::altumwandeln.index');
    }
}
