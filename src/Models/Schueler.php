<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Schüler je Schuljahr – modul-eigene Zeile pro Schuljahr (zugleich Einschulung:
 * trägt klasse_id). KEIN Verweis auf die Core-users-Tabelle, kein Login.
 * quell_id ist die stabile externe ID (loser Wert), die dieselbe Person über die
 * Jahre verbindet; der Klartext-Name macht die Zeile selbstständig.
 */
class Schueler extends Model
{
    protected $table = 'zeugnis_schuljahr_schueler';

    protected $guarded = [];

    protected $casts = [
        'geburtsdatum' => 'date',
    ];

    public function schuljahr(): BelongsTo
    {
        return $this->belongsTo(Schuljahr::class, 'schuljahr_id');
    }

    public function klasse(): BelongsTo
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    /** Abweichendes Format nur für diesen Schüler (überschreibt den Klassen-Standard). */
    public function formatOverride(): BelongsTo
    {
        return $this->belongsTo(Format::class, 'format_override_id');
    }

    public function zeugnis(): HasOne
    {
        return $this->hasOne(Zeugnis::class, 'schueler_id');
    }

    public function fullName(): string
    {
        return trim($this->vorname . ' ' . $this->nachname);
    }

    /** Effektives Format: eigener Override, sonst Standard der Klasse. */
    public function effektivesFormatId(): ?int
    {
        return $this->format_override_id ?? $this->klasse?->standard_format_id;
    }
}

