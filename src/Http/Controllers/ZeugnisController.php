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
use Intranet\Modules\Schulzeugnis\Models\Spruch;
use Intranet\Modules\Schulzeugnis\Models\Zeugnis;
use Intranet\Modules\Schulzeugnis\Support\Ghostscript;
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

        // Hauptzeugnis: jeder Schüler bekommt automatisch eins (kein manuelles Anlegen).
        if ($klasse->hat_hauptzeugnis) {
            $klasse->loadMissing(['klassenlehrer', 'hauptbereiche']);
            foreach ($klasse->schueler()->whereDoesntHave('hauptzeugnis')->get() as $ohne) {
                $ohne->setRelation('klasse', $klasse);
                $this->erzeugeHauptzeugnis($klasse, $ohne);
            }
        }

        // Zeugnisspruch: fehlenden Spruch-Abschnitt am Container-Zeugnis nachziehen
        // (für Zeugnisse, die vor Aktivierung des Flags angelegt wurden).
        if ($klasse->hat_zeugnisspruch) {
            $klasse->loadMissing('klassenlehrer');
            $containerRel = $klasse->hat_fachzeugnis ? 'fachzeugnis' : 'hauptzeugnis';
            foreach ($klasse->schueler()->whereHas($containerRel)->with($containerRel)->get() as $s) {
                $this->spruchAbschnittAnlegen($s->getRelation($containerRel), $klasse);
            }
        }

        $schueler = $klasse->schueler()
            ->with(['fachzeugnis.abschnitte', 'hauptzeugnis.abschnitte'])
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

        $ktRows = Klassentext::where('klasse_id', $klasse->id)->with('korrektoren')->get()
            ->keyBy(fn ($kt) => $kt->fach_id === null ? 'haupt' : $kt->fach_id);
        $klassentexte = $ktRows->map(fn ($kt) => (string) $kt->text);

        // Klassenweit-Status auch für zugewiesene Korrektoren anklickbar machen (öffnet den Editor).
        $meineLehrerIds  = Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->where('core_user_id', $userId)->pluck('id');
        $ktKorrektorKeys = $ktRows->filter(fn ($kt) => $kt->korrektoren->pluck('id')->intersect($meineLehrerIds)->isNotEmpty())->keys()->all();

        // Überlauf-/Auto-Verkleinerungs-Analyse je Zeugnis (aus dem Cache; nur beim
        // ersten Mal bzw. nach Inhaltsänderungen wird gerechnet).
        $warnungen = [];
        $warnAgg   = ['fach' => false, 'haupt' => false];
        foreach ($schueler as $s) {
            $warnungen[$s->id] = ['fach' => null, 'haupt' => null];
            foreach (['fach' => $s->fachzeugnis, 'haupt' => $s->hauptzeugnis] as $k => $z) {
                if (! $z) {
                    continue;
                }
                if ($z->ueberlauf_status === null) {
                    $this->ueberlaufNeuBerechnen($z);
                }
                $warnungen[$s->id][$k] = ['status' => $z->ueberlauf_status, 'passtBei' => $z->ueberlauf_passt_bei];
                if (in_array($z->ueberlauf_status, ['verkleinert', 'ueberlauf'], true)) {
                    $warnAgg[$k] = true;
                }
            }
        }

        // Zeugnisspruch je Schüler (der eine Spruch-Abschnitt am Container-Zeugnis).
        $spruchAbschnitte = [];
        if ($klasse->hat_zeugnisspruch) {
            foreach ($schueler as $s) {
                $container = $klasse->hat_fachzeugnis ? $s->fachzeugnis : $s->hauptzeugnis;
                $spruchAbschnitte[$s->id] = $container?->abschnitte->firstWhere('typ', Abschnitt::TYP_SPRUCH);
            }
        }

        return view('schulzeugnis::zeugnisse.index', [
            'klasse'           => $klasse,
            'faecher'          => $faecher,
            'schueler'         => $schueler,
            'hatSpruch'        => (bool) $klasse->hat_zeugnisspruch,
            'spruchAbschnitte' => $spruchAbschnitte,
            'stati'            => Abschnitt::STATI,
            'meineFachIds'     => $meineFachIds,
            'binKlassenlehrer' => $binKlassenlehrer,
            'warnungen'        => $warnungen,
            'warnAgg'          => $warnAgg,
            'fachlehrer'       => $fachlehrer,
            'klassentexte'     => $klassentexte,
            'ktRows'           => $ktRows,
            'ktKorrektorKeys'  => $ktKorrektorKeys,
            'hatFach'          => (bool) $klasse->hat_fachzeugnis,
            'hatHaupt'         => (bool) $klasse->hat_hauptzeugnis,
            'bereiche'         => $klasse->hat_hauptzeugnis ? $klasse->hauptbereiche : collect(),
            'istAdmin'         => (bool) auth()->user()?->is_admin,
            'gsVerfuegbar'     => Ghostscript::verfuegbar(),
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
        $zeugnis->loadMissing(['format', 'schueler.klasse.schuljahr']);
        $format = $zeugnis->format;

        [$groesse, $lage] = $format && $format->broschuere
            ? ['a3', 'landscape']
            : [$format && $format->seitenformat === 'a3' ? 'a3' : 'a4', $format && $format->ausrichtung === 'quer' ? 'landscape' : 'portrait'];

        $r = $renderer->render($zeugnis);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulzeugnis::formate.render', ['seiten' => $r['seiten'], 'daten' => $r['daten']])
            ->setPaper($groesse, $lage)
            ->setOption('tempDir', \Intranet\Modules\Schulzeugnis\Support\PdfTemp::dir());

        $typLabel  = $zeugnis->istHaupt() ? 'Hauptzeugnis' : 'Fachzeugnis';
        $schuljahr = $zeugnis->schueler?->klasse?->schuljahr?->name ?? '';
        $name      = $zeugnis->schueler?->fullName() ?: 'Schueler';

        return $pdf->stream($this->pdfDateiname($typLabel, $schuljahr, $name));
    }

    /** Dateisystem-sicherer PDF-Dateiname: Typ_Schuljahr_Name (Sonderzeichen bereinigt). */
    private function pdfDateiname(string $typLabel, string $schuljahr, string $name): string
    {
        $sauber = fn ($s) => trim(str_replace(' ', '_', preg_replace('#[/\\\\:*?"<>|]+#', '-', (string) $s)), '_-');

        return "{$typLabel}_" . $sauber($schuljahr) . '_' . $sauber($name) . '.pdf';
    }

    /** Alle Zeugnisse eines Typs (fach|haupt) einer Klasse – in Schüler-Reihenfolge. */
    private function sammelZeugnisse(Klasse $klasse, string $typ)
    {
        $typWert = $typ === 'haupt' ? Zeugnis::TYP_HAUPT : Zeugnis::TYP_FACH;

        return Zeugnis::where('typ', $typWert)
            ->whereHas('schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
            ->with(['schueler', 'format', 'abschnitte'])
            ->get()
            ->sortBy(fn ($z) => sprintf('%s|%s', $z->schueler?->nachname ?? '', $z->schueler?->vorname ?? ''))
            ->values();
    }

    /** HTML-Vorschau aller Zeugnisse eines Typs einer Klasse hintereinander. */
    public function sammelVorschau(Klasse $klasse, string $typ, ZeugnisRenderer $renderer)
    {
        $seiten = [];
        foreach ($this->sammelZeugnisse($klasse, $typ) as $z) {
            $seiten = array_merge($seiten, $renderer->render($z)['seiten']);
        }

        return view('schulzeugnis::formate.render', ['seiten' => $seiten, 'daten' => []]);
    }

    /** Alle Zeugnisse eines Typs einer Klasse gebündelt als eine PDF. */
    public function sammelPdf(Klasse $klasse, string $typ, ZeugnisRenderer $renderer)
    {
        $seiten = [];
        $format = null;
        foreach ($this->sammelZeugnisse($klasse, $typ) as $z) {
            $seiten = array_merge($seiten, $renderer->render($z)['seiten']);
            $format ??= $z->format;
        }

        [$groesse, $lage] = $this->papier($format);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulzeugnis::formate.render', ['seiten' => $seiten, 'daten' => []])
            ->setPaper($groesse, $lage)
            ->setOption('tempDir', \Intranet\Modules\Schulzeugnis\Support\PdfTemp::dir());

        $typLabel  = $typ === 'haupt' ? 'Hauptzeugnisse' : 'Fachzeugnisse';
        $schuljahr = $klasse->loadMissing('schuljahr')->schuljahr?->name ?? '';

        return $pdf->stream($this->pdfDateiname($typLabel, $schuljahr, 'Klasse-' . $klasse->name));
    }

    /** Papierformat/-lage aus einem Format ableiten (für dompdf setPaper). */
    private function papier(?Format $format): array
    {
        return $format && $format->broschuere
            ? ['a3', 'landscape']
            : [$format && $format->seitenformat === 'a3' ? 'a3' : 'a4', $format && $format->ausrichtung === 'quer' ? 'landscape' : 'portrait'];
    }

    /**
     * Fehlende Zeugnisse eines Schülers anlegen – je nach Klassen-Konfiguration ein
     * Fach- und/oder Hauptzeugnis – und deren Abschnitte automatisch erzeugen.
     */
    public function store(Klasse $klasse, Schueler $schueler)
    {
        $schueler->setRelation('klasse', $klasse);
        $klasse->loadMissing('klassenlehrer');

        $angelegt = [];
        if ($klasse->hat_fachzeugnis && ! $schueler->fachzeugnis) {
            $this->erzeugeFachzeugnis($klasse, $schueler);
            $angelegt[] = 'Fachzeugnis';
        }
        if ($klasse->hat_hauptzeugnis && ! $schueler->hauptzeugnis) {
            $this->erzeugeHauptzeugnis($klasse, $schueler);
            $angelegt[] = 'Hauptzeugnis';
        }

        $meldung = $angelegt
            ? implode(' + ', $angelegt) . " für {$schueler->fullName()} angelegt."
            : "Für {$schueler->fullName()} war nichts anzulegen.";

        return redirect()
            ->route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse)
            ->with('status', $meldung);
    }

    /** Fachzeugnis + je Fach (mit Lehrauftrag) einen Fach- bzw. Notenabschnitt. */
    private function erzeugeFachzeugnis(Klasse $klasse, Schueler $schueler): void
    {
        $formatId = $schueler->effektivesFormatId();
        $istNoten = $formatId && Format::find($formatId)?->typ === 'noten';

        $zeugnis = Zeugnis::create([
            'schueler_id' => $schueler->id,
            'typ'         => Zeugnis::TYP_FACH,
            'format_id'   => $formatId,
            'status'      => Zeugnis::STATUS_ENTWURF,
        ]);

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
                'status'      => Abschnitt::STATUS_STANDARD,
            ]);
        }

        // Zeugnisspruch (falls aktiviert) lebt am Fachzeugnis.
        if ($klasse->hat_zeugnisspruch) {
            $this->spruchAbschnittAnlegen($zeugnis, $klasse);
        }

        Protokoll::log('zeugnis_angelegt', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Fachzeugnis für {$schueler->fullName()} angelegt",
        ]);

        $this->ueberlaufNeuBerechnen($zeugnis);
    }

    /**
     * Legt den einen Zeugnisspruch-Abschnitt am Container-Zeugnis an, falls noch keiner
     * existiert. Autor = Klassenlehrer (nur er darf ihn bearbeiten). Inhalt kommt später
     * per Katalog-Auswahl bzw. freier Eingabe im Editor.
     */
    private function spruchAbschnittAnlegen(Zeugnis $zeugnis, Klasse $klasse): void
    {
        if ($zeugnis->abschnitte()->where('typ', Abschnitt::TYP_SPRUCH)->exists()) {
            return;
        }

        $zeugnis->abschnitte()->create([
            'typ'             => Abschnitt::TYP_SPRUCH,
            'autor_lehrer_id' => $klasse->klassenlehrer_id,
            'autor_name'      => $klasse->klassenlehrer?->fullName(),
            'reihenfolge'     => 900, // hinter den Fächern
            'status'          => Abschnitt::STATUS_STANDARD,
        ]);
    }

    /** Hauptzeugnis = EIN Abschnitt (Status/Korrektoren/Klassentext), je Fachbereich ein Schülertext. */
    private function erzeugeHauptzeugnis(Klasse $klasse, Schueler $schueler): void
    {
        $zeugnis = Zeugnis::create([
            'schueler_id' => $schueler->id,
            'typ'         => Zeugnis::TYP_HAUPT,
            'format_id'   => $klasse->hauptzeugnis_format_id,
            'status'      => Zeugnis::STATUS_ENTWURF,
        ]);

        $abschnitt = $zeugnis->abschnitte()->create([
            'typ'             => Abschnitt::TYP_HAUPTZEUGNIS,
            'autor_lehrer_id' => $klasse->klassenlehrer_id,
            'autor_name'      => $klasse->klassenlehrer?->fullName(),
            'reihenfolge'     => 0,
            'status'          => Abschnitt::STATUS_STANDARD,
        ]);

        $this->syncBereichtexte($abschnitt, $klasse);

        // Nur bei reinen Hauptzeugnis-Klassen lebt der Spruch hier (sonst am Fachzeugnis).
        if ($klasse->hat_zeugnisspruch && ! $klasse->hat_fachzeugnis) {
            $this->spruchAbschnittAnlegen($zeugnis, $klasse);
        }

        Protokoll::log('zeugnis_angelegt', [
            'schuljahr_id' => $klasse->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'beschreibung' => "Hauptzeugnis für {$schueler->fullName()} angelegt",
        ]);

        $this->ueberlaufNeuBerechnen($zeugnis);
    }

    /**
     * Legt zu jedem aktuellen Fachbereich der Klasse eine Bereichtext-Zeile am HAU-Abschnitt
     * an (falls noch nicht vorhanden) – so erscheinen später ergänzte Bereiche automatisch.
     */
    private function syncBereichtexte(Abschnitt $abschnitt, Klasse $klasse): void
    {
        $vorhanden = $abschnitt->bereichtexte()->pluck('bereich_id')->all();

        foreach ($klasse->hauptbereiche as $bereich) {
            if (! in_array($bereich->id, $vorhanden, true)) {
                $abschnitt->bereichtexte()->create([
                    'bereich_id'   => $bereich->id,
                    'bereich_name' => $bereich->name,
                    'reihenfolge'  => $bereich->reihenfolge,
                ]);
            }
        }
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

    /** Einzelnen Abschnitt (Fachtext/Note/Fachbereich) bearbeiten – mit Änderungsverlauf. */
    public function abschnittEdit(Abschnitt $abschnitt)
    {
        $abschnitt->load(['zeugnis.schueler.klasse.schuljahr', 'fach', 'bereichtexte.bereich', 'korrektoren']);
        $zeugnis = $abschnitt->zeugnis;
        $klasse  = $zeugnis->schueler?->klasse;

        // Hauptzeugnis: fehlende Fachbereich-Textfelder ergänzen (falls Bereiche dazukamen).
        if ($abschnitt->typ === Abschnitt::TYP_HAUPTZEUGNIS && $klasse && ! $zeugnis->istAbgeschlossen()) {
            $this->syncBereichtexte($abschnitt, $klasse->loadMissing('hauptbereiche'));
            $abschnitt->load('bereichtexte.bereich');
        }

        $hex = self::STATUS_FARBE_HEX;
        $verlauf = Protokoll::where('abschnitt_id', $abschnitt->id)
            ->whereIn('aktion', ['abschnitt_geaendert', 'abschnitt_status', 'abschnitt_notiz', 'abschnitt_klassentext', 'abschnitt_wiederhergestellt', 'abschnitt_klassentext_wiederhergestellt', 'abschnitt_korrektor_hinzugefuegt', 'abschnitt_korrektor_entfernt'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Protokoll $e) use ($hex) {
                $istStatus  = $e->aktion === 'abschnitt_status';
                $istRestore = in_array($e->aktion, ['abschnitt_wiederhergestellt', 'abschnitt_klassentext_wiederhergestellt'], true);
                $istMeta    = in_array($e->aktion, ['abschnitt_korrektor_hinzugefuegt', 'abschnitt_korrektor_entfernt'], true);
                $wz = fn ($s) => trim((string) $s) === '' ? 0 : count(preg_split('/\s+/u', trim((string) $s)));

                $status  = null;
                $summary = '';

                if ($istMeta) {
                    // Korrektor-Änderung: die Beschreibung sagt alles, kein Text-Diff.
                } elseif ($istStatus) {
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
                    'istMeta'           => $istMeta,
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

        // „Vorherige Zeile": oberhalb des ersten Schülers steht in der Tabelle die
        // Klassenweit-Zeile – dorthin (Klassentext desselben Fachs) kann man hoch springen.
        $ktParam = $this->klassentextParamFuerAbschnitt($abschnitt);
        $klassentextZeileUrl = ($ktParam !== null && $klasse)
            ? route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $ktParam])
            : null;

        return view('schulzeugnis::zeugnisse.abschnitt', [
            'quelle'         => $quelle,
            'zurueck'        => $zurueck,
            'abschnitt'      => $abschnitt,
            'zeugnis'        => $zeugnis,
            'schueler'       => $zeugnis->schueler,
            'stati'          => Abschnitt::STATI,
            'verlauf'        => $verlauf,
            'klassentext'    => $this->klassentextAnzeige($abschnitt, $klasse),
            'klassentextZeileUrl' => $klassentextZeileUrl,
            'bereichtexte'   => $abschnitt->typ === Abschnitt::TYP_HAUPTZEUGNIS ? $abschnitt->bereichtexte : collect(),
            'berechtigung'   => $berechtigung,
            'korrekturStati' => self::KORREKTUR_STATI,
            'alleLehrer'     => $klasse ? Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->whereNotIn('id', $this->meineLehrerIds($klasse))->orderBy('nachname')->orderBy('vorname')->get() : collect(),
            'korrektorIds'   => $abschnitt->korrektoren->pluck('id')->diff($this->meineLehrerIds($klasse))->values()->all(),
            'readonly'       => $zeugnis->istAbgeschlossen() || $berechtigung === 'keine',
            'navPrev'        => $nachbarn['prev'],
            'navNext'        => $nachbarn['next'],
            'navPosition'    => $nachbarn['position'],
            'navGesamt'      => $nachbarn['gesamt'],
            'sprueche'       => $abschnitt->typ === Abschnitt::TYP_SPRUCH
                ? Spruch::where('aktiv', true)->orderBy('reihenfolge')->orderBy('id')->get()
                : collect(),
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

        $istHaupt = $abschnitt->typ === Abschnitt::TYP_HAUPTZEUGNIS;
        $kette = $klasse->schueler()
            ->with([$istHaupt ? 'hauptzeugnis.abschnitte' : 'fachzeugnis.abschnitte'])
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get()
            ->map(function ($s) use ($abschnitt, $istHaupt) {
                $z = $istHaupt ? $s->hauptzeugnis : $s->fachzeugnis;
                $treffer = $z?->abschnitte->first(
                    fn ($a) => $istHaupt
                        ? $a->typ === Abschnitt::TYP_HAUPTZEUGNIS
                        : ($a->typ === $abschnitt->typ && $a->fach_id === $abschnitt->fach_id)
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
        $abschnitt->load('zeugnis.schueler.klasse', 'fach', 'bereich', 'korrektoren');
        $zeugnis = $abschnitt->zeugnis;
        $klasse  = $zeugnis->schueler?->klasse;
        $b       = $this->berechtigung($abschnitt, auth()->user());

        // Für die Rückmeldung: wessen Text (Name + Fach) tatsächlich gespeichert wurde
        // – wichtig beim Blättern, wo danach schon der nächste Schüler angezeigt wird.
        $gespeichertName = $zeugnis->schueler?->fullName() ?: 'Schüler';
        $gespeichertWas  = match ($abschnitt->typ) {
            Abschnitt::TYP_HAUPTZEUGNIS => 'Hauptzeugnis',
            Abschnitt::TYP_HAUPTTEXT    => 'Haupttext',
            Abschnitt::TYP_SPRUCH       => 'Zeugnisspruch',
            default                     => $abschnitt->fach?->name ?? 'Fachtext',
        };

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
                'notiz'  => ['nullable', 'string'],
                'status' => ['required', Rule::in(self::KORREKTUR_STATI)],
                'weiter' => ['nullable', Rule::in(['next', 'prev', 'index', 'klassentext'])],
            ]);

            $altInhalt = $abschnitt->inhalt;
            $altNotiz  = $abschnitt->notiz;
            $altStatus = $abschnitt->status;
            $abschnitt->inhalt = $data['inhalt'] ?? null;
            if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
                $abschnitt->note = $data['note'] ?? null;
            }
            $abschnitt->notiz = $data['notiz'] ?? null;
            $abschnitt->status = $data['status'];
            $abschnitt->save();

            $textFeld = $abschnitt->typ === Abschnitt::TYP_NOTE ? 'Note' : 'Schülertext';
            $this->logFeld($abschnitt, $textFeld, $altInhalt, $abschnitt->inhalt);
            $this->logFeld($abschnitt, 'Notiz', $altNotiz, $abschnitt->notiz, 'abschnitt_notiz');
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
            'weiter'        => ['nullable', Rule::in(['next', 'prev', 'index', 'klassentext'])],
        ]);

        // Korrektor-Pflicht nur beim normalen Speichern erzwingen. Beim Blättern
        // („Speichern & vorheriger/nächster") nicht blockieren – dann wird gespeichert
        // und einfach zum Nachbarn gewechselt, Korrektoren können später folgen.
        $korrektoren = $this->ohneEigeneLehrer($data['korrektoren'] ?? [], $klasse);
        $blaettert   = in_array((string) $request->input('weiter'), ['next', 'prev', 'index'], true);
        if (! $blaettert && in_array($data['status'], self::BRAUCHT_KORREKTOREN, true) && empty($korrektoren)) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->withInput()
                ->with('error', 'Bitte mindestens einen Korrektor auswählen, wenn der Text zur Korrektur freigegeben wird.');
        }

        $altInhalt = $abschnitt->inhalt;
        $altNotiz  = $abschnitt->notiz;
        $altStatus = $abschnitt->status;

        $istHaupt = $abschnitt->typ === Abschnitt::TYP_HAUPTZEUGNIS;
        if (! $istHaupt) {
            $abschnitt->inhalt = $data['inhalt'] ?? null;
        }
        if ($abschnitt->typ === Abschnitt::TYP_NOTE) {
            $abschnitt->note = $data['note'] ?? null;
        }
        $abschnitt->status = $data['status'];
        $abschnitt->notiz = $data['notiz'] ?? null;
        $abschnitt->klassentext_neue_zeile = $request->boolean('klassentext_neue_zeile');
        $abschnitt->save();

        $altKorrektoren = $abschnitt->korrektoren->pluck('id')->all();
        $abschnitt->korrektoren()->sync($korrektoren);
        $this->protokolliereKorrektoren([
            'schuljahr_id' => $zeugnis->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis->id,
            'abschnitt_id' => $abschnitt->id,
        ], 'abschnitt_korrektor', $altKorrektoren, $korrektoren);

        if ($istHaupt) {
            // Hauptzeugnis: die mehreren Schülertexte (je Fachbereich) speichern.
            $btEingaben = $request->input('bereichtexte', []);
            foreach ($abschnitt->bereichtexte as $bt) {
                if (! array_key_exists($bt->id, $btEingaben)) {
                    continue;
                }
                $altBt = $bt->inhalt;
                $bt->inhalt = $btEingaben[$bt->id]['inhalt'] ?? null;
                $bt->save();
                $this->logFeld($abschnitt, $bt->ueberschrift(), $altBt, $bt->inhalt);
            }
        } else {
            $textFeld = $abschnitt->typ === Abschnitt::TYP_NOTE ? 'Note' : 'Schülertext';
            $this->logFeld($abschnitt, $textFeld, $altInhalt, $abschnitt->inhalt);
        }
        $this->logFeld($abschnitt, 'Notiz', $altNotiz, $abschnitt->notiz, 'abschnitt_notiz');
        $this->logStatus($abschnitt, $altStatus, $abschnitt->status);

        // Klassenweiter Text – gilt für alle Schüler der Klasse (je Fach bzw. Fachbereich).
        $neu = $data['klassentext'] ?? null;
        [$altKt, $klassentextGeaendert] = $this->klassentextSpeichern($abschnitt, $klasse, $neu);
        if ($klassentextGeaendert) {
            $this->logFeld($abschnitt, 'Klassenweiter Text', $altKt, $neu, 'abschnitt_klassentext');
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
        if ($weiter === 'klassentext') {
            // Eine Zeile hoch: in die Klassenweit-Zeile desselben Fachs.
            $ktParam = $this->klassentextParamFuerAbschnitt($abschnitt);
            if ($ktParam !== null && $klasse) {
                return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $ktParam]);
            }
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

    /**
     * Korrektor lehnt die Korrektur ab: er entfernt sich selbst aus den Korrektoren
     * und der Status geht zurück auf „Frei zur Korrektur" – die verantwortliche
     * Lehrkraft kann dann eine andere Korrektorin/einen anderen Korrektor wählen.
     */
    public function abschnittAblehnen(Abschnitt $abschnitt)
    {
        $abschnitt->load('zeugnis.schueler.klasse', 'korrektoren');
        $zeugnis = $abschnitt->zeugnis;
        $klasse  = $zeugnis?->schueler?->klasse;

        if ($zeugnis?->istAbgeschlossen()) {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Das Zeugnis ist abgeschlossen.');
        }
        if ($this->berechtigung($abschnitt, auth()->user()) !== 'korrektor') {
            return redirect()->route('module.schulzeugnis.klassenraeume.abschnitte.edit', $abschnitt)
                ->with('error', 'Nur ein zugewiesener Korrektor kann die Korrektur ablehnen.');
        }

        $meine = $klasse
            ? Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->where('core_user_id', auth()->id())->pluck('id')->all()
            : [];
        $alt = $abschnitt->korrektoren->pluck('id')->all();
        $neu = array_values(array_diff($alt, $meine));

        $abschnitt->korrektoren()->sync($neu);
        $this->protokolliereKorrektoren([
            'schuljahr_id' => $zeugnis?->schueler?->schuljahr_id,
            'zeugnis_id'   => $zeugnis?->id,
            'abschnitt_id' => $abschnitt->id,
        ], 'abschnitt_korrektor', $alt, $neu);

        $altStatus = $abschnitt->status;
        $abschnitt->status = 'frei_zur_korrektur';
        $abschnitt->save();
        $this->logStatus($abschnitt, $altStatus, $abschnitt->status);

        return redirect()->route('module.schulzeugnis.todo.index')
            ->with('status', 'Korrektur abgelehnt – der Text ist zurück bei der Lehrkraft.');
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

        if (in_array($abschnitt->typ, [Abschnitt::TYP_HAUPTTEXT, Abschnitt::TYP_HAUPTZEUGNIS, Abschnitt::TYP_SPRUCH], true)) {
            // Haupttext / Hauptzeugnis / Zeugnisspruch: nur der Klassenlehrer ist voll berechtigt.
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

    /** Lehrer-IDs, die zum eingeloggten Nutzer im Schuljahr der Klasse gehören. */
    private function meineLehrerIds(?Klasse $klasse): \Illuminate\Support\Collection
    {
        if (! $klasse) {
            return collect();
        }

        return Lehrer::where('schuljahr_id', $klasse->schuljahr_id)
            ->where('core_user_id', auth()->id())
            ->pluck('id');
    }

    /** Eigene Lehrer-IDs aus einer Korrektoren-Auswahl entfernen (man korrigiert sich nicht selbst). */
    private function ohneEigeneLehrer(array $ids, ?Klasse $klasse): array
    {
        $meine = $this->meineLehrerIds($klasse)->map(fn ($i) => (int) $i)->all();

        return array_values(array_diff(array_map('intval', $ids), $meine));
    }

    /**
     * Klassentext-Route-Parameter (fach-id | 'haupt') für die „Vorherige Zeile"-Navigation
     * eines Abschnitts. null bei Hauptzeugnis/Fachbereich (dort kein einzelner Fach-Klassentext).
     */
    private function klassentextParamFuerAbschnitt(Abschnitt $abschnitt): ?string
    {
        if (in_array($abschnitt->typ, [Abschnitt::TYP_HAUPTZEUGNIS, Abschnitt::TYP_FACHBEREICH, Abschnitt::TYP_SPRUCH], true)) {
            return null;
        }

        return $abschnitt->fach_id === null ? 'haupt' : (string) $abschnitt->fach_id;
    }

    /** Klassentext-Objekt (mit ->text) für die Editor-Anzeige – je Fach oder Fachbereich. */
    private function klassentextAnzeige(Abschnitt $abschnitt, ?Klasse $klasse): ?object
    {
        if ($abschnitt->typ === Abschnitt::TYP_FACHBEREICH) {
            return (object) ['text' => $abschnitt->bereich?->klassentext];
        }

        // Zeugnisspruch nutzt keinen Fach-/Haupt-Klassentext (sein klassenweiter Text ist eine
        // eigene Schiene, art='spruch').
        if ($abschnitt->typ === Abschnitt::TYP_SPRUCH) {
            return null;
        }

        return $klasse ? $this->klassentextFuer($klasse->id, $abschnitt->fach_id) : null;
    }

    /**
     * Klassentext speichern – Fach → zeugnis_fach_klassentexte, Fachbereich → am Bereich.
     *
     * @return array{0:?string,1:bool} [alter Wert, wurde geändert]
     */
    private function klassentextSpeichern(Abschnitt $abschnitt, ?Klasse $klasse, ?string $neu): array
    {
        // Zeugnisspruch fasst keinen Fach-/Haupt-Klassentext an.
        if ($abschnitt->typ === Abschnitt::TYP_SPRUCH) {
            return [null, false];
        }

        if ($abschnitt->typ === Abschnitt::TYP_FACHBEREICH) {
            $bereich = $abschnitt->bereich;
            if (! $bereich) {
                return [null, false];
            }
            $alt = $bereich->klassentext;
            if ((string) $alt === (string) $neu) {
                return [$alt, false];
            }
            $bereich->klassentext = $neu;
            $bereich->save();

            return [$alt, true];
        }

        if (! $klasse) {
            return [null, false];
        }
        $kt  = $this->klassentextFuer($klasse->id, $abschnitt->fach_id);
        $alt = $kt->text;
        if ((string) $alt === (string) $neu) {
            return [$alt, false];
        }
        $kt->text = $neu;
        $kt->save();

        return [$alt, true];
    }

    /** Klassenweiter Text für (Klasse, Fach) – fach_id null = Haupttext. */
    private function klassentextFuer(int $klasseId, ?int $fachId): Klassentext
    {
        $q = Klassentext::where('klasse_id', $klasseId);
        $fachId ? $q->where('fach_id', $fachId) : $q->whereNull('fach_id');

        return $q->first() ?? new Klassentext(['klasse_id' => $klasseId, 'fach_id' => $fachId]);
    }

    /**
     * Klassenweiten Text (je Fach bzw. Haupttext) direkt bearbeiten – verhält sich
     * wie der Abschnitt-Editor (Notiz, Korrektoren, Status, Verlauf, Navigation),
     * nur mit EINEM Textfeld statt Schüler-/Klassentext-Tabs.
     */
    public function klassentextEdit(Klasse $klasse, string $fach)
    {
        [$fachId, $fachModel] = $this->fachAusParam($fach);
        $klasse->loadMissing('schuljahr');
        $kt = $this->klassentextFuer($klasse->id, $fachId);
        if ($kt->exists) {
            $kt->load('korrektoren');
        }

        $berechtigung = $this->klassentextBerechtigung($kt, $fachId, $klasse, auth()->user());
        if ($berechtigung === 'keine') {
            abort(403, 'Keine Berechtigung, diesen Klassentext zu bearbeiten.');
        }

        $nachbarn = $this->klassentextNachbarn($klasse, $fachId);

        return view('schulzeugnis::klassen.klassentext', [
            'klasse'         => $klasse,
            'fach'           => $fachModel,
            'fachParam'      => $fach,
            'klassentext'    => $kt,
            'stati'          => Abschnitt::STATI,
            'korrekturStati' => self::KORREKTUR_STATI,
            'verlauf'        => $this->klassentextVerlauf($kt),
            'berechtigung'   => $berechtigung,
            'alleLehrer'     => Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->whereNotIn('id', $this->meineLehrerIds($klasse))->orderBy('nachname')->orderBy('vorname')->get(),
            'korrektorIds'   => $kt->exists ? $kt->korrektoren->pluck('id')->diff($this->meineLehrerIds($klasse))->values()->all() : [],
            'readonly'       => false,
            'navPrev'        => $nachbarn['prev'],
            'navNext'        => $nachbarn['next'],
            'navPosition'    => $nachbarn['position'],
            'navGesamt'      => $nachbarn['gesamt'],
        ]);
    }

    public function klassentextUpdate(Request $request, Klasse $klasse, string $fach)
    {
        [$fachId, $fachModel] = $this->fachAusParam($fach);
        $klasse->loadMissing('schuljahr');
        $kt = $this->klassentextFuer($klasse->id, $fachId);
        if ($kt->exists) {
            $kt->load('korrektoren');
        }
        $b     = $this->klassentextBerechtigung($kt, $fachId, $klasse, auth()->user());
        $label = $fachModel?->name ?? 'Haupttext';

        if ($b === 'keine') {
            return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach])
                ->with('error', 'Du bist für diesen Klassentext nicht berechtigt.');
        }

        // Korrektor: nur Text korrigieren + Korrektur-Status.
        if ($b === 'korrektor') {
            $data = $request->validate([
                'text'   => ['nullable', 'string'],
                'notiz'  => ['nullable', 'string'],
                'status' => ['required', Rule::in(self::KORREKTUR_STATI)],
                'weiter' => ['nullable', Rule::in(['next', 'prev', 'index', 'klassentext'])],
            ]);

            $altText   = $kt->text;
            $altNotiz  = $kt->notiz;
            $altStatus = $kt->status;
            $kt->text = $data['text'] ?? null;
            $kt->notiz = $data['notiz'] ?? null;
            $kt->status = $data['status'];
            $kt->save();

            $this->logKlassentext($kt, $klasse->schuljahr_id, 'Klassenweiter Text', $altText, $kt->text);
            $this->logKlassentext($kt, $klasse->schuljahr_id, 'Notiz', $altNotiz, $kt->notiz, 'klassentext_notiz');
            $this->logKlassentext($kt, $klasse->schuljahr_id, 'Status', $altStatus ?? 'unbearbeitet', $kt->status, 'klassentext_status');
            $this->klassentextUeberlaufVerwerfen($klasse, $altText, $kt->text);

            return $this->klassentextZiel($request, $klasse, $fachId, $label, $b);
        }

        // Voll berechtigt: Text, Notiz, Status, Korrektoren.
        $data = $request->validate([
            'text'          => ['nullable', 'string'],
            'notiz'         => ['nullable', 'string'],
            'status'        => ['required', Rule::in(array_keys(Abschnitt::STATI))],
            'korrektoren'   => ['array'],
            'korrektoren.*' => ['integer', Rule::exists('zeugnis_schuljahr_lehrer', 'id')],
            'weiter'        => ['nullable', Rule::in(['next', 'prev', 'index', 'klassentext'])],
        ]);

        // Korrektor-Pflicht nur beim reinen Speichern erzwingen (beim Blättern nicht blockieren).
        $korrektoren = $this->ohneEigeneLehrer($data['korrektoren'] ?? [], $klasse);
        $blaettert   = in_array((string) $request->input('weiter'), ['next', 'prev', 'index'], true);
        if (! $blaettert && in_array($data['status'], self::BRAUCHT_KORREKTOREN, true) && empty($korrektoren)) {
            return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach])
                ->withInput()
                ->with('error', 'Bitte mindestens einen Korrektor auswählen, wenn der Text zur Korrektur freigegeben wird.');
        }

        $altText   = $kt->text;
        $altNotiz  = $kt->notiz;
        $altStatus = $kt->status;
        $kt->text = $data['text'] ?? null;
        $kt->notiz = $data['notiz'] ?? null;
        $kt->status = $data['status'];
        $kt->save();

        $altKorrektoren = ($kt->relationLoaded('korrektoren')) ? $kt->korrektoren->pluck('id')->all() : [];
        $kt->korrektoren()->sync($korrektoren);
        $this->protokolliereKorrektoren([
            'schuljahr_id'   => $klasse->schuljahr_id,
            'klassentext_id' => $kt->id,
        ], 'klassentext_korrektor', $altKorrektoren, $korrektoren);

        $this->logKlassentext($kt, $klasse->schuljahr_id, 'Klassenweiter Text', $altText, $kt->text);
        $this->logKlassentext($kt, $klasse->schuljahr_id, 'Notiz', $altNotiz, $kt->notiz, 'klassentext_notiz');
        $this->logKlassentext($kt, $klasse->schuljahr_id, 'Status', $altStatus ?? 'unbearbeitet', $kt->status, 'klassentext_status');
        $this->klassentextUeberlaufVerwerfen($klasse, $altText, $kt->text);

        return $this->klassentextZiel($request, $klasse, $fachId, $label, $b);
    }

    /** Einen früheren Textstand eines Klassentextes aus dem Verlauf wiederherstellen. */
    public function klassentextWiederherstellen(Request $request, Klasse $klasse, string $fach)
    {
        [$fachId] = $this->fachAusParam($fach);
        $klasse->loadMissing('schuljahr');
        $kt = $this->klassentextFuer($klasse->id, $fachId);
        if ($kt->exists) {
            $kt->load('korrektoren');
        }

        if ($this->klassentextBerechtigung($kt, $fachId, $klasse, auth()->user()) !== 'voll') {
            return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach])
                ->with('error', 'Nur die verantwortliche Lehrkraft kann frühere Stände wiederherstellen.');
        }

        $eintrag = Protokoll::where('klassentext_id', $kt->id)
            ->whereIn('aktion', ['klassentext_geaendert', 'klassentext_wiederhergestellt'])
            ->findOrFail((int) $request->input('protokoll_id'));

        $ziel = $eintrag->alt_wert;
        $alt  = $kt->text;
        $kt->text = $ziel;
        $kt->save();

        $this->logKlassentext($kt, $klasse->schuljahr_id, 'Klassenweiter Text', $alt, $ziel, 'klassentext_wiederhergestellt');
        $this->klassentextUeberlaufVerwerfen($klasse, $alt, $ziel);

        return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach])
            ->with('status', 'Früherer Klassentext-Stand wiederhergestellt.');
    }

    /** Korrektor lehnt die Korrektur eines Klassentexts ab (siehe abschnittAblehnen). */
    public function klassentextAblehnen(Klasse $klasse, string $fach)
    {
        [$fachId] = $this->fachAusParam($fach);
        $klasse->loadMissing('schuljahr');
        $kt = $this->klassentextFuer($klasse->id, $fachId);
        if ($kt->exists) {
            $kt->load('korrektoren');
        }

        if ($this->klassentextBerechtigung($kt, $fachId, $klasse, auth()->user()) !== 'korrektor') {
            return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fach])
                ->with('error', 'Nur ein zugewiesener Korrektor kann die Korrektur ablehnen.');
        }

        $meine = Lehrer::where('schuljahr_id', $klasse->schuljahr_id)->where('core_user_id', auth()->id())->pluck('id')->all();
        $alt   = $kt->korrektoren->pluck('id')->all();
        $neu   = array_values(array_diff($alt, $meine));

        $kt->korrektoren()->sync($neu);
        $this->protokolliereKorrektoren([
            'schuljahr_id'   => $klasse->schuljahr_id,
            'klassentext_id' => $kt->id,
        ], 'klassentext_korrektor', $alt, $neu);

        $altStatus = $kt->status;
        $kt->status = 'frei_zur_korrektur';
        $kt->save();
        $this->logKlassentext($kt, $klasse->schuljahr_id, 'Status', $altStatus ?? 'unbearbeitet', $kt->status, 'klassentext_status');

        return redirect()->route('module.schulzeugnis.todo.index')
            ->with('status', 'Korrektur abgelehnt – der Klassentext ist zurück bei der Lehrkraft.');
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

    /**
     * Berechtigung für einen Klassentext: 'voll' | 'korrektor' | 'keine' – analog
     * zu {@see berechtigung()} für Abschnitte.
     */
    private function klassentextBerechtigung(Klassentext $kt, ?int $fachId, Klasse $klasse, $user): string
    {
        if (! $user) {
            return 'keine';
        }
        if ($user->is_admin) {
            return 'voll';
        }

        $meineLehrerIds = Lehrer::where('schuljahr_id', $klasse->schuljahr_id)
            ->where('core_user_id', $user->id)
            ->pluck('id');

        if ($fachId === null) {
            if ($klasse->klassenlehrer_id && $meineLehrerIds->contains($klasse->klassenlehrer_id)) {
                return 'voll';
            }
        } else {
            $fachLehrer = Lehrauftrag::where('klasse_id', $klasse->id)
                ->where('fach_id', $fachId)
                ->pluck('lehrer_id');
            if ($meineLehrerIds->intersect($fachLehrer)->isNotEmpty()) {
                return 'voll';
            }
        }

        $korrektorIds = $kt->exists
            ? ($kt->relationLoaded('korrektoren') ? $kt->korrektoren->pluck('id') : $kt->korrektoren()->pluck('zeugnis_schuljahr_lehrer.id'))
            : collect();
        if ($meineLehrerIds->intersect($korrektorIds)->isNotEmpty()) {
            return 'korrektor';
        }

        return 'keine';
    }

    /**
     * Vor-/nächstes Fach der Klasse für die „Danach weiter"-Navigation (gleiche
     * Reihenfolge wie die Spalten der Zeugnis-Tabelle). Haupttext hat keine Nachbarn.
     *
     * @return array{prev:?array{param:string,name:string},next:?array{param:string,name:string},position:?int,gesamt:?int}
     */
    private function klassentextNachbarn(Klasse $klasse, ?int $fachId): array
    {
        $leer = ['prev' => null, 'next' => null, 'position' => null, 'gesamt' => null];
        if ($fachId === null) {
            return $leer;
        }

        $fachIds = $klasse->lehrauftraege()->distinct()->pluck('fach_id');
        $kette   = Fach::whereIn('id', $fachIds)->orderBy('reihenfolge')->orderBy('name')->get()
            ->map(fn ($f) => ['param' => (string) $f->id, 'name' => $f->name])
            ->values();

        $idx = $kette->search(fn ($e) => $e['param'] === (string) $fachId);
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

    /**
     * Redirect nach dem Speichern gemäß Auswahl „weiter" (nächstes/voriges Fach bzw.
     * Zeugnis-Tabelle). Korrektoren dürfen NICHT zu Nachbar-Fächern springen (dort sind
     * sie nicht zugewiesen → 403); sie landen wieder in ihren ToDos.
     */
    private function klassentextZiel(Request $request, Klasse $klasse, ?int $fachId, string $label, string $berechtigung = 'voll')
    {
        if ($berechtigung !== 'voll') {
            return redirect()->route('module.schulzeugnis.todo.index')
                ->with('status', 'Klassentext (' . $label . ') gespeichert.');
        }

        $weiter   = (string) $request->input('weiter');
        $nachbarn = $this->klassentextNachbarn($klasse, $fachId);

        $ziel = null;
        if ($weiter === 'next' && $nachbarn['next']) {
            $ziel = $nachbarn['next']['param'];
        } elseif ($weiter === 'prev' && $nachbarn['prev']) {
            $ziel = $nachbarn['prev']['param'];
        }

        if ($ziel !== null) {
            return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $ziel])
                ->with('status', 'Klassentext (' . $label . ') gespeichert.');
        }
        if ($weiter === 'index') {
            return redirect()->route('module.schulzeugnis.klassenraeume.zeugnisse.index', $klasse)
                ->with('status', 'Klassentext (' . $label . ') gespeichert.');
        }

        $fachParam = $fachId === null ? 'haupt' : (string) $fachId;

        return redirect()->route('module.schulzeugnis.klassenraeume.klassentexte.edit', ['klasse' => $klasse, 'fach' => $fachParam])
            ->with('status', 'Klassentext (' . $label . ') gespeichert.');
    }

    /**
     * Korrektor-Zuweisungen protokollieren: je hinzugefügtem/entferntem Lehrer eine
     * append-only Zeile mit Name des Betroffenen (Akteur = eingeloggter Nutzer, Zeit
     * automatisch). $aktionPrefix z. B. 'abschnitt_korrektor' oder 'klassentext_korrektor'.
     *
     * @param  array<string,mixed>  $baseAttrs  Bezug (schuljahr_id/zeugnis_id/abschnitt_id/klassentext_id)
     * @param  array<int,int|string>  $alt
     * @param  array<int,int|string>  $neu
     */
    private function protokolliereKorrektoren(array $baseAttrs, string $aktionPrefix, array $alt, array $neu): void
    {
        $alt = array_map('intval', $alt);
        $neu = array_map('intval', $neu);
        $hinzu = array_values(array_diff($neu, $alt));
        $weg   = array_values(array_diff($alt, $neu));

        if (! $hinzu && ! $weg) {
            return;
        }

        $namen = Lehrer::whereIn('id', array_merge($hinzu, $weg))->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->fullName()]);

        foreach ($hinzu as $id) {
            Protokoll::log($aktionPrefix . '_hinzugefuegt', array_merge($baseAttrs, [
                'beschreibung' => 'Korrektor hinzugefügt: ' . ($namen[$id] ?? ('#' . $id)),
                'neu_wert'     => $namen[$id] ?? ('#' . $id),
            ]));
        }
        foreach ($weg as $id) {
            Protokoll::log($aktionPrefix . '_entfernt', array_merge($baseAttrs, [
                'beschreibung' => 'Korrektor entfernt: ' . ($namen[$id] ?? ('#' . $id)),
                'alt_wert'     => $namen[$id] ?? ('#' . $id),
            ]));
        }
    }

    /** Eine Feld-Änderung am Klassentext protokollieren – nur wenn sie sich unterscheidet. */
    private function logKlassentext(Klassentext $kt, ?int $schuljahrId, string $feld, ?string $alt, ?string $neu, string $aktion = 'klassentext_geaendert'): void
    {
        if ((string) $alt === (string) $neu) {
            return;
        }

        Protokoll::log($aktion, [
            'schuljahr_id'   => $schuljahrId,
            'klassentext_id' => $kt->id,
            'beschreibung'   => $feld,
            'alt_wert'       => $alt,
            'neu_wert'       => $neu,
        ]);
    }

    /** Überlauf-Analyse aller Zeugnisse der Klasse verwerfen, wenn sich der Klassentext geändert hat. */
    private function klassentextUeberlaufVerwerfen(Klasse $klasse, ?string $alt, ?string $neu): void
    {
        if ((string) $alt === (string) $neu) {
            return;
        }
        Zeugnis::whereHas('schueler', fn ($q) => $q->where('klasse_id', $klasse->id))
            ->update(['ueberlauf_status' => null]);
    }

    /** Änderungsverlauf eines Klassentextes (Text/Notiz/Status) für die Editor-Anzeige. */
    private function klassentextVerlauf(Klassentext $kt)
    {
        if (! $kt->exists) {
            return collect();
        }

        $hex = self::STATUS_FARBE_HEX;

        return Protokoll::where('klassentext_id', $kt->id)
            ->whereIn('aktion', ['klassentext_geaendert', 'klassentext_notiz', 'klassentext_status', 'klassentext_wiederhergestellt', 'klassentext_korrektor_hinzugefuegt', 'klassentext_korrektor_entfernt'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Protokoll $e) use ($hex) {
                $istStatus  = $e->aktion === 'klassentext_status';
                $istRestore = $e->aktion === 'klassentext_wiederhergestellt';
                $istMeta    = in_array($e->aktion, ['klassentext_korrektor_hinzugefuegt', 'klassentext_korrektor_entfernt'], true);
                $wz = fn ($s) => trim((string) $s) === '' ? 0 : count(preg_split('/\s+/u', trim((string) $s)));

                $status  = null;
                $summary = '';

                if ($istMeta) {
                    // Korrektor-Änderung: die Beschreibung sagt alles, kein Text-Diff.
                } elseif ($istStatus) {
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
                    'istMeta'           => $istMeta,
                    'wiederhergestellt' => $istRestore,
                    'status'            => $status,
                    'summary'           => $summary,
                    'alt'               => (string) $e->alt_wert,
                    'neu'               => (string) $e->neu_wert,
                    'restorable'        => in_array($e->aktion, ['klassentext_geaendert', 'klassentext_wiederhergestellt'], true),
                ];
            });
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
