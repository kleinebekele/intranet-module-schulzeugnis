<?php

namespace Intranet\Modules\Schulzeugnis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulzeugnis\Models\Abschnitt;
use Intranet\Modules\Schulzeugnis\Models\Fach;
use Intranet\Modules\Schulzeugnis\Models\Format;
use Intranet\Modules\Schulzeugnis\Models\Klasse;
use Intranet\Modules\Schulzeugnis\Models\Klassentext;
use Intranet\Modules\Schulzeugnis\Models\Lehrauftrag;
use Intranet\Modules\Schulzeugnis\Models\Lehrer;
use Intranet\Modules\Schulzeugnis\Models\Protokoll;
use Intranet\Modules\Schulzeugnis\Models\Schueler;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;
use Intranet\Modules\Schulzeugnis\Support\ZeugnisRenderer;

/**
 * Befüllte Zeugnisse einer Klasse: anlegen (Abschnitte automatisch), befüllen,
 * abschließen (friert ein) und – nur für Admins – wieder öffnen.
 */
class ZeugnisController
{
    /** Status, die ein zugewiesener Korrektor setzen darf. */
    private const KORREKTUR_STATI = ['in_korrektur', 'korrektur_durchgefuehrt'];

    /** Status, für die beim Speichern Korrektoren ausgewählt sein müssen. */
    private const BRAUCHT_KORREKTOREN = ['frei_zur_korrektur', 'korrektur_noetig'];

    /** Status-Farbe (farbe-Key aus Abschnitt::STATI) → Hex, für die Verlaufs-Anzeige. */
    private const STATUS_FARBE_HEX = ['gray' => '#9ca3af', 'amber' => '#f59e0b', 'red' => '#ef4444', 'green' => '#16a34a'];

    public function index(Klasse $klasse)
    {
        $klasse->load(['schuljahr', 'klassenlehrer']);

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

        // „Meine Fächer": Fächer, in denen der eingeloggte Nutzer hier einen Lehrauftrag hat.
        $userId = auth()->id();
        $meineFachIds = $klasse->lehrauftraege()
            ->whereHas('lehrer', fn ($q) => $q->where('core_user_id', $userId))
            ->pluck('fach_id')->unique()->values()->all();
        $binKlassenlehrer = $klasse->klassenlehrer && (int) $klasse->klassenlehrer->core_user_id === (int) $userId;

        // Für die Spaltenkopf-Tooltips: Fachlehrer je Fach + Klassentext je Fach (und Haupttext).
        $fachlehrer = $klasse->lehrauftraege()->with('lehrer')->get()
            ->groupBy('fach_id')
            ->map(fn ($g) => $g->map(fn ($la) => $la->lehrer?->fullName())->filter()->unique()->values()->all());

        $klassentexte = Klassentext::where('klasse_id', $klasse->id)->get()
            ->mapWithKeys(fn ($kt) => [($kt->fach_id === null ? 'haupt' : $kt->fach_id) => (string) $kt->text]);

        // Überlauf-/Auto-Verkleinerungs-Analyse je Zeugnis (aus dem Cache; nur beim
        // ersten Mal bzw. nach Inhaltsänderungen wird gerechnet).
        $warnungen = [];
        foreach ($schueler as $s) {
            if (! $s->zeugnis) {
                continue;
            }
            $z = $s->zeugnis;
            if ($z->ueberlauf_status === null) {
                $this->ueberlaufNeuBerechnen($z);
            }
            $warnungen[$s->id] = ['status' => $z->ueberlauf_status, 'passtBei' => $z->ueberlauf_passt_bei];
        }

        return view('schulzeugnis::zeugnisse.index', [
            'klasse'           => $klasse,
            'faecher'          => $faecher,
            'schueler'         => $schueler,
            'stati'            => Abschnitt::STATI,
            'meineFachIds'     => $meineFachIds,
            'binKlassenlehrer' => $binKlassenlehrer,
            'warnungen'        => $warnungen,
            'fachlehrer'       => $fachlehrer,
            'klassentexte'     => $klassentexte,
            'istAdmin'         => (bool) auth()->user()?->is_admin,
        ]);
    }

    /** HTML-Vorschau des befüllten Zeugnisses (echte Daten durchs Format-Layout). */
    public function vorschau(Zeugnis $zeugnis, ZeugnisRenderer $renderer)
    {
        $r = $renderer->render($zeugnis);

        return view('schulzeugnis::formate.render', ['seiten' => $r['seiten'], 'daten' => $r['daten']]);
    }

    /** Befülltes Zeugnis als PDF (dompdf), echtes Papierformat. */
    public function pdf(Zeugnis $zeugnis, ZeugnisRenderer $renderer)
    {
        $zeugnis->loadMissing('format');
        $format = $zeugnis->format;

        [$groesse, $lage] = $format && $format->broschuere
            ? ['a3', 'landscape']
            : [$format && $format->seitenformat === 'a3' ? 'a3' : 'a4', $format && $format->ausrichtung === 'quer' ? 'landscape' : 'portrait'];

        $r = $renderer->render($zeugnis);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulzeugnis::formate.render', ['seiten' => $r['seiten'], 'daten' => $r['daten']])
            ->setPaper($groesse, $lage);

        $name = $zeugnis->schueler?->fullName() ?: 'zeugnis';

        return $pdf->stream('zeugnis-' . \Illuminate\Support\Str::slug($name) . '.pdf');
    }

    /** Zeugnis für einen Schüler anlegen und die Abschnitte automatisch erzeugen. */
    public function store(Klasse $klasse, Schueler $schueler)
    {
        if ($schueler->zeugnis) {
            return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $schueler->zeugnis);
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

        $this->ueberlaufNeuBerechnen($zeugnis);

        return redirect()
            ->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
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
            return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
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

        $this->ueberlaufNeuBerechnen($zeugnis);

        return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis gespeichert.');
    }

    public function abschliessen(Zeugnis $zeugnis)
    {
        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis);
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

        return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis abgeschlossen und eingefroren.');
    }

    /** Nur Admins dürfen ein abgeschlossenes Zeugnis wieder öffnen. */
    public function wiederOeffnen(Zeugnis $zeugnis)
    {
        if (! auth()->user()?->is_admin) {
            return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
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

        return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.edit', $zeugnis)
            ->with('status', 'Zeugnis wieder geöffnet – Bearbeitung möglich.');
    }

    /** Einzelnen Abschnitt (Fachtext/Haupttext/Note) bearbeiten – mit Änderungsverlauf. */
    public function abschnittEdit(Abschnitt $abschnitt)
    {
        $abschnitt->load(['zeugnis.schueler.klasse.schuljahr', 'fach', 'korrektoren']);
        $zeugnis = $abschnitt->zeugnis;
        $klasse  = $zeugnis->schueler?->klasse;

        $hex = self::STATUS_FARBE_HEX;
        $verlauf = Protokoll::where('abschnitt_id', $abschnitt->id)
            ->whereIn('aktion', ['abschnitt_geaendert', 'abschnitt_status', 'abschnitt_notiz', 'abschnitt_klassentext', 'abschnitt_wiederhergestellt', 'abschnitt_klassentext_wiederhergestellt'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Protokoll $e) use ($hex) {
                $istStatus  = $e->aktion === 'abschnitt_status';
                $istRestore = in_array($e->aktion, ['abschnitt_wiederhergestellt', 'abschnitt_klassentext_wiederhergestellt'], true);
                $wz = fn ($s) => trim((string) $s) === '' ? 0 : count(preg_split('/\s+/u', trim((string) $s)));

                $status  = null;
                $summary = '';

                if ($istStatus) {
                    $meta = fn ($k) => Abschnitt::STATI[$k] ?? ['label' => $k, 'icon' => 'bx-circle', 'farbe' => 'gray'];
                    $ma = $meta($e->alt_wert);
                    $mn = $meta($e->neu_wert);
                    $status = [
                        'altLabel' => $ma['label'], 'altIcon' => $ma['icon'], 'altColor' => $hex[$ma['farbe']] ?? '#9ca3af',
                        'neuLabel' => $mn['label'], 'neuIcon' => $mn['icon'], 'neuColor' => $hex[$mn['farbe']] ?? '#9ca3af',
                    ];
                } elseif ($istRestore) {
                    $n = $wz($e->neu_wert);
                    $summary = $n > 0
                        ? ($n . ($n === 1 ? ' Wort' : ' Wörter') . ' wiederhergestellt')
                        : 'Text geleert (wiederhergestellt)';
                } else {
                    $delta = $wz($e->neu_wert) - $wz($e->alt_wert);
                    $summary = $delta > 0
                        ? ($delta . ($delta === 1 ? ' Wort' : ' Wörter') . ' hinzugefügt')
                        : ($delta < 0
                            ? (abs($delta) . (abs($delta) === 1 ? ' Wort' : ' Wörter') . ' entfernt')
                            : 'überarbeitet (gleiche Wortzahl)');
                }

                return [
                    'id'                => $e->id,
                    'zeit'              => $e->created_at,
                    'akteur'            => $e->akteur_name,
                    'feld'              => $e->beschreibung,
                    'istStatus'         => $istStatus,
                    'wiederhergestellt' => $istRestore,
                    'status'            => $status,
                    'summary'           => $summary,
                    'alt'               => (string) $e->alt_wert,
                    'neu'               => (string) $e->neu_wert,
                    'restorable'        => in_array($e->aktion, ['abschnitt_geaendert', 'abschnitt_wiederhergestellt', 'abschnitt_klassentext', 'abschnitt_klassentext_wiederhergestellt'], true),
                ];
            });

        $berechtigung = $this->berechtigung($abschnitt, auth()->user());
        $nachbarn     = $this->abschnittNachbarn($abschnitt);

        // Herkunft merken (?quelle=todo|zeugnisse) → passender „Zurück"-Button.
        // focus = zuletzt bearbeiteter Abschnitt → Zielseite scrollt/klappt ihn auf.
        $quelle  = request('quelle') === 'todo' ? 'todo' : 'zeugnisse';
        $zurueck = $quelle === 'todo'
            ? ['url' => route('module.schulzeugnis.todo.index', ['focus' => $abschnitt->id]), 'label' => 'Meine ToDos', 'icon' => 'bx-list-check']
            : ['url' => $klasse
                    ? route('module.schulzeugnis.klassenraeume.zeugnisse.index', ['klasse' => $klasse, 'focus' => $abschnitt->id])
                    : route('module.schulzeugnis.klassenraeume.index'),
               'label' => 'Zeugnis-Tabelle', 'icon' => 'bx-table'];

        return view('schulzeugnis::zeugnisse.abschnitt', [
            'quelle'         => $quelle,
            'zurueck'        => $zurueck,
            'abschnitt'      => $abschnitt,
            'zeugnis'        => $zeugnis,
            'schueler'       => $zeugnis->schueler,
            'stati'          => Abschnitt::STATI,
            'verlauf'        => $verlauf,
            'klassentext'    => $klasse ? $this->klassentextFuer($klasse->id, $abschnitt->fach_id) : null,
            'berechtigung'   => $berechtigung,
            'korrekturStati' => self::KORREKTUR_STATI,
            'alleLehrer'     => $klasse ? Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->orderBy('nachname')->orderBy('vorname')->get() : collect(),
            'korrektorIds'   => $abschnitt->korrektoren->pluck('id')->all(),
            'readonly'       => $zeugnis->istAbgeschlossen() || $berechtigung === 'keine',
            'navPrev'        => $nachbarn['prev'],
            'navNext'        => $nachbarn['next'],
            'navPosition'    => $nachbarn['position'],
            'navGesamt'      => $nachbarn['gesamt'],
        ]);
    }

    /**
     * Vorheriger/nächster Schüler mit demselben Abschnitt (gleicher Typ + Fach),
     * in derselben Klasse und in Tabellen-Reihenfolge (Nachname, Vorname).
     * Schüler ohne passenden Abschnitt werden übersprungen.
     *
     * @return array{prev:?array{id:int,name:string},next:?array{id:int,name:string},position:?int,gesamt:?int}
     */
    private function abschnittNachbarn(Abschnitt $abschnitt): array
    {
        $leer   = ['prev' => null, 'next' => null, 'position' => null, 'gesamt' => null];
        $klasse = $abschnitt->zeugnis?->schueler?->klasse;
        if (! $klasse) {
            return $leer;
        }

        $kette = $klasse->schueler()
            ->with(['zeugnis.abschnitte'])
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get()
            ->map(function ($s) use ($abschnitt) {
                $treffer = $s->zeugnis?->abschnitte->first(
                    fn ($a) => $a->typ === $abschnitt->typ && $a->fach_id === $abschnitt->fach_id
                );

                return $treffer ? ['id' => $treffer->id, 'name' => $s->fullName()] : null;
            })
            ->filter()
            ->values();

        $idx = $kette->search(fn ($e) => $e['id'] === $abschnitt->id);
        if ($idx === false) {
            return $leer;
        }

        return [
            'prev'     => $idx > 0 ? $kette[$idx - 1] : null,
            'next'     => $idx < $kette->count() - 1 ? $kette[$idx + 1] : null,
            'position' => $idx + 1,
            'gesamt'   => $kette->count(),
        ];
    }

    public function abschnittUpdate(Request $request, Abschnitt $abschnitt)
    {
        $abschnitt->load('zeugnis.schueler.klasse', 'fach', 'korrektoren');
        $zeugnis = $abschnitt->zeugnis;
        $klasse  = $zeugnis->schueler?->klasse;
        $b       = $this->berechtigung($abschnitt, auth()->user());

        // Für die Rückmeldung: wessen Text (Name + Fach) tatsächlich gespeichert wurde
        // – wichtig beim Blättern, wo danach schon der nächste Schüler angezeigt wird.
        $gespeichertName = $zeugnis->schueler?->fullName() ?: 'Schüler';
        $gespeichertWas  = $abschnitt->typ === Abschnitt::TYP_HAUPTTEXT ? 'Haupttext' : ($abschnitt->fach?->name ?? 'Fachtext');

        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Das Zeugnis ist abgeschlossen und kann nicht geändert werden.');
        }
        if ($b === 'keine') {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Du bist für diesen Text nicht berechtigt.');
        }

        // Korrektor: nur Text korrigieren + Status auf „in Korrektur"/„Korrektur durchgeführt".
        if ($b === 'korrektor') {
            $data = $request->validate([
                'inhalt' => ['nullable', 'string'],
                'note'   => ['nullable', 'string', 'max:20'],
                'status' => ['required', Rule::in(self::KORREKTUR_STATI)],
                'weiter' => ['nullable', Rule::in(['next', 'prev', 'index'])],
            ]);

            $altInhalt = $abschnitt->inhalt;
            $altStatus = $abschnitt->status;
            $abschnitt->inhalt = $data['inhalt'] ?? null;
            if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
                $abschnitt->note = $data['note'] ?? null;
            }
            $abschnitt->status = $data['status'];
            $abschnitt->save();

            $textFeld = $abschnitt->typ === Abschnitt::TYP_NOTE ? 'Note' : 'Schülertext';
            $this->logFeld($abschnitt, $textFeld, $altInhalt, $abschnitt->inhalt);
            $this->logStatus($abschnitt, $altStatus, $abschnitt->status);

            $this->ueberlaufNeuBerechnen($zeugnis);

            return $this->zielNachSpeichern($request, $abschnitt)
                ->with('status', $gespeichertWas . ' für ' . $gespeichertName . ' korrigiert.');
        }

        // Voll berechtigt (Autor / Klassenlehrer / Admin): alles inkl. Korrektoren-Zuweisung.
        $data = $request->validate([
            'inhalt'        => ['nullable', 'string'],
            'note'          => ['nullable', 'string', 'max:20'],
            'status'        => ['required', Rule::in(array_keys(Abschnitt::STATI))],
            'notiz'         => ['nullable', 'string'],
            'klassentext'   => ['nullable', 'string'],
            'korrektoren'   => ['array'],
            'korrektoren.*' => ['integer', Rule::exists('zeugnis_schuljahr_lehrer', 'id')],
            'weiter'        => ['nullable', Rule::in(['next', 'prev', 'index'])],
        ]);

        // Korrektor-Pflicht nur beim normalen Speichern erzwingen. Beim Blättern
        // („Speichern & vorheriger/nächster") nicht blockieren – dann wird gespeichert
        // und einfach zum Nachbarn gewechselt, Korrektoren können später folgen.
        $korrektoren = $data['korrektoren'] ?? [];
        $blaettert   = in_array((string) $request->input('weiter'), ['next', 'prev', 'index'], true);
        if (! $blaettert && in_array($data['status'], self::BRAUCHT_KORREKTOREN, true) && empty($korrektoren)) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->withInput()
                ->with('error', 'Bitte mindestens einen Korrektor auswählen, wenn der Text zur Korrektur freigegeben wird.');
        }

        $altInhalt = $abschnitt->inhalt;
        $altNotiz  = $abschnitt->notiz;
        $altStatus = $abschnitt->status;

        $abschnitt->inhalt = $data['inhalt'] ?? null;
        if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
            $abschnitt->note = $data['note'] ?? null;
        }
        $abschnitt->status = $data['status'];
        $abschnitt->notiz = $data['notiz'] ?? null;
        $abschnitt->klassentext_neue_zeile = $request->boolean('klassentext_neue_zeile');
        $abschnitt->save();

        $abschnitt->korrektoren()->sync($korrektoren);

        $textFeld = $abschnitt->typ === Abschnitt::TYP_NOTE ? 'Note' : 'Schülertext';
        $this->logFeld($abschnitt, $textFeld, $altInhalt, $abschnitt->inhalt);
        $this->logFeld($abschnitt, 'Notiz', $altNotiz, $abschnitt->notiz, 'abschnitt_notiz');
        $this->logStatus($abschnitt, $altStatus, $abschnitt->status);

        // Klassenweiter Text – gilt für alle Schüler der Klasse (je Fach bzw. Haupttext).
        $klassentextGeaendert = false;
        if ($klasse) {
            $kt  = $this->klassentextFuer($klasse->id, $abschnitt->fach_id);
            $neu = $data['klassentext'] ?? null;
            if ((string) $kt->text !== (string) $neu) {
                $altKt = $kt->text;
                $kt->text = $neu;
                $kt->save();
                $klassentextGeaendert = true;
                $this->logFeld($abschnitt, 'Klassenweiter Text', $altKt, $neu, 'abschnitt_klassentext');
            }
        }

        $this->ueberlaufNeuBerechnen($zeugnis);

        if ($klassentextGeaendert && $klasse) {
            Zeugnis::whereHas('schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
                ->where('id', '!=', $zeugnis->id)
                ->update(['ueberlauf_status' => null]);
        }

        return $this->zielNachSpeichern($request, $abschnitt)
            ->with('status', $gespeichertWas . ' für ' . $gespeichertName . ' gespeichert.');
    }

    /**
     * Redirect nach dem Speichern gemäß Auswahl „weiter":
     * next/prev = Nachbar-Schüler (gleiches Fach), index = zurück zur Zeugnisliste,
     * sonst beim aktuellen Abschnitt bleiben.
     */
    private function zielNachSpeichern(Request $request, Abschnitt $abschnitt)
    {
        $weiter   = (string) $request->input('weiter');
        $quelle   = $request->input('quelle') === 'todo' ? 'todo' : 'zeugnisse';
        $nachbarn = $this->abschnittNachbarn($abschnitt);
        $klasse   = $abschnitt->zeugnis?->schueler?->klasse;

        if ($weiter === 'next' && $nachbarn['next']) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $nachbarn['next']['id'], 'quelle' => $quelle]);
        }
        if ($weiter === 'prev' && $nachbarn['prev']) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $nachbarn['prev']['id'], 'quelle' => $quelle]);
        }
        if ($weiter === 'index') {
            // „Zurück zur Übersicht" führt zur Herkunft (ToDos bzw. Zeugnis-Tabelle),
            // mit Fokus auf den gerade gespeicherten Abschnitt.
            if ($quelle === 'todo') {
                return redirect()->route('module.schulzeugnis.todo.index', ['focus' => $abschnitt->id]);
            }

            return $klasse
                ? redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.index', ['klasse' => $klasse, 'focus' => $abschnitt->id])
                : redirect()->route('module.schulzeugnis.klassenraeume.index');
        }

        return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', ['abschnitt' => $abschnitt, 'quelle' => $quelle]);
    }

    /** Einen früheren Textstand aus dem Verlauf wiederherstellen. */
    public function abschnittWiederherstellen(Request $request, Abschnitt $abschnitt)
    {
        $abschnitt->load('zeugnis.schueler.klasse', 'fach', 'korrektoren');
        $zeugnis = $abschnitt->zeugnis;

        if ($zeugnis->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Das Zeugnis ist abgeschlossen und kann nicht geändert werden.');
        }
        if ($this->berechtigung($abschnitt, auth()->user()) !== 'voll') {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Nur die verantwortliche Lehrkraft kann frühere Stände wiederherstellen.');
        }

        $eintrag = Protokoll::where('abschnitt_id', $abschnitt->id)
            ->whereIn('aktion', ['abschnitt_geaendert', 'abschnitt_wiederhergestellt', 'abschnitt_klassentext', 'abschnitt_klassentext_wiederhergestellt'])
            ->findOrFail((int) $request->input('protokoll_id'));

        $ziel = $eintrag->alt_wert; // der „Vorher"-Stand dieser Änderung

        // Klassenweiter Text: in den Klassentext (Klasse + Fach) zurückschreiben.
        if (in_array($eintrag->aktion, ['abschnitt_klassentext', 'abschnitt_klassentext_wiederhergestellt'], true)) {
            $klasse = $zeugnis->schueler?->klasse;
            if ($klasse) {
                $kt  = $this->klassentextFuer($klasse->id, $abschnitt->fach_id);
                $alt = $kt->text;
                $kt->text = $ziel;
                $kt->save();
                $this->logFeld($abschnitt, 'Klassenweiter Text', $alt, $ziel, 'abschnitt_klassentext_wiederhergestellt');

                // Überlauf-Analyse aller Zeugnisse der Klasse verwerfen – sie ist jetzt veraltet.
                Zeugnis::whereHas('schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
                    ->update(['ueberlauf_status' => null]);
            }

            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('status', 'Früherer Klassentext-Stand wiederhergestellt.');
        }

        // Schülertext: in den Abschnitt zurückschreiben.
        $altInhalt = $abschnitt->inhalt;
        $abschnitt->update(['inhalt' => $ziel]);

        Protokoll::log('abschnitt_wiederhergestellt', [
            'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'abschnitt_id' => $abschnitt->id,
            'beschreibung' => 'Schülertext',
            'alt_wert'     => $altInhalt,
            'neu_wert'     => $ziel,
        ]);

        $this->ueberlaufNeuBerechnen($zeugnis);

        return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
            ->with('status', 'Früherer Stand wiederhergestellt.');
    }

    /** Eine Feld-Änderung (Text/Notiz/Klassentext) protokollieren – nur wenn sie sich unterscheidet. */
    private function logFeld(Abschnitt $abschnitt, string $feld, ?string $alt, ?string $neu, string $aktion = 'abschnitt_geaendert'): void
    {
        if ((string) $alt === (string) $neu) {
            return;
        }

        Protokoll::log($aktion, [
            'schuljahr_id' => $abschnitt->zeugnis?->schueler?->schuljahr_id,
            'zeugnis_id'   => $abschnitt->zeugnis_id,
            'abschnitt_id' => $abschnitt->id,
            'beschreibung' => $feld,
            'alt_wert'     => $alt,
            'neu_wert'     => $neu,
        ]);
    }

    /** Eine Status-Änderung protokollieren (alt/neu als Status-Keys – Label/Icon/Farbe folgen bei der Anzeige). */
    private function logStatus(Abschnitt $abschnitt, string $alt, string $neu): void
    {
        $this->logFeld($abschnitt, 'Status', $alt, $neu, 'abschnitt_status');
    }

    /**
     * Berechtigung des Nutzers für einen Abschnitt: 'voll' | 'korrektor' | 'keine'.
     * Voll = Admin, Fachlehrer (Lehrauftrag) bzw. Klassenlehrer (Haupttext).
     * Korrektor = für genau diesen Abschnitt zur Korrektur zugewiesen.
     */
    private function berechtigung(Abschnitt $abschnitt, $user): string
    {
        if (! $user) {
            return 'keine';
        }
        if ($user->is_admin) {
            return 'voll';
        }

        $klasse = $abschnitt->zeugnis?->schueler?->klasse;
        if (! $klasse) {
            return 'keine';
        }

        $meineLehrerIds = Lehrer::where('schuljahr_id', $klasse->schuljahr_id)
            ->where('core_user_id', $user->id)
            ->pluck('id');

        if ($abschnitt->typ === Abschnitt::TYP_HAUPTTEXT) {
            if ($klasse->klassenlehrer_id && $meineLehrerIds->contains($klasse->klassenlehrer_id)) {
                return 'voll';
            }
        } else {
            $fachLehrer = Lehrauftrag::where('klasse_id', $klasse->id)
                ->where('fach_id', $abschnitt->fach_id)
                ->pluck('lehrer_id');
            if ($meineLehrerIds->intersect($fachLehrer)->isNotEmpty()) {
                return 'voll';
            }
        }

        $korrektorIds = $abschnitt->relationLoaded('korrektoren')
            ? $abschnitt->korrektoren->pluck('id')
            : $abschnitt->korrektoren()->pluck('zeugnis_schuljahr_lehrer.id');
        if ($meineLehrerIds->intersect($korrektorIds)->isNotEmpty()) {
            return 'korrektor';
        }

        return 'keine';
    }

    /** Klassenweiter Text für (Klasse, Fach) – fach_id null = Haupttext. */
    private function klassentextFuer(int $klasseId, ?int $fachId): Klassentext
    {
        $q = Klassentext::where('klasse_id', $klasseId);
        $fachId ? $q->where('fach_id', $fachId) : $q->whereNull('fach_id');

        return $q->first() ?? new Klassentext(['klasse_id' => $klasseId, 'fach_id' => $fachId]);
    }

    /** Klassenweiten Text (je Fach bzw. Haupttext) direkt bearbeiten. */
    public function klassentextEdit(Klasse $klasse, string $fach)
    {
        [$fachId, $fachModel] = $this->fachAusParam($fach);
        $this->autorisiereKlassentext($klasse, $fachId);

        return view('schulzeugnis::klassen.klassentext', [
            'klasse'      => $klasse->load('schuljahr'),
            'fach'        => $fachModel,
            'fachParam'   => $fach,
            'klassentext' => $this->klassentextFuer($klasse->id, $fachId),
        ]);
    }

    public function klassentextUpdate(Request $request, Klasse $klasse, string $fach)
    {
        [$fachId, $fachModel] = $this->fachAusParam($fach);
        $this->autorisiereKlassentext($klasse, $fachId);

        $data = $request->validate(['text' => ['nullable', 'string']]);

        $kt  = $this->klassentextFuer($klasse->id, $fachId);
        $alt = $kt->text;
        $kt->text = $data['text'] ?? null;
        $kt->save();

        if ((string) $alt !== (string) $kt->text) {
            Protokoll::log('klassentext_geaendert', [
                'schuljahr_id' => $klasse->schuljahr_id,
                'beschreibung' => 'Klassenweiter Text (' . ($fachModel?->name ?? 'Haupttext') . ') in ' . ($klasse->name ?? '') . ' geändert',
                'neu_wert'     => $kt->text,
            ]);

            // Überlauf-Analyse aller Zeugnisse der Klasse verwerfen – sie ist jetzt veraltet.
            Zeugnis::whereHas('schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
                ->update(['ueberlauf_status' => null]);
        }

        return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse)
            ->with('status', 'Klassentext (' . ($fachModel?->name ?? 'Haupttext') . ') gespeichert.');
    }

    /** Route-Parameter → [fachId|null, Fach|null]. "haupt" = Haupttext. */
    private function fachAusParam(string $fach): array
    {
        if ($fach === 'haupt') {
            return [null, null];
        }
        $model = Fach::findOrFail((int) $fach);

        return [$model->id, $model];
    }

    /** Klassentext darf ändern: Admin, Fachlehrer des Fachs bzw. Klassenlehrer (Haupttext). */
    private function autorisiereKlassentext(Klasse $klasse, ?int $fachId): void
    {
        $user = auth()->user();
        if ($user?->is_admin) {
            return;
        }

        $meineLehrerIds = Lehrer::where('schuljahr_id', $klasse->schuljahr_id)
            ->where('core_user_id', $user?->id)
            ->pluck('id');

        if ($fachId === null) {
            if ($klasse->klassenlehrer_id && $meineLehrerIds->contains($klasse->klassenlehrer_id)) {
                return;
            }
        } else {
            $fachLehrer = Lehrauftrag::where('klasse_id', $klasse->id)
                ->where('fach_id', $fachId)
                ->pluck('lehrer_id');
            if ($meineLehrerIds->intersect($fachLehrer)->isNotEmpty()) {
                return;
            }
        }

        abort(403, 'Keine Berechtigung, diesen Klassentext zu bearbeiten.');
    }

    /** Überlauf-Analyse neu berechnen und am Zeugnis zwischenspeichern. */
    private function ueberlaufNeuBerechnen(Zeugnis $zeugnis): void
    {
        $a = app(ZeugnisRenderer::class)->analyse($zeugnis);

        $zeugnis->update([
            'ueberlauf_status'    => $a['status'],
            'ueberlauf_passt_bei' => $a['passtBei'],
        ]);
    }
}
