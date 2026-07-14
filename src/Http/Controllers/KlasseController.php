<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Hauptbereich;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;
use Intranet\Modules\Schulzeugnis\Models\Stufe;

/**
 * Verwaltung der Klassen – immer im Kontext eines Schuljahres (die URL trägt es).
 */
class KlasseController
{
    public function index(Schuljahr $schuljahr)
    {
        // Natürliche Sortierung: "1, 2, … 10, 11, 12, 13" statt alphabetisch
        // "1, 10, 11, 12, 13, 2, …". Cross-DB sicher in PHP (strnatcasecmp).
        $klassen = $schuljahr->klassen()
            ->with(['standardFormat', 'klassenlehrer', 'stufe'])
            ->withCount(['lehrauftraege', 'hauptbereiche'])
            ->get()
            ->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))
            ->values();

        return view('schulzeugnis::klassen.index', compact('schuljahr', 'klassen'));
    }

    public function create(Schuljahr $schuljahr)
    {
        return view('schulzeugnis::klassen.form', [
            'schuljahr' => $schuljahr,
            'klasse'    => new Klasse(),
            'formate'   => $this->formatOptions(),
            'lehrer'    => $this->lehrerOptions($schuljahr),
            'stufen'    => $this->stufenOptions(),
            'bereiche'  => collect(),
        ]);
    }

    public function store(Request $request, Schuljahr $schuljahr)
    {
        $data = $this->validated($request, $schuljahr->id);

        $klasse = $schuljahr->klassen()->create($data);
        $this->syncHauptzeugnis($request, $klasse);

        Protokoll::log('klasse_angelegt', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Klasse {$klasse->name} in {$schuljahr->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.klassen.jahr', $schuljahr)
            ->with('status', "Klasse {$klasse->name} angelegt.");
    }

    public function edit(Klasse $klasse)
    {
        return view('schulzeugnis::klassen.form', [
            'schuljahr' => $klasse->schuljahr,
            'klasse'    => $klasse,
            'formate'   => $this->formatOptions($klasse->standard_format_id, $klasse->hauptzeugnis_format_id),
            'lehrer'    => $this->lehrerOptions($klasse->schuljahr),
            'stufen'    => $this->stufenOptions(),
            'bereiche'  => $klasse->hauptbereiche,
        ]);
    }

    public function update(Request $request, Klasse $klasse)
    {
        $data = $this->validated($request, $klasse->schuljahr_id);
        $alt  = $klasse->name;

        $klasse->update($data);
        $this->syncHauptzeugnis($request, $klasse);

        Protokoll::log('klasse_geaendert', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'beschreibung' => 'Klasse bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $klasse->name,
        ]);

        return redirect()
            ->route('module.schulzeugnis.klassen.jahr', $klasse->schuljahr_id)
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
                ->route('module.schulzeugnis.klassen.jahr', $klasse->schuljahr_id)
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
            ->route('module.schulzeugnis.klassen.jahr', $schuljahrId)
            ->with('status', "Klasse {$name} gelöscht.");
    }

    /**
     * Menü-Sprung "Klassen": ins aktive Schuljahr, sonst zur Schuljahr-Liste.
     */
    public function current()
    {
        $aktiv = Schuljahr::where('is_active', true)->first();

        if ($aktiv) {
            return redirect()->route('module.schulzeugnis.klassen.jahr', $aktiv);
        }

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('error', 'Kein aktives Schuljahr gesetzt – bitte zuerst eines aktiv schalten.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, int $schuljahrId): array
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'stufe_id'               => ['nullable', 'integer', Rule::exists('zeugnis_stufen', 'id')],
            'standard_format_id'     => ['nullable', 'integer', Rule::exists('zeugnis_formate', 'id')],
            'hauptzeugnis_format_id' => ['nullable', 'integer', Rule::exists('zeugnis_formate', 'id')],
            'klassenlehrer_id'       => ['nullable', 'integer', Rule::exists('zeugnis_schuljahr_lehrer', 'id')->where('schuljahr_id', $schuljahrId)],
        ]);

        // Checkboxen kommen nur beim Anhaken mit – daher explizit als bool lesen.
        $data['hat_fachzeugnis']   = $request->boolean('hat_fachzeugnis');
        $data['hat_hauptzeugnis']  = $request->boolean('hat_hauptzeugnis');
        $data['hat_zeugnisspruch'] = $request->boolean('hat_zeugnisspruch');
        if (! $data['hat_hauptzeugnis']) {
            $data['hauptzeugnis_format_id'] = null;
        }

        return $data;
    }

    /**
     * Fachbereiche des Hauptzeugnisses aus dem Formular übernehmen (nur wenn aktiv).
     * ID-basiert, damit bestehende Bereiche – und die daran hängenden Schülertexte –
     * erhalten bleiben; "Allgemein" ist Pflicht.
     */
    private function syncHauptzeugnis(Request $request, Klasse $klasse): void
    {
        if (! $klasse->hat_hauptzeugnis) {
            return; // Bereiche bleiben erhalten, falls das Hauptzeugnis später wieder aktiviert wird
        }

        $eingaben = collect($request->input('bereiche', []))
            ->map(fn ($b) => ['id' => $b['id'] ?? null, 'name' => trim((string) ($b['name'] ?? ''))])
            ->filter(fn ($b) => $b['name'] !== '')
            ->values();

        $behalten = [];
        $pos = 0;
        foreach ($eingaben as $b) {
            $row = $b['id'] ? $klasse->hauptbereiche()->find($b['id']) : null;
            if ($row) {
                $row->update(['name' => $b['name'], 'reihenfolge' => $pos]);
            } else {
                $row = $klasse->hauptbereiche()->create(['name' => $b['name'], 'reihenfolge' => $pos]);
            }
            $behalten[] = $row->id;
            $pos++;
        }

        // Entfernte Bereiche löschen – anhängende Schülertexte behalten via nullOnDelete ihren bereich_name.
        $klasse->hauptbereiche()->whereNotIn('id', $behalten ?: [0])->delete();

        // "Allgemein" ist Pflicht – notfalls vorne ergänzen.
        if (! $klasse->hauptbereiche()->where('name', Hauptbereich::STANDARD)->exists()) {
            $klasse->hauptbereiche()->create(['name' => Hauptbereich::STANDARD, 'reihenfolge' => -1]);
        }
    }

    /** Lehrer des Schuljahres für die Klassenlehrer-Auswahl. */
    private function lehrerOptions(Schuljahr $schuljahr)
    {
        return Lehrer::where('schuljahr_id', $schuljahr->id)
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    /** Auswählbare Formate: aktive plus die aktuell gesetzten (auch wenn archiviert). */
    private function formatOptions(?int ...$currentIds)
    {
        $ids = array_filter($currentIds);

        return Format::where('aktiv', true)
            ->when($ids, fn ($q) => $q->orWhereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    /** Schulstufen für die Stufen-Auswahl (nach Reihenfolge sortiert). */
    private function stufenOptions()
    {
        return Stufe::orderBy('reihenfolge')->orderBy('name')->get();
    }
}
