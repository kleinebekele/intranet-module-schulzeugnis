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

    /**
     * Stichtag für den Austritt: Wer an diesem Tag oder später austritt, bekommt sein
     * Zeugnis noch. Das gepflegte Ende des Schuljahres, sonst der 31.07. des zweiten
     * Jahres aus dem Namen („2025/2026" → 31.07.2026); null, wenn beides fehlt.
     */
    public function schuljahresende(): ?\Carbon\CarbonInterface
    {
        if ($this->end_date) {
            return $this->end_date->copy()->startOfDay();
        }
        if (preg_match('/(\d{4})\s*\/\s*(\d{2,4})/', (string) $this->name, $m)) {
            $jahr = strlen($m[2]) === 2 ? (int) (substr($m[1], 0, 2).$m[2]) : (int) $m[2];

            return \Carbon\Carbon::create($jahr, 7, 31)->startOfDay();
        }

        return null;
    }
}
