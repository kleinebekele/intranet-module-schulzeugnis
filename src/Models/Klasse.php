<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Klasse – Jahres-Klasse (z. B. "5a"), gehört zu genau einem Schuljahr.
 * klassenlehrer_id und standard_format_id folgen, sobald Lehrer/Formate existieren.
 */
class Klasse extends Model
{
    protected $table = 'zeugnis_klassen';

    protected $guarded = [];

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

    /** Klassenlehrer – loser Verweis (kein FK) auf einen Lehrer des Schuljahres. */
    public function klassenlehrer(): BelongsTo
    {
        return $this->belongsTo(Lehrer::class, 'klassenlehrer_id');
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
