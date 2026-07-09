<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schueler;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Verwaltung der Schüler – immer im Kontext eines Schuljahres (die URL trägt es).
 * Import je Schuljahr (additiv) kommt später; hier die manuelle Pflege.
 */
class SchuelerController
{
    public function index(Schuljahr $schuljahr)
    {
        $schueler = $schuljahr->schueler()
            ->with(['klasse', 'formatOverride'])
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('schulzeugnis::schueler.index', compact('schuljahr', 'schueler'));
    }

    public function create(Schuljahr $schuljahr)
    {
        return view('schulzeugnis::schueler.form', [
            'schuljahr' => $schuljahr,
            'schueler'  => new Schueler(),
            'klassen'   => $this->klassenOptions($schuljahr),
            'formate'   => $this->formatOptions(),
        ]);
    }

    public function store(Request $request, Schuljahr $schuljahr)
    {
        $data = $this->validated($request, $schuljahr);

        $schueler = $schuljahr->schueler()->create($data);

        Protokoll::log('schueler_angelegt', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Schüler {$schueler->fullName()} in {$schuljahr->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.schueler.jahr', $schuljahr)
            ->with('status', "Schüler {$schueler->fullName()} angelegt.");
    }

    public function edit(Schueler $schueler)
    {
        return view('schulzeugnis::schueler.form', [
            'schuljahr' => $schueler->schuljahr,
            'schueler'  => $schueler,
            'klassen'   => $this->klassenOptions($schueler->schuljahr),
            'formate'   => $this->formatOptions($schueler->format_override_id),
        ]);
    }

    public function update(Request $request, Schueler $schueler)
    {
        $data = $this->validated($request, $schueler->schuljahr);
        $alt  = $schueler->fullName();

        $schueler->update($data);

        Protokoll::log('schueler_geaendert', [
            'schuljahr_id' => $schueler->schuljahr_id,
            'beschreibung' => 'Schüler bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $schueler->fullName(),
        ]);

        return redirect()
            ->route('module.schulzeugnis.schueler.jahr', $schueler->schuljahr_id)
            ->with('status', "Schüler {$schueler->fullName()} gespeichert.");
    }

    public function destroy(Schueler $schueler)
    {
        // Schutz: ein Schüler mit bereits erstelltem Zeugnis wird nicht gelöscht,
        // damit keine Historie verschwindet.
        if (DB::table('zeugnisse')->where('schueler_id', $schueler->id)->exists()) {
            return redirect()
                ->route('module.schulzeugnis.schueler.jahr', $schueler->schuljahr_id)
                ->with('error', "{$schueler->fullName()} kann nicht gelöscht werden – es existiert bereits ein Zeugnis.");
        }

        $name = $schueler->fullName();
        $sjId = $schueler->schuljahr_id;

        Protokoll::log('schueler_geloescht', [
            'schuljahr_id' => $sjId,
            'beschreibung' => "Schüler {$name} gelöscht",
        ]);

        $schueler->delete();

        return redirect()
            ->route('module.schulzeugnis.schueler.jahr', $sjId)
            ->with('status', "Schüler {$name} gelöscht.");
    }

    /** Menü-Sprung "Schüler": ins aktive Schuljahr, sonst zur Schuljahr-Liste. */
    public function current()
    {
        $aktiv = Schuljahr::where('is_active', true)->first();

        if ($aktiv) {
            return redirect()->route('module.schulzeugnis.schueler.jahr', $aktiv);
        }

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('error', 'Kein aktives Schuljahr gesetzt – bitte zuerst eines aktiv schalten.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, Schuljahr $schuljahr): array
    {
        return $request->validate([
            'vorname'            => ['required', 'string', 'max:255'],
            'nachname'           => ['required', 'string', 'max:255'],
            'geburtsdatum'       => ['nullable', 'date'],
            'geburtsort'         => ['nullable', 'string', 'max:255'],
            'geschlecht'         => ['nullable', Rule::in(['w', 'm', 'd'])],
            'quell_id'           => ['nullable', 'string', 'max:255'],
            'klasse_id'          => ['nullable', 'integer', Rule::exists('zeugnis_klassen', 'id')->where('schuljahr_id', $schuljahr->id)],
            'format_override_id' => ['nullable', 'integer', Rule::exists('zeugnis_formate', 'id')],
        ]);
    }

    private function klassenOptions(Schuljahr $schuljahr)
    {
        return $schuljahr->klassen()->orderBy('name')->get();
    }

    /** Auswählbare Formate: aktive plus das aktuell gesetzte (auch wenn archiviert). */
    private function formatOptions(?int $currentId = null)
    {
        return Format::where('aktiv', true)
            ->when($currentId, fn ($q) => $q->orWhere('id', $currentId))
            ->orderBy('name')
            ->get();
    }
}
