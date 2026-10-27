---
titel: Fächer
route: module.schulzeugnis.faecher.index
kategorie: Schulzeugnis – Verwaltung
position: 26
rollen: zeugnis_admin, admin
---

Der Fächerkatalog – **jahresübergreifend**. Anders als Klassen, Lehrer und Schüler werden
Fächer nur einmal gepflegt und in jedem Schuljahr weiterverwendet.

Über **Fächer aus Linear** kommen die Fächer, die im aktiven Schuljahr in Linear vorkommen – mit Vorschau, siehe Stammdaten-Import.

## Kürzel und Reihenfolge

Das **Kürzel** ist der Wiedererkennungs-Schlüssel beim Import: Ein Fach mit demselben Kürzel
wird aktualisiert statt doppelt angelegt. Ändern Sie es nicht ohne Grund – der nächste Import
legt sonst ein zweites Fach an.

Die **Reihenfolge** steuert, in welcher Abfolge die Fächer im Zeugnis und in der
Zeugnisliste erscheinen. Eine Zahl mit Abstand (10, 20, 30) lässt später Platz zum
Einschieben.

## Archivieren statt löschen

Ein Fach, das nicht mehr unterrichtet wird, stellen Sie auf **inaktiv**. Es steht dann für
neue Lehraufträge nicht mehr zur Auswahl, bleibt aber in allen alten Zeugnissen erhalten.

Löschen ist der falsche Weg, wenn an dem Fach schon Texte hängen. Ein Zeugnis von 2024 soll
in fünf Jahren noch dasselbe sagen wie heute.

## Ist der Hauptunterricht (Spalte HAU)

Am Fach Hauptunterricht setzen Sie den Haken **Ist der Hauptunterricht (Spalte HAU)**. In
Klassen mit Hauptzeugnis wird das Fach dann zur Spalte **HAU** der Zeugnisliste:

- Die Lehrkräfte mit Lehrauftrag im Hauptunterricht schreiben – neben der Klassenleitung –
  die Fachbereiche des Hauptzeugnisses.
- Im Fachzeugnis entfällt das Fach (kein eigener Abschnitt, keine doppelte Spalte).

In Klassen ohne Hauptzeugnis bleibt es ein normales Fach. Schon geschriebene Fachtexte werden
nur ausgeblendet, nicht gelöscht.
