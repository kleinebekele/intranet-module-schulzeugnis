<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Stufe;

/**
 * Verwaltung der Schulstufen (feste, jahresübergreifende Liste mit Türfarbe).
 */
class StufeController
{
    public function index()
    {
        $stufen = Stufe::withCount('klassen')
            ->orderBy('reihenfolge')
            ->orderBy('name')
            ->get();

        return view('schulzeugnis::stufen.index', compact('stufen'));
    }

    public function create()
    {
        return view('schulzeugnis::stufen.form', ['stufe' => new Stufe(['farbe' => '#6b7280'])]);
    }

    public function store(Request $request)
    {
        $stufe = Stufe::create($this->validated($request));

        Protokoll::log('stufe_angelegt', [
            'beschreibung' => "Schulstufe {$stufe->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.stufen.index')
            ->with('status', "Schulstufe {$stufe->name} angelegt.");
    }

    public function edit(Stufe $stufe)
    {
        return view('schulzeugnis::stufen.form', compact('stufe'));
    }

    public function update(Request $request, Stufe $stufe)
    {
        $alt = $stufe->name;

        $stufe->update($this->validated($request));

        Protokoll::log('stufe_geaendert', [
            'beschreibung' => 'Schulstufe bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $stufe->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.stufen.index')
            ->with('status', "Schulstufe {$stufe->name} gespeichert.");
    }

    public function destroy(Stufe $stufe)
    {
        // Wird die Stufe noch von Klassen genutzt, nicht löschen – sonst verlieren
        // diese ihre Türfarbe unbemerkt. Erst Klassen umhängen.
        $klassenCount = $stufe->klassen()->count();

        if ($klassenCount > 0) {
            return redirect()
                ->route('module.schulzeugnis.stufen.index')
                ->with('error', "{$stufe->name} kann nicht gelöscht werden – {$klassenCount} Klasse(n) sind ihr noch zugeordnet.");
        }

        $name = $stufe->name;

        Protokoll::log('stufe_geloescht', [
            'beschreibung' => "Schulstufe {$name} gelöscht",
        ]);

        $stufe->delete();

        return redirect()
            ->route('module.schulzeugnis.stufen.index')
            ->with('status', "Schulstufe {$name} gelöscht.");
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'farbe'       => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'reihenfolge' => ['nullable', 'integer', 'min:0'],
            'von_klasse'  => ['nullable', 'integer', 'min:1', 'max:13'],
            'bis_klasse'  => ['nullable', 'integer', 'min:1', 'max:13'],
        ], [], [
            'von_klasse' => 'Klassenstufe von',
            'bis_klasse' => 'Klassenstufe bis',
        ]);

        $data['farbe']       = strtolower($data['farbe']);
        $data['reihenfolge'] = $request->integer('reihenfolge');

        // Klassenstufen-Bereich: leer bleibt leer; von/bis ggf. sortieren.
        $von = $request->filled('von_klasse') ? (int) $request->input('von_klasse') : null;
        $bis = $request->filled('bis_klasse') ? (int) $request->input('bis_klasse') : null;
        if ($von !== null && $bis !== null && $von > $bis) {
            [$von, $bis] = [$bis, $von];
        }
        $data['von_klasse'] = $von;
        $data['bis_klasse'] = $bis;

        return $data;
    }
}
