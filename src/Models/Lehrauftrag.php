<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lehrauftrag – wer unterrichtet welches Fach in welcher Klasse.
 * klasse_id trägt schon das Schuljahr. Mehrere Zeilen je Fach/Klasse =
 * Team-Teaching (mehrere Fachlehrer) möglich.
 */
class Lehrauftrag extends Model
{
    protected $table = 'zeugnis_lehrauftraege';

    protected $guarded = [];

    public function klasse(): BelongsTo
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id');
    }

    public function lehrer(): BelongsTo
    {
        return $this->belongsTo(Lehrer::class, 'lehrer_id');
    }
}
