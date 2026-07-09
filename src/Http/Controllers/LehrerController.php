<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schuljahr;

/**
 * Verwaltung der Lehrer – je Schuljahr. Die Verknüpfung zum Core-Benutzer läuft
 * über core_user_id (loser Wert, kein FK): existiert das Konto, darf der Lehrer
 * im Modul zugreifen; wird es gelöscht, bleibt der Datensatz (Name) erhalten.
 */
class LehrerController
{
    public function index(Schuljahr $schuljahr)
    {
        $lehrer = $schuljahr->lehrer()->orderBy('nachname')->orderBy('vorname')->get();

        // Core-Benutzer zu den gesetzten IDs nachschlagen (Lesezugriff, kein FK) –
        // so sehen wir, welche Verknüpfung noch aufgeht und welche ins Leere zeigt.
        $benutzer = User::whereIn('id', $lehrer->pluck('core_user_id')->filter())->get()->keyBy('id');

        return view('schulzeugnis::lehrer.index', compact('schuljahr', 'lehrer', 'benutzer'));
    }

    public function create(Schuljahr $schuljahr)
    {
        return view('schulzeugnis::lehrer.form', [
            'schuljahr' => $schuljahr,
            'lehrer'    => new Lehrer(),
            'benutzer'  => $this->benutzerListe(),
        ]);
    }

    public function store(Request $request, Schuljahr $schuljahr)
    {
        $lehrer = $schuljahr->lehrer()->create($this->validated($request));

        Protokoll::log('lehrer_angelegt', [
            'schuljahr_id' => $schuljahr->id,
            'beschreibung' => "Lehrer {$lehrer->fullName()} in {$schuljahr->name} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.lehrer.jahr', $schuljahr)
            ->with('status', "Lehrer {$lehrer->fullName()} angelegt.");
    }

    public function edit(Lehrer $lehrer)
    {
        return view('schulzeugnis::lehrer.form', [
            'schuljahr' => $lehrer->schuljahr,
            'lehrer'    => $lehrer,
            'benutzer'  => $this->benutzerListe(),
        ]);
    }

    public function update(Request $request, Lehrer $lehrer)
    {
        $alt = $lehrer->fullName();

        $lehrer->update($this->validated($request));

        Protokoll::log('lehrer_geaendert', [
            'schuljahr_id' => $lehrer->schuljahr_id,
            'beschreibung' => 'Lehrer bearbeitet',
            'alt_wert'     => $alt,
            'neu_wert'     => $lehrer->fullName(),
        ]);

        return redirect()
            ->route('module.schulzeugnis.lehrer.jahr', $lehrer->schuljahr_id)
            ->with('status', "Lehrer {$lehrer->fullName()} gespeichert.");
    }

    public function destroy(Lehrer $lehrer)
    {
        // Schutz: als Klassenlehrer, in Lehraufträgen oder als Autor verwendet → nicht löschen.
        $verwendet = DB::table('zeugnis_klassen')->where('klassenlehrer_id', $lehrer->id)->exists()
            || DB::table('zeugnis_lehrauftraege')->where('lehrer_id', $lehrer->id)->exists()
            || DB::table('zeugnis_abschnitte')->where('autor_lehrer_id', $lehrer->id)->exists();

        if ($verwendet) {
            return redirect()
                ->route('module.schulzeugnis.lehrer.jahr', $lehrer->schuljahr_id)
                ->with('error', "{$lehrer->fullName()} kann nicht gelöscht werden – bereits als Klassenlehrer, in Lehraufträgen oder als Autor verwendet.");
        }

        $name        = $lehrer->fullName();
        $schuljahrId = $lehrer->schuljahr_id;

        Protokoll::log('lehrer_geloescht', [
            'schuljahr_id' => $schuljahrId,
            'beschreibung' => "Lehrer {$name} gelöscht",
        ]);

        $lehrer->delete();

        return redirect()
            ->route('module.schulzeugnis.lehrer.jahr', $schuljahrId)
            ->with('status', "Lehrer {$name} gelöscht.");
    }

    /**
     * Menü-Sprung "Lehrer": ins aktive Schuljahr, sonst zur Schuljahr-Liste.
     */
    public function current()
    {
        $aktiv = Schuljahr::where('is_active', true)->first();

        if ($aktiv) {
            return redirect()->route('module.schulzeugnis.lehrer.jahr', $aktiv);
        }

        return redirect()
            ->route('module.schulzeugnis.schuljahre.index')
            ->with('error', 'Kein aktives Schuljahr gesetzt – bitte zuerst eines aktiv schalten.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'vorname'      => ['required', 'string', 'max:255'],
            'nachname'     => ['required', 'string', 'max:255'],
            'core_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);
    }

    private function benutzerListe()
    {
        return User::orderBy('name')->get(['id', 'name', 'email']);
    }
}
