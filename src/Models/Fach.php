<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fach – feste, jahresübergreifende Stammdatenliste. Nicht mehr unterrichtete
 * Fächer werden archiviert (aktiv = false), nicht gelöscht.
 */
class Fach extends Model
{
    protected $table = 'zeugnis_faecher';

    protected $guarded = [];

    protected $casts = [
        'aktiv' => 'boolean',
    ];
}
