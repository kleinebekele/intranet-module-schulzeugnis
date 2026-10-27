---
titel: Zeugnisliste einer Klasse
route: module.schulzeugnis.klassenraeume.zeugnisse.index
kategorie: Schulzeugnis
position: 3
rollen: teacher, staff, zeugnis_admin, zeugnis_moderator, admin
---

Die Arbeitsfläche: alle Schüler der Klasse als Zeilen, alle Fächer mit Lehrauftrag als
Spalten. Jede Zelle ist ein Abschnitt und zeigt mit Symbol und Farbe, wie weit er ist.

## Es steht schon überall etwas, obwohl ich nichts geschrieben habe

Beim ersten Öffnen legt die Seite für jeden Schüler die fehlenden Zeugnisse und Abschnitte
an, damit die Tabelle vollständig ist. Sie stehen dann auf **Unbearbeitet** – das ist ein
leerer Platzhalter, kein Inhalt.

## Die acht Stände

Von links nach rechts wächst der Fortschritt:

| Stand | bedeutet |
|---|---|
| Unbearbeitet | noch nichts geschrieben |
| In Arbeit | begonnen, noch nicht fertig |
| Frei zur Korrektur | fertig, wartet auf die Korrektoren |
| In Korrektur | ein Korrektor hat sich die Sache vorgenommen |
| Korrektur nötig | zurück an den Verfasser, es gibt etwas zu ändern |
| Korrektur durchgeführt | der Korrektor ist fertig |
| In Überarbeitung | der Verfasser arbeitet die Anmerkungen ein |
| Vollständig | fertig |

Die Stände sind eine **Verabredung**, keine Sperre: Das Modul erzwingt keine Reihenfolge. Nur
zwei Stände verlangen etwas – *Frei zur Korrektur* und *Korrektur nötig* gehen nur, wenn
mindestens ein Korrektor ausgewählt ist. Sonst wüsste niemand, wer gemeint ist.

Zwei Stände setzt das Modul beim Speichern selbst: Wer in einen *unbearbeiteten* Text
schreibt, steht danach auf **In Arbeit**. Und ein komplett geleerter Text fällt auf
**Unbearbeitet** zurück – gleich, welcher Stand vorher gewählt war.

## Die Spalte mit dem Klassentext

Neben den Schülern steht je Fach der **Klassentext** – der gemeinsame Absatz, der bei allen
Kindern vor dem individuellen Text erscheint. Er hat einen eigenen Stand und eigene
Korrektoren; ihn zu schreiben ist eine Aufgabe, nicht dreißig.

## Hauptzeugnis: HAU und die Fachbereiche

Hat die Klasse ein Hauptzeugnis, ist das Zeugnis in **Haupt- und Fachzeugnis** aufgeteilt.
Vorne steht die Spalte **HAU** für den Hauptunterricht, daneben je **Fachbereich** des
Hauptzeugnisses eine eigene Spalte – genau wie ein Fach:

- Jeder Fachbereich hat je Schüler einen eigenen Text mit eigenem Stand und eigenen
  Korrektoren, und in der Zeile *Klassenweit* einen eigenen Klassentext.
- **HAU** zeigt je Schüler den Gesamtstand: den Stand des Fachbereichs, der am wenigsten
  weit ist. Ein Klick öffnet das ganze Hauptzeugnis. In der Zeile *Klassenweit* steht hier
  der Vorspann, der auf dem Hauptzeugnis vor allen Fachbereichen erscheint.
- Schreiben dürfen die Klassenleitung und die Lehrkräfte mit Lehrauftrag im Hauptunterricht.

Welche Fachbereiche es gibt, legt die Zeugnisverwaltung bei der Klasse fest. Ein später
ergänzter Fachbereich erscheint beim nächsten Öffnen der Zeugnisliste bei allen Schülern.

## Passt der Text aufs Blatt?

Zu jedem Zeugnis wird geprüft, ob der Text ins gewählte Format passt. Das Ergebnis wird
gespeichert und bei Änderungen neu berechnet, damit die Tabelle nicht jedes Mal alles
nachrechnen muss:

- **ok** – passt.
- **verkleinert** – passt, aber nur mit kleinerer Schrift; angezeigt wird, bei welcher Größe.
- **Überlauf** – passt nicht. Hier hilft nur kürzen oder ein anderes Format.

Sehen Sie sich einen Überlauf früh an. Am Tag vor der Ausgabe ist Kürzen die unangenehmste
aller Aufgaben.

## Sammel-Ausgabe

Über der Tabelle lassen sich alle Zeugnisse eines Typs auf einmal ansehen und als eine
PDF-Datei erzeugen – für den Druck der ganzen Klasse in einem Rutsch.
