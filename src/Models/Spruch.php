<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Spruch – Eintrag im (jahresübergreifenden) Zeugnisspruch-Katalog. Der Klassenlehrer
 * wählt daraus einen Spruch je Schüler; der Text wird in den Schüler-Spruch kopiert
 * und ist danach frei editierbar. Nicht mehr genutzte Sprüche werden deaktiviert
 * (aktiv = false), nicht gelöscht.
 */
class Spruch extends Model
{
    protected $table = 'zeugnis_sprueche';

    protected $guarded = [];

    protected $casts = [
        'aktiv' => 'boolean',
    ];

    /** Kurzvorschau des Spruchtexts (für Auswahllisten). */
    public function vorschau(int $laenge = 80): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $this->text));

        return mb_strlen($text) > $laenge ? mb_substr($text, 0, $laenge - 1) . '…' : $text;
    }
}
