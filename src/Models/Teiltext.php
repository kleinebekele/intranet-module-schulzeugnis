<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teiltext einer Lehrkraft an einem Fachtext mit mehreren Fachlehrern (Team-Teaching).
 * Siehe {@see \Intranet\Modules\Schulzeugnis\Support\Teamtext}.
 */
class Teiltext extends Model
{
    protected $table = 'zeugnis_abschnitt_teiltexte';

    protected $guarded = [];

    protected $casts = [
        'zusammenfassen' => 'boolean',
    ];

    public function abschnitt(): BelongsTo
    {
        return $this->belongsTo(Abschnitt::class, 'abschnitt_id');
    }
}
