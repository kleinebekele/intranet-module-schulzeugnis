<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Verwaltung der Schuljahre (Anker des Moduls). Anlegen/Bearbeiten manuell –
 * der spätere Jahres-Import setzt hier nur oben drauf.
 */
class SchuljahrController
{
    public function index()
    {
        $schuljahre = Schuljahr::withCount(['klassen', 'lehrer'])->orderByDesc('start_date')->orderByDesc('name')->get();

        return view('schulzeugnis::schuljahre.index', compact('schuljahre'));
    }

    public function create()
    {
        return view('schulzeugnis::schuljahre.form', ['schuljahr' => new Schuljahr()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $schuljahr = Schuljahr::create($data);

        if ($schuljahr->is_active) {
            $this->deactivateOthers($schuljahr->id);
        }

        Protokoll::log('angelegt', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Schuljahr {$schuljahr->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('status', "Schuljahr {$schuljahr->name} angelegt.");
    }

    public function edit(Schuljahr $schuljahr)
    {
        return view('schulzeugnis::schuljahre.form', compact('schuljahr'));
    }

    public function update(Request $request, Schuljahr $schuljahr)
    {
        $data = $this->validated($request);
        $alt  = $schuljahr->name;

        $schuljahr->update($data);

        if ($schuljahr->is_active) {
            $this->deactivateOthers($schuljahr->id);
        }

        Protokoll::log('geaendert', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => 'Schuljahr bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $schuljahr->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('status', "Schuljahr {$schuljahr->name} gespeichert.");
    }

    public function activate(Schuljahr $schuljahr)
    {
        $schuljahr->update(['is_active' => true]);
        $this->deactivateOthers($schuljahr->id);

        Protokoll::log('aktiviert', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Schuljahr {$schuljahr->name} als aktiv gesetzt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('status', "{$schuljahr->name} ist jetzt das aktive Schuljahr.");
    }

    public function destroy(Schuljahr $schuljahr)
    {
        // Schutz: ein Schuljahr, an dem schon Klassen hängen, wird nicht gelöscht –
        // damit keine Historie versehentlich verschwindet.
        if (DB::table('zeugnis_klassen')->where('schuljahr_id', $schuljahr->id)->exists()) {
            return redirect()
                ->route('module.schulzeugnis.schuljahre.index')
                ->with('error', "{$schuljahr->name} kann nicht gelöscht werden – es hängen bereits Klassen daran.");
        }

        $name = $schuljahr->name;

        Protokoll::log('geloescht', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Schuljahr {$name} gelöscht",
        ]);

        $schuljahr->delete();

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('status', "Schuljahr {$name} gelöscht.");
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'ausgabe_datum' => ['nullable', 'date'],
            'eingabe_frist' => ['nullable', 'date', 'before_or_equal:ausgabe_datum'],
            'is_active'     => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function deactivateOthers(int $keepId): void
    {
        Schuljahr::where('id', '!=', $keepId)->update(['is_active' => false]);
    }
}
