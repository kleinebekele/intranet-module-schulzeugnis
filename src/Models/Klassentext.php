<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Klassenweiter Text je Fach – ein gemeinsamer Text pro (Klasse, Fach), der auf
 * dem Zeugnis vor dem individuellen Schülertext steht. Verhält sich im Editor
 * wie ein Abschnitt (Notiz, Korrektoren, Status, Änderungsverlauf).
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
