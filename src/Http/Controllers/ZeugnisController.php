<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
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

        $schueler = $klasse->schueler()
            ->with(['zeugnis.abschnitte'])
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('schulzeugnis::zeugnisse.index', compact('klasse', 'schueler'));
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
            'status'          => Abschnitt::STATUS_OFFEN,
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
            $abschnitt->status = ($row['status'] ?? '') === Abschnitt::STATUS_FERTIG
                ? Abschnitt::STATUS_FERTIG
                : Abschnitt::STATUS_OFFEN;
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
}
