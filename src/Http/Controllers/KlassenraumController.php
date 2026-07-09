<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * „Klassenräume" – der Lehrer-Einstieg: die Klassen des aktiven Schuljahres als
 * Türen. Klick auf eine Tür führt in die Zeugnisliste der Klasse (dort ist für
 * Lehrer automatisch auf die eigenen Fächer vorgefiltert).
 */
class KlassenraumController
{
    public function index()
    {
        $schuljahr = Schuljahr::where('is_active', true)->first();

        if (! $schuljahr) {
            return redirect()
                ->route('module.schulzeugnis.schuljahre.index')
                ->with('error', 'Kein aktives Schuljahr gesetzt – bitte zuerst eines aktiv schalten.');
        }

        // Natürliche Sortierung, damit 2 vor 10 kommt (Namen sind Strings wie "1".."13").
        $klassen = $schuljahr->klassen()
            ->with(['klassenlehrer', 'stufe'])
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->values();

        return view('schulzeugnis::klassenraeume.index', compact('schuljahr', 'klassen'));
    }
}
