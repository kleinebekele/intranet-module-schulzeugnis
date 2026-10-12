<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Klassentext;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
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
            ->with(['klassenleitung', 'stufe'])
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->values();

        $meineKlassenIds = $this->meineKlassenIds($schuljahr, $klassen->pluck('id'));

        return view('schulzeugnis::klassenraeume.index', compact('schuljahr', 'klassen', 'meineKlassenIds'));
    }

    /**
     * Klassen, in denen der eingeloggte Nutzer Aufgaben hat: Klassenleitung, Lehrauftrag
     * oder eine offene Korrektur-Anfrage (Schüler- bzw. klassenweiter Text).
     *
     * @return array<int,int>
     */
    private function meineKlassenIds(Schuljahr $schuljahr, \Illuminate\Support\Collection $klassenIds): array
    {
        $meineLehrerIds = Lehrer::where('schuljahr_id', $schuljahr->id)
            ->where('core_user_id', auth()->id())
            ->pluck('id');
        if ($meineLehrerIds->isEmpty()) {
            return [];
        }

        $offen = ['frei_zur_korrektur', 'in_korrektur', 'korrektur_noetig'];
        $korrektor = fn ($q) => $q->whereIn('zeugnis_schuljahr_lehrer.id', $meineLehrerIds);

        return collect()
            ->merge(\Illuminate\Support\Facades\DB::table('zeugnis_klasse_klassenlehrer')
                ->whereIn('klasse_id', $klassenIds)->whereIn('lehrer_id', $meineLehrerIds)->pluck('klasse_id'))
            ->merge(Lehrauftrag::whereIn('klasse_id', $klassenIds)->whereIn('lehrer_id', $meineLehrerIds)->pluck('klasse_id'))
            ->merge(Klassentext::whereIn('klasse_id', $klassenIds)->whereIn('status', $offen)
                ->whereHas('korrektoren', $korrektor)->pluck('klasse_id'))
            ->merge(Abschnitt::whereIn('status', $offen)->whereHas('korrektoren', $korrektor)
                ->with('zeugnis.schueler:id,klasse_id')->get()
                ->map(fn (Abschnitt $a) => $a->zeugnis?->schueler?->klasse_id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
