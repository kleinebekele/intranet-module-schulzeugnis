<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Klasse – Jahres-Klasse (z. B. "5a"), gehört zu genau einem Schuljahr.
 * Klassenleitung (beliebig viele Klassenlehrer) über zeugnis_klasse_klassenlehrer.
 */
class Klasse extends Model
{
    protected $table = 'zeugnis_klassen';

    protected $guarded = [];

    protected $casts = [
        'hat_fachzeugnis'   => 'boolean',
        'hat_hauptzeugnis'  => 'boolean',
        'hat_zeugnisspruch' => 'boolean',
    ];

    public function schuljahr(): BelongsTo
    {
        return $this->belongsTo(Schuljahr::class, 'schuljahr_id');
    }

    /** Schulstufe der Klasse – bestimmt u. a. die Türfarbe in den Klassenräumen. */
    public function stufe(): BelongsTo
    {
        return $this->belongsTo(Stufe::class, 'stufe_id');
    }

    public function standardFormat(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'standard_format_id');
    }

    /** Vorlage des Hauptzeugnisses (das Fachzeugnis nutzt standardFormat). */
    public function hauptzeugnisFormat(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'hauptzeugnis_format_id');
    }

    /** Fachbereiche des Hauptzeugnisses – frei je Klasse, in Reihenfolge. */
    public function hauptbereiche(): HasMany
    {
        return $this->hasMany(Hauptbereich::class, 'klasse_id')->orderBy('reihenfolge')->orderBy('id');
    }

    /**
     * Fächer, die in dieser Klasse entfallen, weil das Hauptzeugnis sie ersetzt
     * (Fach „Wird durch das Hauptzeugnis ersetzt" + Klasse mit Hauptzeugnis).
     *
     * @return array<int,int>
     */
    public function entfallendeFachIds(): array
    {
        return $this->hat_hauptzeugnis ? Fach::durchHauptzeugnisIds() : [];
    }

    /**
     * Erster Klassenlehrer – loser Verweis (kein FK). Spiegelt den ersten Eintrag der
     * {@see klassenleitung()}; Autor neu angelegter Haupttexte/Sprüche.
     */
    public function klassenlehrer(): BelongsTo
    {
        return $this->belongsTo(Lehrer::class, 'klassenlehrer_id');
    }

    /** Alle Klassenlehrer der Klasse (beliebig viele), in Reihenfolge. */
    public function klassenleitung(): BelongsToMany
    {
        return $this->belongsToMany(Lehrer::class, 'zeugnis_klasse_klassenlehrer', 'klasse_id', 'lehrer_id')
            ->withPivot('reihenfolge')
            ->withTimestamps()
            ->orderBy('zeugnis_klasse_klassenlehrer.reihenfolge');
    }

    /** IDs aller Klassenlehrer (Lehrer des Schuljahres). */
    public function klassenlehrerIds(): Collection
    {
        return $this->klassenleitung->pluck('id');
    }

    /** Namen aller Klassenlehrer, kommagetrennt ('' wenn keiner gesetzt). */
    public function klassenleitungNamen(): string
    {
        return $this->klassenleitung->map(fn (Lehrer $l) => $l->fullName())->implode(', ');
    }

    /** Ist einer der übergebenen Lehrer (IDs) Klassenlehrer dieser Klasse? */
    public function hatKlassenlehrer(iterable $lehrerIds): bool
    {
        return $this->klassenlehrerIds()->intersect(collect($lehrerIds))->isNotEmpty();
    }

    /**
     * Klassenleitung setzen: Reihenfolge = Übergabe, doppelte/leere Werte fallen weg.
     * klassenlehrer_id folgt dem ersten Eintrag.
     *
     * @param  array<int,int|string|null>  $lehrerIds
     */
    public function setzeKlassenleitung(array $lehrerIds): void
    {
        $ids = collect($lehrerIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $this->klassenleitung()->sync(
            $ids->mapWithKeys(fn (int $id, int $pos) => [$id => ['reihenfolge' => $pos]])->all()
        );
        $this->forceFill(['klassenlehrer_id' => $ids->first()])->save();
        $this->unsetRelation('klassenleitung');
    }

    /**
     * Ersten Klassenlehrer setzen (Import), die übrigen bleiben dahinter erhalten.
     */
    public function setzeErstenKlassenlehrer(?int $lehrerId): void
    {
        if ($lehrerId === null) {
            return;
        }
        $this->setzeKlassenleitung([$lehrerId, ...$this->klassenleitung()->pluck('zeugnis_schuljahr_lehrer.id')->all()]);
    }

    public function lehrauftraege(): HasMany
    {
        return $this->hasMany(Lehrauftrag::class, 'klasse_id');
    }

    public function schueler(): HasMany
    {
        return $this->hasMany(Schueler::class, 'klasse_id');
    }
}
