<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Verwaltung der Klassen – immer im Kontext eines Schuljahres (die URL trägt es).
 */
class KlasseController
{
    public function index(Schuljahr $schuljahr)
    {
        $klassen = $schuljahr->klassen()
            ->with(['standardFormat', 'klassenlehrer'])
            ->withCount('lehrauftraege')
            ->orderBy('name')
            ->get();

        return view('schulzeugnis::klassen.index', compact('schuljahr', 'klassen'));
    }

    public function create(Schuljahr $schuljahr)
    {
        return view('schulzeugnis::klassen.form', [
            'schuljahr' => $schuljahr,
            'klasse'    => new Klasse(),
            'formate'   => $this->formatOptions(),
            'lehrer'    => $this->lehrerOptions($schuljahr),
        ]);
    }

    public function store(Request $request, Schuljahr $schuljahr)
    {
        $data = $this->validated($request, $schuljahr->id);

        $klasse = $schuljahr->klassen()->create($data);

        Protokoll::log('klasse_angelegt', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Klasse {$klasse->name} in {$schuljahr->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.klassen.index', $schuljahr)
            ->with('status', "Klasse {$klasse->name} angelegt.");
    }

    public function edit(Klasse $klasse)
    {
        return view('schulzeugnis::klassen.form', [
            'schuljahr' => $klasse->schuljahr,
            'klasse'    => $klasse,
            'formate'   => $this->formatOptions($klasse->standard_format_id),
            'lehrer'    => $this->lehrerOptions($klasse->schuljahr),
        ]);
    }

    public function update(Request $request, Klasse $klasse)
    {
        $data = $this->validated($request, $klasse->schuljahr_id);
        $alt  = $klasse->name;

        $klasse->update($data);

        Protokoll::log('klasse_geaendert', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'beschreibung' => 'Klasse bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $klasse->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.klassen.index', $klasse->schuljahr_id)
            ->with('status', "Klasse {$klasse->name} gespeichert.");
    }

    public function destroy(Klasse $klasse)
    {
        // Schutz: eine Klasse mit bereits zugeordneten Schülern oder Lehraufträgen
        // wird nicht gelöscht, damit keine Historie verschwindet.
        $hatSchueler     = DB::table('zeugnis_schuljahr_schueler')->where('klasse_id', $klasse->id)->exists();
        $hatLehrauftrag  = DB::table('zeugnis_lehrauftraege')->where('klasse_id', $klasse->id)->exists();

        if ($hatSchueler || $hatLehrauftrag) {
            return redirect()
                ->route('module.schulzeugnis.klassen.index', $klasse->schuljahr_id)
                ->with('error', "{$klasse->name} kann nicht gelöscht werden – es hängen bereits Schüler oder Lehraufträge daran.");
        }

        $name         = $klasse->name;
        $schuljahrId  = $klasse->schuljahr_id;

        Protokoll::log('klasse_geloescht', [
            'schuljahr_id' => $schuljahrId,
            'beschreibung' => "Klasse {$name} gelöscht",
        ]);

        $klasse->delete();

        return redirect()
            ->route('module.schulzeugnis.klassen.index', $schuljahrId)
            ->with('status', "Klasse {$name} gelöscht.");
    }

    /**
     * Menü-Sprung "Klassen": ins aktive Schuljahr, sonst zur Schuljahr-Liste.
     */
    public function current()
    {
        $aktiv = Schuljahr::where('is_active', true)->first();

        if ($aktiv) {
            return redirect()->route('module.schulzeugnis.klassen.index', $aktiv);
        }

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('error', 'Kein aktives Schuljahr gesetzt – bitte zuerst eines aktiv schalten.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, int $schuljahrId): array
    {
        return $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'standard_format_id' => ['nullable', 'integer', Rule::exists('zeugnis_formate', 'id')],
            'klassenlehrer_id'   => ['nullable', 'integer', Rule::exists('zeugnis_schuljahr_lehrer', 'id')->where('schuljahr_id', $schuljahrId)],
        ]);
    }

    /** Lehrer des Schuljahres für die Klassenlehrer-Auswahl. */
    private function lehrerOptions(Schuljahr $schuljahr)
    {
        return Lehrer::where('schuljahr_id', $schuljahr->id)
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
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
