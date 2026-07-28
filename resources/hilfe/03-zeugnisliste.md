---
titel: Zeugnisliste einer Klasse
route: module.schulzeugnis.klassenraeume.zeugnisse.index
kategorie: Schulzeugnis
position: 3
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

## Die Spalte mit dem Klassentext

Neben den Schülern steht je Fach der **Klassentext** – der gemeinsame Absatz, der bei allen
Kindern vor dem individuellen Text erscheint. Er hat einen eigenen Stand und eigene
Korrektoren; ihn zu schreiben ist eine Aufgabe, nicht dreißig.

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
