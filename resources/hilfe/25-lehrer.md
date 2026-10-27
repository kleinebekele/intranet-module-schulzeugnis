---
titel: Lehrer
route: module.schulzeugnis.lehrer.jahr
kategorie: Schulzeugnis – Verwaltung
position: 25
rollen: zeugnis_admin, admin
---

Die Lehrkräfte eines Schuljahres. Sie sind mit ihrem Intranet-Konto verknüpft – aber nur
**lose**.

Über **Aus Linear importieren** kommen die Lehrkräfte des Schuljahres aus Linear – mit Vorschau, siehe Stammdaten-Import.

## Was „lose verknüpft" heißt

Der Lehrer-Datensatz merkt sich die Benutzer-ID, mehr nicht. Es gibt keinen Fremdschlüssel
zur Benutzertabelle. Daraus folgt:

- Existiert das Konto, kann die Lehrkraft ihre Texte bearbeiten.
- Wird das Konto gelöscht, bleiben Lehrer-Datensatz, Texte, Autorennamen und Protokoll
  vollständig erhalten. Nur der Zugriff fällt weg.

Ein Zeugnis muss auch dann noch lesbar sein, wenn die Lehrkraft die Schule längst verlassen
hat. Deshalb steht der Name als eingefrorener Klartext am Text – nicht als Verweis.

## Ein Lehrer ohne Konto

Kommt vor, besonders direkt nach einem Import: Die Lehrkraft ist im Zeugnismodul angelegt,
im Intranet gibt es aber noch keinen Benutzer.

Das Modul löst das von selbst. Sobald ein passendes Konto entsteht oder seine externe ID
gesetzt wird, verknüpft es sich **sofort**; zusätzlich läuft nachts um 3 Uhr ein Abgleich für
alle noch offenen Fälle.

Sie müssen also nichts nachtragen – nur warten oder das Konto anlegen.

## Als Lehrkraft nutzen

Mit **Als Lehrkraft handeln** in der Zeile einer Lehrkraft sehen Sie das Zeugnismodul genau so, wie diese
Lehrkraft es sieht: ihre Klassenräume, ihre ToDos, ihre Rechte an den Texten, ihre
Menüpunkte. Damit lässt sich prüfen, ob Lehraufträge und Klassenleitung richtig sitzen.

- Der Knopf erscheint nur bei Lehrkräften mit verknüpftem Konto und nur für Administratoren
  bzw. wer die Lehrer **verwalten** darf.
- Solange es läuft, steht oben ein roter Balken. **Beenden – wieder ich selbst** führt zurück.
- Es wirkt nur im Zeugnismodul. Außerhalb sind Sie wieder Sie selbst.
- Jede Änderung in dieser Zeit fragt vorher nach und landet im Audit-Log – mit Ihnen als
  Handelndem und der Lehrkraft als Betroffener.

## Wenn jemand nicht schreiben kann

Die Reihenfolge zum Nachsehen:

1. Ist die Lehrkraft in **diesem** Schuljahr angelegt? Lehrer hängen am Jahr.
2. Ist sie mit ihrem Konto verknüpft?
3. Hat sie einen **Lehrauftrag** in der Klasse und dem Fach – oder ist sie Klassenlehrer?

Fast immer liegt es an Punkt 3. Am schnellsten prüfen Sie es mit **Als Lehrkraft handeln** – dann sehen Sie
selbst, was die Lehrkraft sieht.
