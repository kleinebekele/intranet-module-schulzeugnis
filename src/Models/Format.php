<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zeugnisformat – pflegbare Vorlage. typ legt fest, ob Freitext oder Noten.
 * Zuweisung mit Vererbung: Standard je Klasse, Override je Schüler.
 */
class Format extends Model
{
    protected $table = 'zeugnis_formate';

    protected $guarded = [];

    protected $casts = [
        'aktiv'      => 'boolean',
        'broschuere' => 'boolean',
        'layout'     => 'array',
    ];

    /** Anzahl der Design-Seiten: Broschüre = 4 A4-Seiten, sonst 1. */
    public function seitenAnzahl(): int
    {
        return $this->broschuere ? 4 : 1;
    }

    /** Für die Anzeige: 'text' => 'Textzeugnis', 'noten' => 'Noten 1–6'. */
    public function typLabel(): string
    {
        return $this->typ === 'noten' ? 'Noten 1–6' : 'Textzeugnis';
    }

    /** Seitenmaße in mm (b/h), abhängig von Format und Ausrichtung. */
    public function seiteMm(): array
    {
        $basis = $this->seitenformat === 'a3' ? [297, 420] : [210, 297];

        return $this->ausrichtung === 'quer'
            ? ['b' => $basis[1], 'h' => $basis[0]]
            : ['b' => $basis[0], 'h' => $basis[1]];
    }
}
