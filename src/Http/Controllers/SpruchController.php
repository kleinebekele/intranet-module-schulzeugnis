<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Spruch;

/**
 * Verwaltung des Zeugnisspruch-Katalogs (jahresübergreifende Liste). Der Klassenlehrer
 * wählt daraus einen Spruch je Schüler aus und kann ihn danach frei bearbeiten.
 */
class SpruchController
{
    public function index()
    {
        $sprueche = Spruch::orderBy('reihenfolge')->orderBy('id')->get();

        return view('schulzeugnis::sprueche.index', compact('sprueche'));
    }

    public function create()
    {
        return view('schulzeugnis::sprueche.form', ['spruch' => new Spruch(['aktiv' => true])]);
    }

    public function store(Request $request)
    {
        $spruch = Spruch::create($this->validated($request));

        Protokoll::log('spruch_angelegt', [
            'beschreibung' => 'Zeugnisspruch angelegt' . ($spruch->titel ? ": {$spruch->titel}" : ''),
        ]);

        return redirect()
            ->route('module.schulzeugnis.sprueche.index')
            ->with('status', 'Zeugnisspruch angelegt.');
    }

    public function edit(Spruch $spruch)
    {
        return view('schulzeugnis::sprueche.form', compact('spruch'));
    }

    public function update(Request $request, Spruch $spruch)
    {
        $spruch->update($this->validated($request));

        Protokoll::log('spruch_geaendert', [
            'beschreibung' => 'Zeugnisspruch bearbeitet' . ($spruch->titel ? ": {$spruch->titel}" : ''),
        ]);

        return redirect()
            ->route('module.schulzeugnis.sprueche.index')
            ->with('status', 'Zeugnisspruch gespeichert.');
    }

    public function toggle(Spruch $spruch)
    {
        $spruch->update(['aktiv' => ! $spruch->aktiv]);

        Protokoll::log($spruch->aktiv ? 'spruch_reaktiviert' : 'spruch_archiviert', [
            'beschreibung' => 'Zeugnisspruch ' . ($spruch->aktiv ? 'reaktiviert' : 'deaktiviert'),
        ]);

        return redirect()
            ->route('module.schulzeugnis.sprueche.index')
            ->with('status', 'Zeugnisspruch ' . ($spruch->aktiv ? 'reaktiviert.' : 'deaktiviert.'));
    }

    public function destroy(Spruch $spruch)
    {
        Protokoll::log('spruch_geloescht', [
            'beschreibung' => 'Zeugnisspruch gelöscht' . ($spruch->titel ? ": {$spruch->titel}" : ''),
        ]);

        $spruch->delete();

        return redirect()
            ->route('module.schulzeugnis.sprueche.index')
            ->with('status', 'Zeugnisspruch gelöscht.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'titel'       => ['nullable', 'string', 'max:255'],
            'text'        => ['required', 'string'],
            'reihenfolge' => ['nullable', 'integer', 'min:0'],
            'aktiv'       => ['nullable', 'boolean'],
        ]);

        $data['reihenfolge'] = $request->integer('reihenfolge');
        $data['aktiv']       = $request->boolean('aktiv');

        return $data;
    }
}
