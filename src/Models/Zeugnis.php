<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zeugnis – das befüllte Zeugnis eines Schülers in einem Schuljahr.
 * Beim Abschließen werden Name/Geburtsdatum/-ort als Klartext-Schnappschuss
 * eingefroren, damit ein Nachdruck originalgetreu bleibt.
 */
class Zeugnis extends Model
{
    public const STATUS_ENTWURF = 'entwurf';
    public const STATUS_ABGESCHLOSSEN = 'abgeschlossen';

    protected $table = 'zeugnisse';

    protected $guarded = [];

    protected $casts = [
        'ausgestellt_am'           => 'datetime',
        'ausgestellt_geburtsdatum' => 'date',
    ];

    public function schueler(): BelongsTo
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'format_id');
    }

    public function abschnitte(): HasMany
    {
        return $this->hasMany(Abschnitt::class, 'zeugnis_id');
    }

    public function istAbgeschlossen(): bool
    {
        return $this->status === self::STATUS_ABGESCHLOSSEN;
    }
}
