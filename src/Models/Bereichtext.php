<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Schülertext eines Hauptzeugnis-Abschnitts – gehört zu genau einem Fachbereich.
 * Der HAU-Abschnitt trägt Status/Korrektoren/Klassentext; seine mehreren Texte (einer
 * je Fachbereich) liegen hier. bereich_name ist der eingefrorene Klartext-Fallback,
 * damit die Zeile selbstständig bleibt (Anzeige nutzt bevorzugt den lebenden Bereich).
 */
class Bereichtext extends Model
{
    protected $table = 'zeugnis_bereichtexte';

    protected $guarded = [];

    public function abschnitt(): BelongsTo
    {
        return $this->belongsTo(Abschnitt::class, 'abschnitt_id');
    }

    public function bereich(): BelongsTo
    {
        return $this->belongsTo(Hauptbereich::class, 'bereich_id');
    }

    /** Überschrift: bevorzugt der lebende Bereich, sonst der eingefrorene Klartext. */
    public function ueberschrift(): string
    {
        return $this->bereich?->name ?? (string) $this->bereich_name;
    }
}
