<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Schuljahr – der Anker des Moduls. Genau eines ist "aktiv" (Standard-Ansicht);
 * ältere Schuljahre bleiben für immer bestehen und einsehbar.
 */
class Schuljahr extends Model
{
    protected $table = 'zeugnis_schuljahre';

    protected $guarded = [];

    public function klassen(): HasMany
    {
        return $this->hasMany(Klasse::class, 'schuljahr_id');
    }

    public function lehrer(): HasMany
    {
        return $this->hasMany(Lehrer::class, 'schuljahr_id');
    }

    public function schueler(): HasMany
    {
        return $this->hasMany(Schueler::class, 'schuljahr_id');
    }

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'ausgabe_datum' => 'date',
        'eingabe_frist' => 'date',
        'is_active'     => 'boolean',
    ];
}
