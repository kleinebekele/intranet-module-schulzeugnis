# Schulzeugnis-Modul

Zeugnis-Modul für die modulare Intranet-Plattform – zugeschnitten auf eine **Waldorfschule**:
Textzeugnisse in den meisten Jahrgängen, Schulnoten 1–6 nur zum Abschluss.

## Merkmale

- **Stammdaten je Schuljahr:** Schuljahre, Klassen, Lehrer, Schüler, Fächer, Lehraufträge.
- **Vollständig vom Core entkoppelt:** keine Fremdschlüssel auf die Core-Benutzertabelle.
  Schüler haben kein Login; Lehrer sind nur lose über die Core-User-ID verknüpft
  (existiert das Konto → Zugriff; wird es gelöscht → Daten bleiben erhalten).
- **Additiver Jahres-Import:** jedes Schuljahr wird neu verdrahtet, alte Jahrgänge bleiben
  für immer vollständig einsehbar.
- **Append-only Protokoll:** jede Handlung (wer · wann · was · alt → neu) wird festgehalten
  und überlebt die Löschung des Benutzers.
- **WYSIWYG-Zeugnisdesigner:** Elemente frei auf dem Blatt positionieren, an Datenfelder binden;
  DIN A4/A3, Hoch-/Querformat und **gefaltete A3-Broschüre** (4 A4-Seiten). Ausgabe als PDF (dompdf).

## Installation

```bash
composer require do1emu/module-schulzeugnis
php artisan modules:sync
php artisan migrate
```

## Lizenz

MIT
