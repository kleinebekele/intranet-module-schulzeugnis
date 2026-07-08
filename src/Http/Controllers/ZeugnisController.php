<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schueler;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;

/**
 * Befüllte Zeugnisse einer Klasse: anlegen (Abschnitte automatisch), befüllen,
 * abschließen (friert ein) und – nur für Admins – wieder öffnen.
 */
class ZeugnisController
{
    public function index(Klasse $klasse)
    {
        $klasse->load('schuljahr');

        // Spalten der Tabelle: alle Fächer, die in dieser Klasse einen Lehrauftrag haben.
        $fachIds = $klasse->lehrauftraege()->distinct()->pluck('fach_id');
        $faecher = Fach::whereIn('id', $fachIds)
            ->orderBy('reihenfolge')
            ->orderBy('name')
            ->get();

        $schueler = $klasse->schueler()
            ->with(['zeugnis.abschnitte'])
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('schulzeugnis::zeugnisse.index', [
            'klasse'   => $klasse,
            'faecher'  => $faecher,
            'schueler' => $schueler,
            'stati'    => Abschnitt::STATI,
        ]);
    }

    /** Zeugnis für einen Schüler anlegen und die Abschnitte automatisch erzeugen. */
    public function store(Klasse $klasse, Schueler $schueler)
    {
        if ($schueler->zeugnis) {
            return redirect()->route('module.schulzeugnis.zeugnisse.edit', $schueler->zeugnis);
        }

        $schueler->setRelation('klasse', $klasse);
        $formatId = $schueler->effektivesFormatId();
        $format   = $formatId ? Format::find($formatId) : null;
        $istNoten = $format && $format->typ === 'noten';

        $zeugnis = Zeugnis::create([
            'schueler_id' => $schueler->id,
            'format_id'   => $formatId,
            'status'      => Zeugnis::STATUS_ENTWURF,
        ]);

        // Haupttext (Klassenlehrer)
        $klasse->loadMissing('klassenlehrer');
        $zeugnis->abschnitte()->create([
            'typ'             => Abschnitt::TYP_HAUPTTEXT,
            'autor_lehrer_id' => $klasse->klassenlehrer_id,
            'autor_name'      => $klasse->klassenlehrer?->fullName(),
            'reihenfolge'     => 0,
            'status'          => Abschnitt::STATUS_STANDARD,
        ]);

        // Je Fach mit Lehrauftrag ein Fachtext (bzw. Note bei Noten-Formaten).
        // Team-Teaching: alle Lehrer eines Fachs sind gemeinsam ein Autor.
        $proFach = $klasse->lehrauftraege()->with(['fach', 'lehrer'])->get()->groupBy('fach_id');

        foreach ($proFach as $gruppe) {
            $fach    = $gruppe->first()->fach;
            $autoren = $gruppe->map(fn ($la) => $la->lehrer?->fullName())->filter()->unique()->implode(', ');

            $zeugnis->abschnitte()->create([
                'typ'         => $istNoten ? Abschnitt::TYP_NOTE : Abschnitt::TYP_FACHTEXT,
                'fach_id'     => $fach?->id,
                'autor_name'  => $autoren ?: null,
                'reihenfolge' => $fach?->reihenfolge ?? 1,
                'status'      => Abschnitt::STATUS_OFFEN,
            ]);
        }

        Protokoll::log('zeugnis_angelegt', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Zeugnis für {$schueler->fullName()} angelegt",
        ]);

        return redirect()
            ->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
            ->with('status', "Zeugnis für {$schueler->fullName()} angelegt.");
    }

    public function edit(Zeugnis $zeugnis)
    {
        $zeugnis->load(['schueler.klasse.schuljahr', 'format', 'abschnitte.fach']);

        $abschnitte = $zeugnis->abschnitte
            ->sortBy([['reihenfolge', 'asc'], ['id', 'asc']])
            ->values();

        return view('schulzeugnis::zeugnisse.edit', [
            'zeugnis'    => $zeugnis,
            'schueler'   => $zeugnis->schueler,
            'abschnitte' => $abschnitte,
            'stati'      => Abschnitt::STATI,
            'istAdmin'   => (bool) auth()->user()?->is_admin,
        ]);
    }

    public function update(Request $request, Zeugnis $zeugnis)
    {
        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
                ->with('error', 'Das Zeugnis ist abgeschlossen und kann nicht geändert werden.');
        }

        $eingaben = $request->input('abschnitte', []);

        foreach ($zeugnis->abschnitte as $abschnitt) {
            if (! array_key_exists($abschnitt->id, $eingaben)) {
                continue;
            }
            $row = $eingaben[$abschnitt->id];

            $abschnitt->inhalt = $row['inhalt'] ?? null;
            if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
                $abschnitt->note = $row['note'] ?? null;
            }
            $statusEingabe = $row['status'] ?? '';
            $abschnitt->status = array_key_exists($statusEingabe, Abschnitt::STATI)
                ? $statusEingabe
                : Abschnitt::STATUS_STANDARD;
            $abschnitt->save();
        }

        Protokoll::log('zeugnis_gespeichert', [
            'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Zeugnis für {$zeugnis->schueler?->fullName()} gespeichert",
        ]);

        return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis gespeichert.');
    }

    public function abschliessen(Zeugnis $zeugnis)
    {
        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis);
        }

        $schueler = $zeugnis->schueler;

        $zeugnis->update([
            'status'                   => Zeugnis::STATUS_ABGESCHLOSSEN,
            'ausgestellt_am'           => now(),
            'ausgestellt_auf_name'     => $schueler?->fullName(),
            'ausgestellt_geburtsdatum' => $schueler?->geburtsdatum,
            'ausgestellt_geburtsort'   => $schueler?->geburtsort,
        ]);

        Protokoll::log('zeugnis_abgeschlossen', [
            'schuljahr_id' => $schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Zeugnis für {$schueler?->fullName()} abgeschlossen (eingefroren)",
        ]);

        return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis abgeschlossen und eingefroren.');
    }

    /** Nur Admins dürfen ein abgeschlossenes Zeugnis wieder öffnen. */
    public function wiederOeffnen(Zeugnis $zeugnis)
    {
        if (! auth()->user()?->is_admin) {
            return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
                ->with('error', 'Nur Administratoren können ein abgeschlossenes Zeugnis wieder öffnen.');
        }

        $zeugnis->update([
            'status'                   => Zeugnis::STATUS_ENTWURF,
            'ausgestellt_am'           => null,
            'ausgestellt_auf_name'     => null,
            'ausgestellt_geburtsdatum' => null,
            'ausgestellt_geburtsort'   => null,
        ]);

        Protokoll::log('zeugnis_wieder_geoeffnet', [
            'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Zeugnis für {$zeugnis->schueler?->fullName()} wieder geöffnet (Admin)",
        ]);

        return redirect()->route('module.schulzeugnis.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis wieder geöffnet – Bearbeitung möglich.');
    }

    /** Einzelnen Abschnitt (Fachtext/Haupttext/Note) bearbeiten – mit Änderungsverlauf. */
    public function abschnittEdit(Abschnitt $abschnitt)
    {
        $abschnitt->load(['zeugnis.schueler.klasse.schuljahr', 'fach']);
        $zeugnis = $abschnitt->zeugnis;

        $verlauf = Protokoll::where('abschnitt_id', $abschnitt->id)
            ->whereIn('aktion', ['abschnitt_geaendert', 'abschnitt_wiederhergestellt'])
            ->orderByDesc('id')
            ->get();

        return view('schulzeugnis::zeugnisse.abschnitt', [
            'abschnitt' => $abschnitt,
            'zeugnis'   => $zeugnis,
            'schueler'  => $zeugnis->schueler,
            'stati'     => Abschnitt::STATI,
            'verlauf'   => $verlauf,
            'readonly'  => $zeugnis->istAbgeschlossen(),
        ]);
    }

    public function abschnittUpdate(Request $request, Abschnitt $abschnitt)
    {
        $abschnitt->load('zeugnis.schueler', 'fach');
        $zeugnis = $abschnitt->zeugnis;

        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.abschnitte.edit', $abschnitt)
                ->with('error', 'Das Zeugnis ist abgeschlossen und kann nicht geändert werden.');
        }

        $data = $request->validate([
            'inhalt' => ['nullable', 'string'],
            'note'   => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(array_keys(Abschnitt::STATI))],
        ]);

        $altInhalt = $abschnitt->inhalt;

        $abschnitt->inhalt = $data['inhalt'] ?? null;
        if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
            $abschnitt->note = $data['note'] ?? null;
        }
        $abschnitt->status = $data['status'];
        $abschnitt->save();

        // Nur inhaltliche Änderungen kommen in den (append-only) Verlauf.
        if ((string) $altInhalt !== (string) $abschnitt->inhalt) {
            Protokoll::log('abschnitt_geaendert', [
                'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
                'zeugnis_id'   => $zeugnis->id,
                'abschnitt_id' => $abschnitt->id,
                'beschreibung' => $this->abschnittLabel($abschnitt) . ' geändert',
                'alt_wert'     => $altInhalt,
                'neu_wert'     => $abschnitt->inhalt,
            ]);
        }

        return redirect()->route('module.schulzeugnis.abschnitte.edit', $abschnitt)
            ->with('status', 'Gespeichert.');
    }

    /** Einen früheren Textstand aus dem Verlauf wiederherstellen. */
    public function abschnittWiederherstellen(Request $request, Abschnitt $abschnitt)
    {
        $abschnitt->load('zeugnis.schueler', 'fach');
        $zeugnis = $abschnitt->zeugnis;

        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.abschnitte.edit', $abschnitt)
                ->with('error', 'Das Zeugnis ist abgeschlossen und kann nicht geändert werden.');
        }

        $eintrag = Protokoll::where('abschnitt_id', $abschnitt->id)
            ->whereIn('aktion', ['abschnitt_geaendert', 'abschnitt_wiederhergestellt'])
            ->findOrFail((int) $request->input('protokoll_id'));

        $altInhalt = $abschnitt->inhalt;
        $ziel      = $eintrag->neu_wert;

        $abschnitt->update(['inhalt' => $ziel]);

        Protokoll::log('abschnitt_wiederhergestellt', [
            'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'abschnitt_id' => $abschnitt->id,
            'beschreibung' => $this->abschnittLabel($abschnitt) . ': Stand vom ' . $eintrag->created_at?->format('d.m.Y H:i') . ' wiederhergestellt',
            'alt_wert'     => $altInhalt,
            'neu_wert'     => $ziel,
        ]);

        return redirect()->route('module.schulzeugnis.abschnitte.edit', $abschnitt)
            ->with('status', 'Früherer Stand wiederhergestellt.');
    }

    private function abschnittLabel(Abschnitt $abschnitt): string
    {
        return $abschnitt->typ === Abschnitt::TYP_HAUPTTEXT
            ? 'Haupttext'
            : ('Fach: ' . ($abschnitt->fach?->name ?? '—'));
    }
}
