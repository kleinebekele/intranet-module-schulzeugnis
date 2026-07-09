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

    public function klassen(): HasMany
    {
        return $this->hasMany(Klasse::class, 'stufe_id');
    }
}
