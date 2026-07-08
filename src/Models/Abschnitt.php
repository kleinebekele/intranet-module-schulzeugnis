<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abschnitt – Baustein eines Zeugnisses (typ: haupttext | fachtext | note).
 * autor_lehrer_id ist ein LOSER Verweis (kein FK); autor_name ist der
 * eingefrorene Klartext, damit "wer hat das geschrieben" erhalten bleibt.
 * Bei Team-Teaching sind mehrere Lehrer gemeinsam für EINEN Inhalt verantwortlich –
 * autor_name hält dann alle Namen.
 */
class Abschnitt extends Model
{
    public const TYP_HAUPTTEXT = 'haupttext';
    public const TYP_FACHTEXT = 'fachtext';
    public const TYP_NOTE = 'note';

    public const STATUS_OFFEN = 'offen';
    public const STATUS_FERTIG = 'fertig';

    protected $table = 'zeugnis_abschnitte';

    protected $guarded = [];

    public function zeugnis(): BelongsTo
    {
        return $this->belongsTo(Zeugnis::class, 'zeugnis_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id');
    }
}
