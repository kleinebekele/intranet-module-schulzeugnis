<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Frei pflegbarer Beispiel-Zeugnistext für die Layout-Vorschau (modulweit).
 * Ist die Tabelle leer, verwendet der Controller die eingebauten Standardtexte.
 */
class Beispieltext extends Model
{
    protected $table = 'zeugnis_beispieltexte';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];
}
