<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Lehraufträge einer Klasse verwalten (Fach × Lehrer). Mehrere Lehrer je Fach
 * sind erlaubt (Team-Teaching). Die Klasse trägt schon das Schuljahr.
 */
class LehrauftragController
{
    public function index(Klasse $klasse)
    {
        $klasse->load('schuljahr', 'klassenlehrer');

        $lehrauftraege = $klasse->lehrauftraege()
            ->with(['fach', 'lehrer'])
            ->get()
            ->sortBy(fn ($la) => sprintf('%05d|%s|%s', $la->fach->reihenfolge ?? 0, $la->fach->name ?? '', $la->lehrer->nachname ?? ''))
            ->values();

        return view('schulzeugnis::lehrauftraege.index', [
            'klasse'        => $klasse,
            'lehrauftraege' => $lehrauftraege,
            'faecher'       => Fach::where('aktiv', true)->orderBy('reihenfolge')->orderBy('name')->get(),
            'lehrerListe'   => $klasse->schuljahr->lehrer()->orderBy('nachname')->orderBy('vorname')->get(),
        ]);
    }

    public function store(Request $request, Klasse $klasse)
    {
        $data = $request->validate([
            'fach_id'   => ['required', 'integer', Rule::exists('zeugnis_faecher', 'id')],
            'lehrer_id' => ['required', 'integer', Rule::exists('zeugnis_schuljahr_lehrer', 'id')->where('schuljahr_id', $klasse->schuljahr_id)],
        ]);

        $doppelt = $klasse->lehrauftraege()
            ->where('fach_id', $data['fach_id'])
            ->where('lehrer_id', $data['lehrer_id'])
            ->exists();

        if ($doppelt) {
            return redirect()
                ->route('module.schulzeugnis.klassen.lehrauftraege.index', $klasse)
                ->with('error', 'Dieser Lehrauftrag besteht bereits.');
        }

        $lehrauftrag = $klasse->lehrauftraege()->create($data);
        $lehrauftrag->load('fach', 'lehrer');

        Protokoll::log('lehrauftrag_angelegt', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'beschreibung' => "Lehrauftrag {$lehrauftrag->fach?->name} · {$lehrauftrag->lehrer?->fullName()} in {$klasse->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.klassen.lehrauftraege.index', $klasse)
            ->with('status', 'Lehrauftrag hinzugefügt.');
    }

    public function destroy(Lehrauftrag $lehrauftrag)
    {
        $lehrauftrag->load('klasse', 'fach', 'lehrer');
        $klasseId = $lehrauftrag->klasse_id;

        Protokoll::log('lehrauftrag_geloescht', [
            'schuljahr_id' => $lehrauftrag->klasse?->schuljahr_id,
            'beschreibung' => "Lehrauftrag {$lehrauftrag->fach?->name} · {$lehrauftrag->lehrer?->fullName()} in {$lehrauftrag->klasse?->name} entfernt",
        ]);

        $lehrauftrag->delete();

        return redirect()
            ->route('module.schulzeugnis.klassen.lehrauftraege.index', $klasseId)
            ->with('status', 'Lehrauftrag entfernt.');
    }
}
