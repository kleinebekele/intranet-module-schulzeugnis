<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teiltext einer Lehrkraft am klassenweiten Text eines Fachs mit mehreren Fachlehrern.
 * Gegenstück zu {@see Teiltext}; siehe {@see \Intranet\Modules\Schulzeugnis\Support\Teamtext}.
 */
class KlassentextTeiltext extends Model
{
    protected $table = 'zeugnis_klassentext_teiltexte';

    protected $guarded = [];

    protected $casts = [
        'zusammenfassen' => 'boolean',
    ];

    public function klassentext(): BelongsTo
    {
        return $this->belongsTo(Klassentext::class, 'klassentext_id');
    }
}
