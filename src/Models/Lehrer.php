<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lehrer je Schuljahr – modul-eigene Zeile. core_user_id ist ein LOSER Wert
 * (kein FK auf die Core-users-Tabelle); vorname/nachname sind der Schnappschuss,
 * der auch nach Löschung des Core-Kontos erhalten bleibt.
 */
class Lehrer extends Model
{
    protected $table = 'zeugnis_schuljahr_lehrer';

    protected $guarded = [];

    public function schuljahr(): BelongsTo
    {
        return $this->belongsTo(Schuljahr::class, 'schuljahr_id');
    }

    public function fullName(): string
    {
        return trim($this->vorname . ' ' . $this->nachname);
    }
}
