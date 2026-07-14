<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stufe – Schulstufe (Primarstufe, Sekundarstufe I …). Jahresübergreifende
 * Stammdaten; trägt die Türfarbe für die Klassenräume-Ansicht.
 */
class Stufe extends Model
{
    protected $table = 'zeugnis_stufen';

    protected $guarded = [];

    protected $casts = [
        'von_klasse' => 'integer',
        'bis_klasse' => 'integer',
    ];

    /** Kurzform des Klassenstufen-Bereichs, z. B. "Kl. 5–7" (oder null). */
    public function klassenBereich(): ?string
    {
        if ($this->von_klasse === null || $this->bis_klasse === null) {
            return null;
        }

        return $this->von_klasse === $this->bis_klasse
            ? "Kl. {$this->von_klasse}"
            : "Kl. {$this->von_klasse}–{$this->bis_klasse}";
    }

    public function klassen(): HasMany
    {
        return $this->hasMany(Klasse::class, 'stufe_id');
    }
}
