# Einstellungen

Die Einstellungsbereiche erreichst du in der ChurchTools Suite unter **Einstellungen**. Nach Änderungen jeweils den Speichern-Button des betreffenden Formulars verwenden.

## API & Verbindung

**ChurchTools-Instanz** nimmt nur den Namen vor `.church.tools` an. Die **Anmeldemethode** ist entweder **Benutzername & Passwort** oder **API-Token**. Der [Einrichtungsablauf](./erste-schritte.md#churchtools-verbinden) erklärt Test und Speicherung.

![API-Verbindung der ChurchTools Integration Suite: Anmeldemethode Benutzername und Passwort mit leeren Feldern für Instanz, Benutzername und Passwort](/screenshots/churchtools-suite-connection.png)

*Echte Demoaufnahme der Version 1.4.4 mit vollständig leeren sichtbaren Zugangsfeldern und Status „Nicht konfiguriert“. Es wurde weder gespeichert noch eine Synchronisation gestartet.*

- **Verbindung testen** ist eine Vorschau mit den aktuellen Eingaben, keine Speicherung.
- Passwort und Token erscheinen nach dem Speichern absichtlich als leere Felder. Ein neues Passwort ist bei Benutzer-/Instanzwechsel erforderlich, ein neues Token bei Instanzwechsel.
- Das Speichern setzt den zwischengespeicherten Sitzungs-/Benutzerzustand zurück. Ein Instanzwechsel entfernt die alten gespeicherten Geheimnisse, damit sie nicht für den neuen Host wiederverwendet werden.
- Das API-Token wird verschlüsselt in WordPress-Optionen gespeichert. Das macht Datenbankzugriff, Backups und Administratorrechte nicht unkritisch. Zugangsdaten und Sitzungsdaten niemals in Support-Anfragen oder Screenshots veröffentlichen.

Verwende einen ChurchTools-Account mit nur den benötigten Rechten. Zusätzliche Rechte in ChurchTools können zusätzliche Daten für den Import zugänglich machen.

## Kalender

1. **Kalender jetzt synchronisieren** lädt die verfügbare Kalenderliste, noch nicht die eigentlichen Termine.
2. Kalender markieren und **Auswahl speichern**. Die Liste zeigt **Kalender**, **ChurchTools-ID**, **Sichtbarkeit**, **Farbe** und **Fallback-Bild**.
3. Optional über **Bild wählen** ein vorhandenes Mediathek-Bild zuordnen; **Entfernen** hebt diese Zuordnung auf. Das Bild dient als Ersatz, wenn ein Termin kein eigenes Bild hat.
4. Danach [Termine synchronisieren](./synchronisation.md).

Die Sichtbarkeitsangabe **Privat** ist ein Hinweis auf die Quelle und kein Ersatz für eine Prüfung der WordPress-Ausgabe. Kontrolliere vor dem Veröffentlichen ausdrücklich, welche Termine, Texte, Bilder und Dienste öffentlich sichtbar werden. Importierte Bilder liegen in deiner WordPress-Mediathek; eine geschützte ChurchTools-Quelle garantiert keinen privaten WordPress-Medienzugriff.

## Dienste

Für Dienste die Reihenfolge auf der Seite **Einstellungen → Dienste** einhalten:

1. **Dienstgruppen synchronisieren**.
2. Benötigte Dienstgruppen markieren, gegebenenfalls **Sortierung** anpassen und **Auswahl speichern**.
3. **Dienste synchronisieren**.
4. Gewünschte Dienste auswählen, Sortierung anpassen und erneut **Auswahl speichern**.
5. Termine synchronisieren und **Übersichten → Importierte Dienste** kontrollieren. Die Ausgabe in einer Terminansicht mit `show_services="true"` einschalten.

Nicht jede Ansicht zeigt dieselben Felder. Prüfe außerdem, ob Namen und Dienstzuordnungen öffentlich erscheinen dürfen; die WordPress-Seite ist nicht automatisch auf ChurchTools-Mitglieder beschränkt.

## Darstellung

Unter **Einstellungen → Darstellung** legen **Standard Single Template** und **Standard Modal Template** das Layout für Einzelseiten und Modal-Overlays fest. Vorgabe ist jeweils `professional`; `minimal` ist die reduzierte Alternative.

Das Verhalten beim Klick stellst du am Block oder Shortcode ein, nicht global auf dieser Einstellungsseite. Beispiel: `event_action="modal"` oder `event_action="page"` im Shortcode. Weitere [Beispiele und Vorlagen](./termine-anzeigen.md).

## Sync-Einstellungen, Posts und Erweitert

- **Sync-Einstellungen**: Zeitraum, Löschalter und Auto-Sync werden auf der Seite [Synchronisation](./synchronisation.md) erklärt.
- **Posts**: Einstellungen erscheinen erst mit aktivem Posts-Sync-Addon; siehe [Erweiterungen](./erweiterungen.md#posts-sync).
- **Erweitert → Erweiterter Modus**: standardmäßig aus. Einschalten und **Speichern** blendet **Erweiterte Diagnose** mit Status, Logs und Wartungsaktionen ein. Nicht allein zur Einrichtung erforderlich. Lösch-/Reset-Aktionen sind keine normale Fehlerbehebung; vorher sichern.
