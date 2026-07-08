<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Abschnitt – Baustein eines Zeugnisses (typ: haupttext | fachtext | note).
 * autor_lehrer_id ist ein LOSER Verweis (kein FK); autor_name ist der
 * eingefrorene Klartext, damit "wer hat das geschrieben" erhalten bleibt.
 * Bei Team-Teaching sind mehrere Lehrer gemeinsam für EINEN Inhalt verantwortlich –
 * autor_name hält dann alle Namen.
 */
class Abschnitt extends Model
{
    public const TYP_HAUPTTEXT = 'haupttext';
    public const TYP_FACHTEXT = 'fachtext';
    public const TYP_NOTE = 'note';

    public const STATUS_STANDARD = 'unbearbeitet';

    /**
     * Bearbeitungs-Workflow eines Abschnitts (Reihenfolge = Fortschritt).
     * label/icon (Boxicons)/farbe steuern die Anzeige in der Zeugnis-Tabelle.
     *
     * @var array<string,array{label:string,icon:string,farbe:string}>
     */
    public const STATI = [
        'unbearbeitet'            => ['label' => 'Unbearbeitet',            'icon' => 'bx-circle',         'farbe' => 'gray'],
        'in_arbeit'               => ['label' => 'In Arbeit',               'icon' => 'bx-loader-circle',  'farbe' => 'gray'],
        'frei_zur_korrektur'      => ['label' => 'Frei zur Korrektur',      'icon' => 'bxs-edit',          'farbe' => 'amber'],
        'in_korrektur'            => ['label' => 'In Korrektur',            'icon' => 'bxs-edit',          'farbe' => 'red'],
        'korrektur_noetig'        => ['label' => 'Korrektur nötig',         'icon' => 'bx-error-circle',   'farbe' => 'red'],
        'korrektur_durchgefuehrt' => ['label' => 'Korrektur durchgeführt',  'icon' => 'bxs-edit',          'farbe' => 'green'],
        'in_ueberarbeitung'       => ['label' => 'In Überarbeitung',        'icon' => 'bx-revision',       'farbe' => 'amber'],
        'vollstaendig'            => ['label' => 'Vollständig',             'icon' => 'bxs-check-circle',  'farbe' => 'green'],
    ];

    protected $table = 'zeugnis_abschnitte';

    protected $guarded = [];

    protected $casts = [
        'klassentext_neue_zeile' => 'boolean',
    ];

    protected $attributes = [
        'klassentext_neue_zeile' => false,
    ];

    public function zeugnis(): BelongsTo
    {
        return $this->belongsTo(Zeugnis::class, 'zeugnis_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id');
    }

    /** Lehrer, die diesen Abschnitt korrigieren dürfen. */
    public function korrektoren(): BelongsToMany
    {
        return $this->belongsToMany(Lehrer::class, 'zeugnis_abschnitt_korrektoren', 'abschnitt_id', 'lehrer_id');
    }

    /** Anzeige-Metadaten (label/icon/farbe) zum aktuellen Status – robust gegen Altwerte. */
    public function statusMeta(): array
    {
        return self::STATI[$this->status] ?? ['label' => 'Unbearbeitet', 'icon' => 'bx-circle', 'farbe' => 'gray'];
    }

    /** Gilt der Abschnitt als abgeschlossen (für Fortschrittsanzeigen)? */
    public function istFertig(): bool
    {
        return $this->status === 'vollstaendig';
    }
}
