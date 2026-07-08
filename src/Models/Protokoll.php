<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Protokoll – append-only. Zeilen werden nur geschrieben, nie geändert.
 * Der Akteur wird als eingefrorener Schnappschuss festgehalten (Name/ID zum
 * Zeitpunkt der Handlung), damit die Historie die Löschung des Core-Users überlebt.
 */
class Protokoll extends Model
{
    protected $table = 'zeugnis_protokoll';

    /** Nur created_at – Zeilen werden nie aktualisiert. */
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Eine Handlung festhalten. Der Akteur kommt aus dem eingeloggten Core-User,
     * wird aber als Klartext-Schnappschuss gespeichert (kein FK).
     *
     * @param  array<string,mixed>  $attrs  zusätzliche Felder (schuljahr_id, zeugnis_id,
     *                                       abschnitt_id, beschreibung, alt_wert, neu_wert)
     */
    public static function log(string $aktion, array $attrs = []): void
    {
        $user = auth()->user();

        static::create(array_merge([
            'aktion'         => $aktion,
            'akteur_user_id' => $user?->id,
            'akteur_name'    => $user?->name,
            'created_at'     => now(),
        ], $attrs));
    }
}
