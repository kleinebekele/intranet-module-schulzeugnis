<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Klassenweiter Text je Fach – ein gemeinsamer Text pro (Klasse, Fach), der auf
 * dem Zeugnis vor dem individuellen Schülertext steht.
 */
class Klassentext extends Model
{
    protected $table = 'zeugnis_fach_klassentexte';

    protected $guarded = [];

    public function klasse(): BelongsTo
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id');
    }
}
