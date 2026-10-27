<?php

namespace Intranet\Modules\Schulzeugnis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public const TYP_FACHBEREICH = 'fachbereich';
    /** Altbestand (vor v1.16): der eine Sammelabschnitt eines Hauptzeugnisses mit Bereichtexten.
     *  Ersetzt durch je Fachbereich einen Abschnitt (TYP_FACHBEREICH); bleibt nur zur Ansicht. */
    public const TYP_HAUPTZEUGNIS = 'hauptzeugnis';
    /** Zeugnisspruch je Schüler – eigener Baustein (wie ein Fach), nur vom
     *  Klassenlehrer setzbar; Inhalt aus dem Spruch-Katalog, danach frei editierbar. */
    public const TYP_SPRUCH = 'zeugnisspruch';

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

    /**
     * Status-Automatik beim Speichern eines Textes: Wer in einen unbearbeiteten Text
     * schreibt, ist „In Arbeit"; ein komplett geleerter Text ist wieder „Unbearbeitet"
     * – gleich welcher Status gewählt war. Gilt auch für Klassentexte.
     */
    public static function statusNachInhalt(string $status, bool $hatInhalt): string
    {
        if (! $hatInhalt) {
            return self::STATUS_STANDARD;
        }

        return $status === self::STATUS_STANDARD ? 'in_arbeit' : $status;
    }

    /** Steht im Text etwas außer Leerraum/Auszeichnung? */
    public static function textGefuellt(?string $text): bool
    {
        $rein = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $rein)) !== '';
    }

    /** Hat dieser Abschnitt Inhalt (Text, Note bzw. einen der Fachbereichstexte)? */
    public function hatInhalt(): bool
    {
        if ($this->typ === self::TYP_HAUPTZEUGNIS) {
            return $this->bereichtexte->contains(fn ($bt) => self::textGefuellt($bt->inhalt));
        }

        return self::textGefuellt($this->inhalt) || ($this->typ === self::TYP_NOTE && filled($this->note));
    }

    protected $table = 'zeugnis_abschnitte';

    protected $guarded = [];

    protected $casts = [
        'klassentext_neue_zeile' => 'boolean',
        'zusammengefasst'        => 'boolean',
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

    /** Fachbereich (nur bei typ = fachbereich, Hauptzeugnis) – loser Bezug, nullOnDelete. */
    public function bereich(): BelongsTo
    {
        return $this->belongsTo(Hauptbereich::class, 'bereich_id');
    }

    /** Überschrift eines Fachbereich-Abschnitts: bevorzugt der lebende Bereich, sonst der eingefrorene Klartext. */
    public function ueberschrift(): string
    {
        return $this->bereich?->name ?? (string) $this->bereich_name;
    }

    /** Fachbereich „Allgemein" = Schülertext der Spalte HAU (Hauptunterricht). */
    public function istHauptunterricht(): bool
    {
        return $this->typ === self::TYP_FACHBEREICH && (bool) $this->bereich?->istStandard();
    }

    /** Kurzname für Überschriften und Rückmeldungen (Fach, Fachbereich bzw. Art des Textes). */
    public function bezeichnung(): string
    {
        return match ($this->typ) {
            self::TYP_HAUPTZEUGNIS => 'Hauptzeugnis',
            self::TYP_HAUPTTEXT    => 'Haupttext',
            self::TYP_SPRUCH       => 'Zeugnisspruch',
            self::TYP_FACHBEREICH  => $this->istHauptunterricht() ? 'Hauptunterricht' : ($this->ueberschrift() ?: 'Fachbereich'),
            default                => $this->fach?->name ?? 'Fachtext',
        };
    }

    /** Lehrer, die diesen Abschnitt korrigieren dürfen. */
    public function korrektoren(): BelongsToMany
    {
        return $this->belongsToMany(Lehrer::class, 'zeugnis_abschnitt_korrektoren', 'abschnitt_id', 'lehrer_id');
    }

    /** Schülertexte je Fachbereich – nur beim Hauptzeugnis-Abschnitt (typ = hauptzeugnis). */
    /** Teiltexte je Fachlehrer (Team-Teaching), siehe Support\Teamtext. */
    public function teiltexte(): HasMany
    {
        return $this->hasMany(Teiltext::class, 'abschnitt_id');
    }

    public function bereichtexte(): HasMany
    {
        return $this->hasMany(Bereichtext::class, 'abschnitt_id')->orderBy('reihenfolge')->orderBy('id');
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
