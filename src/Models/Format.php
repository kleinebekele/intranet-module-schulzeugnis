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
        'seiten'     => 'array',
    ];

    /**
     * Rollen der Design-Seiten (nur Nicht-Broschüre): 'start' = erscheint genau
     * einmal, 'folge' = wiederholt sich beliebig oft, bis der Text durch ist.
     *
     * @return array<int,string>
     */
    public function seitenRollen(): array
    {
        if ($this->broschuere) {
            return ['start', 'start', 'start', 'start'];
        }

        $rollen = array_values(array_filter(
            (array) ($this->seiten ?? []),
            fn ($r) => in_array($r, ['start', 'folge'], true)
        ));

        return $rollen ?: ['start'];
    }

    /** Anzahl der Design-Seiten: Broschüre = 4 A4-Seiten, sonst nach Rollen (min. 1). */
    public function seitenAnzahl(): int
    {
        return $this->broschuere ? 4 : count($this->seitenRollen());
    }

    /** Hat das Format (Nicht-Broschüre) mindestens eine sich wiederholende Folgeseite? */
    public function hatFolgeseiten(): bool
    {
        return ! $this->broschuere && in_array('folge', $this->seitenRollen(), true);
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
