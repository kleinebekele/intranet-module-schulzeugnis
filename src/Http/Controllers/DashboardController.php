<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

/**
 * Landing-Route des Moduls. Vorläufig eine Übersichtsseite über die geplanten
 * Bausteine; wird ersetzt, sobald die ersten echten Bereiche gebaut sind.
 */
class DashboardController
{
    public function index()
    {
        return view('schulzeugnis::dashboard.index');
    }
}
