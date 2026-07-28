---
titel: Stammdaten-Import
route: module.schulzeugnis.import.index
kategorie: Schulzeugnis – Verwaltung
position: 30
rollen: zeugnis_admin, admin
---

Klassen, Lehrer, Schüler, Fächer und Lehraufträge aus dem Schulverwaltungsprogramm
übernehmen, statt sie abzutippen. Fünf Import-Arten, jede mit eigenen Spalten.

## Immer erst der Trockenlauf

Der Ablauf ist bei jeder Art derselbe:

1. Import-Art wählen und Datei angeben – hochladen oder eine bereits abgelegte Datei nehmen.
2. **Vorschau**: Das Modul liest die Datei und zeigt Zeile für Zeile, was passieren würde.
   Dabei wird **nichts** geschrieben.
3. Erst nach dem Bestätigen wird geschrieben – in einer Transaktion und mit Protokoll.

Nutzen Sie den Trockenlauf wirklich. Er zeigt Zeilen, die keiner Klasse zugeordnet werden
können, doppelte Kürzel und fehlende Pflichtangaben, bevor sie in der Datenbank stehen.

## Additiv – es wird nie gelöscht

Ein Import legt an und aktualisiert, aber er räumt nicht auf. Wer nicht in der Datei steht,
verschwindet nicht.

Das ist Absicht: Ein unvollständiger Export darf keinen halben Jahrgang wegwischen. Wer
wirklich weg soll, wird von Hand entfernt.

## Woran Zeilen wiedererkannt werden

- **Fächer** am Kürzel, ersatzweise am Namen. Deshalb sollte ein Kürzel stabil bleiben.
- **Lehrer** an der externen ID – derselben, die auch das Intranet-Konto trägt. Damit
  entsteht die Konto-Verknüpfung gleich mit.
- Die übrigen Arten hängen am gewählten **Schuljahr**; Fächer sind die Ausnahme, sie gelten
  jahresübergreifend.

## Reihenfolge

Von grob nach fein, sonst finden die späteren Zeilen ihre Bezüge nicht:

**Fächer → Lehrer → Klassen → Schüler → Lehraufträge**

Die Lehraufträge zuletzt, weil sie Klasse, Lehrer und Fach gleichzeitig brauchen.

## Fehlt ein Intranet-Konto

Ein Lehrer wird auch dann angelegt, wenn es zu ihm noch kein Benutzerkonto gibt. Die
Verknüpfung zieht das Modul selbst nach, sobald das Konto entsteht – sofort beim Anlegen,
und zusätzlich über einen nächtlichen Abgleich.

## Automatischer Abgleich

Wird das Modul an eine laufende Quelle angebunden, kann der Abgleich auch nächtlich und ohne
Datei laufen. Dieser Weg hier – Datei, Vorschau, Bestätigen – bleibt davon unberührt und ist
der richtige für Nachträge und Korrekturen.
