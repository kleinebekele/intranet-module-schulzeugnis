<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fachbereich eines Hauptzeugnisses – frei je Klasse definiert (z. B. "Allgemein",
 * "Rechnen", "Schreiben und Lesen"). "Allgemein" wird beim Einschalten des
 * Hauptzeugnisses automatisch angelegt und ist nicht löschbar.
 */
class Hauptbereich extends Model
{
    /** Name des Standard-Bereichs, den jedes Hauptzeugnis mindestens hat. */
    public const STANDARD = 'Allgemein';

    protected $table = 'zeugnis_hauptbereiche';

    protected $guarded = [];

    public function klasse(): BelongsTo
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }
}
