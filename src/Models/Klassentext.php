<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Klassenweiter Text je Fach – ein gemeinsamer Text pro (Klasse, Fach), der auf
 * dem Zeugnis vor dem individuellen Schülertext steht. Verhält sich im Editor
 * wie ein Abschnitt (Notiz, Korrektoren, Status, Änderungsverlauf).
 */
class Klassentext extends Model
{
    protected $table = 'zeugnis_fach_klassentexte';

    protected $guarded = [];

    protected $casts = [
        'zusammengefasst' => 'boolean',
    ];

    /** Teiltexte je Fachlehrer (Team-Teaching), siehe Support\Teamtext. */
    public function teiltexte(): HasMany
    {
        return $this->hasMany(KlassentextTeiltext::class, 'klassentext_id');
    }

    public function klasse(): BelongsTo
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id');
    }

    /** Fachbereich des Hauptzeugnisses (loser Verweis, null = Fach- bzw. Haupttext). */
    public function bereich(): BelongsTo
    {
        return $this->belongsTo(Hauptbereich::class, 'bereich_id');
    }

    /**
     * Route-Parameter des Klassentext-Editors: Fach-ID, 'b{bereich}', 'haupt' oder 'spruch'.
     */
    public function param(): string
    {
        return match (true) {
            $this->art === 'spruch'    => 'spruch',
            $this->bereich_id !== null => 'b' . $this->bereich_id,
            $this->fach_id !== null    => (string) $this->fach_id,
            default                    => 'haupt',
        };
    }

    /** Lehrer, die diesen klassenweiten Text korrigieren dürfen (analog Abschnitt). */
    public function korrektoren(): BelongsToMany
    {
        return $this->belongsToMany(Lehrer::class, 'zeugnis_klassentext_korrektoren', 'klassentext_id', 'lehrer_id');
    }

    /** Anzeige-Metadaten (label/icon/farbe) zum Klassentext-Status – gleiche Skala wie Abschnitte. */
    public function statusMeta(): array
    {
        return Abschnitt::STATI[$this->status] ?? Abschnitt::STATI['unbearbeitet'];
    }
}
