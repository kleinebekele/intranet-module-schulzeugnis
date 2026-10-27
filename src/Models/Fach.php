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
        'aktiv'              => 'boolean',
        'durch_hauptzeugnis' => 'boolean',
    ];

    /**
     * IDs der Fächer „Ist der Hauptunterricht" (Spalte durch_hauptzeugnis): in Klassen mit
     * Hauptzeugnis bilden sie die Spalte HAU und entfallen im Fachzeugnis.
     */
    public static function durchHauptzeugnisIds(): array
    {
        return self::where('durch_hauptzeugnis', true)->pluck('id')->all();
    }
}
