<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;

/**
 * Verwaltung der Fächer (feste, jahresübergreifende Liste).
 */
class FachController
{
    public function index()
    {
        $faecher = Fach::orderBy('reihenfolge')->orderBy('name')->get();

        return view('schulzeugnis::faecher.index', compact('faecher'));
    }

    public function create()
    {
        return view('schulzeugnis::faecher.form', ['fach' => new Fach()]);
    }

    public function store(Request $request)
    {
        $fach = Fach::create($this->validated($request));

        Protokoll::log('fach_angelegt', [
            'beschreibung' => "Fach {$fach->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.faecher.index')
            ->with('status', "Fach {$fach->name} angelegt.");
    }

    public function edit(Fach $fach)
    {
        return view('schulzeugnis::faecher.form', compact('fach'));
    }

    public function update(Request $request, Fach $fach)
    {
        $alt = $fach->name;

        $fach->update($this->validated($request));

        Protokoll::log('fach_geaendert', [
            'beschreibung' => 'Fach bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $fach->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.faecher.index')
            ->with('status', "Fach {$fach->name} gespeichert.");
    }

    public function toggle(Fach $fach)
    {
        $fach->update(['aktiv' => ! $fach->aktiv]);

        Protokoll::log($fach->aktiv ? 'fach_reaktiviert' : 'fach_archiviert', [
            'beschreibung' => "Fach {$fach->name} " . ($fach->aktiv ? 'reaktiviert' : 'archiviert'),
        ]);

        return redirect()
            ->route('module.schulzeugnis.faecher.index')
            ->with('status', "Fach {$fach->name} " . ($fach->aktiv ? 'reaktiviert.' : 'archiviert.'));
    }

    public function destroy(Fach $fach)
    {
        // Hartes Löschen nur, wenn das Fach nirgends verwendet wurde – sonst archivieren.
        $verwendet = DB::table('zeugnis_lehrauftraege')->where('fach_id', $fach->id)->exists()
            || DB::table('zeugnis_abschnitte')->where('fach_id', $fach->id)->exists();

        if ($verwendet) {
            return redirect()
                ->route('module.schulzeugnis.faecher.index')
                ->with('error', "{$fach->name} wurde bereits verwendet – bitte archivieren statt löschen.");
        }

        $name = $fach->name;

        Protokoll::log('fach_geloescht', [
            'beschreibung' => "Fach {$name} gelöscht",
        ]);

        $fach->delete();

        return redirect()
            ->route('module.schulzeugnis.faecher.index')
            ->with('status', "Fach {$name} gelöscht.");
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'kuerzel'     => ['nullable', 'string', 'max:50'],
            'reihenfolge' => ['nullable', 'integer', 'min:0'],
            'aktiv'       => ['nullable', 'boolean'],
        ]);

        $data['reihenfolge'] = $request->integer('reihenfolge');
        $data['aktiv']       = $request->boolean('aktiv');

        return $data;
    }
}
