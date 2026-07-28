<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Randnotiz an einem Abschnitt oder Klassentext – append-only: Zeilen werden
 * nur angehängt, nie geändert oder gelöscht. Der Autor wird als eingefrorener
 * Schnappschuss festgehalten (wie im Protokoll), damit die Notiz die Löschung
 * des Core-Users überlebt.
 */
class Notiz extends Model
{
    protected $table = 'zeugnis_notizen';

    /** Nur created_at – Zeilen werden nie aktualisiert. */
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function abschnitt(): BelongsTo
    {
        return $this->belongsTo(Abschnitt::class, 'abschnitt_id');
    }

    public function klassentext(): BelongsTo
    {
        return $this->belongsTo(Klassentext::class, 'klassentext_id');
    }

    /**
     * Notiz mit Akteur-Schnappschuss aus dem eingeloggten User anhängen.
     *
     * @param  array<string,mixed>  $attrs  abschnitt_id ODER klassentext_id + text
     */
    public static function anlegen(array $attrs): void
    {
        $user = auth()->user();

        static::create(array_merge([
            'autor_user_id' => $user?->id,
            'autor_name'    => $user?->name ?? '(unbekannt)',
            'created_at'    => now(),
        ], $attrs));
    }
}
