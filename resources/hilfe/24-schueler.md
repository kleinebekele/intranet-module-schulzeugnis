---
titel: Schüler
route: module.schulzeugnis.schueler.jahr
kategorie: Schulzeugnis – Verwaltung
position: 24
rollen: zeugnis_admin, admin
---

Die Schüler eines Schuljahres, jeder in genau einer Klasse.

Über **Aus Linear importieren** kommen die Schüler des Schuljahres samt Klasse aus Linear – mit Vorschau, siehe Stammdaten-Import.

## Ausgetretene Schüler

Linear führt bei jedem Kind ein Ende der Klassenzugehörigkeit – fast immer den regulären
letzten Schultag. Als **Austritt** übernimmt der Linear-Import nur ein Datum **davor**, also
Kinder, die unterm Jahr gegangen sind. Von Hand (oder per CSV-Spalte „Austritt") lässt sich
das Datum ebenfalls eintragen. Entscheidend ist das **Schuljahresende** (gepflegt am Schuljahr, sonst der 31.07.):

- Austritt **zum Schuljahresende oder später**: Das Kind bekommt sein Zeugnis ganz normal –
  das Datum ist nur eine Info.
- Austritt **vorher**: Das Kind ist **für das Zeugnis gesperrt**. Es steht nicht mehr in der
  Zeugnisliste, nicht in den ToDos und nicht im Sammel-PDF; unter der Zeugnisliste wird es als
  gesperrt genannt. Bereits geschriebene Texte bleiben erhalten.

Die Sperre lässt sich unter **Bearbeiten** aufheben (oder von Hand setzen). Ein späterer
Import ändert sie nur, wenn sich das Austrittsdatum ändert.

## Schüler haben kein Intranet-Konto

Das ist eine bewusste Entscheidung: Ein Schüler ist hier ein Datensatz, kein Benutzer. Er
meldet sich nicht an, und es besteht keine Verbindung zur Benutzerverwaltung des Intranets.

Für das Zeugnis heißt das: Name, Geburtsdatum und Geburtsort werden hier gepflegt – sie
landen genau so auf dem Dokument.

## Warum die Angaben vor dem Abschließen stimmen müssen

Beim Abschließen eines Zeugnisses friert das Modul Name, Geburtsdatum und Geburtsort **ein**.
Eine spätere Korrektur in den Stammdaten ändert ein bereits abgeschlossenes Zeugnis nicht
mehr – so soll es sein, aber es heißt auch: Ein Tippfehler im Namen muss vorher weg.

## Format je Schüler

Normalerweise gilt das Standardformat der Klasse. Braucht ein Kind ein anderes, lässt sich
hier ein abweichendes Format hinterlegen; es sticht das der Klasse.

## Ein Kind wechselt die Klasse

Innerhalb eines Schuljahres ändern Sie die Klasse hier. Zum neuen Schuljahr entsteht ohnehin
ein neuer Datensatz – jedes Jahr wird neu verdrahtet, alte Zeugnisse bleiben bei ihrer alten
Klasse.

## Löschen

Mit dem Schüler gehen seine Zeugnisse dieses Jahres. Bei einem Kind, das die Schule
verlässt, ist es fast immer richtiger, den Datensatz stehen zu lassen: Das Zeugnis ist ein
Dokument, das man später noch einmal braucht.
