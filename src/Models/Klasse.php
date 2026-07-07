<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function standardFormat(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'standard_format_id');
    }
}
