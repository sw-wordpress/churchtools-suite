# Synchronisation

Die Suite hält lokale Kopien ausgewählter ChurchTools-Daten vor. Änderungen in ChurchTools erscheinen deshalb erst nach einem erfolgreichen Import. Eine öffentliche Terminansicht ersetzt keinen Sync.

## Zeitraum und erster manueller Lauf

Unter **Einstellungen → Sync-Einstellungen** gelten ohne eigene Änderung:

| Einstellung | Vorgabe | Bedeutung |
| --- | --- | --- |
| Vergangene Tage | 7 | Rückblick des Terminimports |
| Zukünftige Tage | 90 | Vorlauf des Terminimports |
| Alte Termine löschen | Aus | Optionale Bereinigung alter Termine und ihrer importierten Bilder |
| Löschalter | 30 Tage | Mindestalter nach Terminende für die Bereinigung |
| Auto-Sync aktivieren | Aus | Automatischen Terminimport einschalten |
| Synchronisations-Intervall | Täglich | Verwendet, wenn Auto-Sync aktiviert wird |

1. Zuerst Verbindung speichern und unter **Einstellungen → Kalender** Kalender synchronisieren und auswählen.
2. Gewünschten Zeitraum speichern.
3. Auf **Synchronisation → Termine jetzt synchronisieren** klicken und Rückmeldung abwarten.
4. In **Übersichten → Termine** sowie im Frontend prüfen. Für vergangene Termine den Datumsfilter der Übersicht ändern.

Der Import berücksichtigt Termine mit Event-Zuordnung und eigenständige Kalendertermine. Nach dem ersten Lauf kann er inkrementell arbeiten. **Vollständigen Sync erzwingen** erneut alle Termine im eingestellten Zeitraum abrufen lassen, wenn ein normaler Import nicht reicht. Es ist keine Erweiterung des Zeitraums und kein Ersatz für fehlende Quellrechte.

## Auto-Sync und WordPress-Cron

1. **Auto-Sync aktivieren** markieren.
2. **Synchronisations-Intervall** wählen und **Speichern**: täglich, alle 2 Tage, alle 3 Tage, wöchentlich, alle 2 Wochen oder monatlich.
3. **Nächster geplanter Auto-Sync** und **Letzter erfolgreicher Auto-Sync** im Dashboard bzw. in den Sync-Einstellungen kontrollieren.

Mit aktivem Auto-Sync ist die manuelle Termin-Sync-Schaltfläche auf der Synchronisationsseite deaktiviert. Für einen gezielten manuellen Erst-/Kontrolllauf Auto-Sync vorübergehend abschalten und speichern; anschließend bei Bedarf wieder einschalten.

WP-Cron läuft normalerweise durch Seitenbesuche. Ein geplanter Zeitpunkt ist daher keine Zusage für eine sekundengenaue Ausführung. Bei wenig Traffic oder `DISABLE_WP_CRON` muss die Serveradministration `wp-cron.php` regelmäßig extern aufrufen. Beispiel eines System-Cron-Eintrags (Domain ersetzen):

```text
*/5 * * * * curl --fail --silent --show-error https://example.org/wp-cron.php >/dev/null
```

Externer Cron ruft WordPress auf, nicht eine eigene ungeschützte Sync-URL. Prüfe beim Hosting, dass der Aufruf erlaubt ist und Fehler sichtbar bleiben. **Nicht geplant** oder **Noch kein erfolgreicher Auto-Sync** bedeutet nicht, dass bereits erfolgreich synchronisiert wurde.

## Alte Termine und Medien löschen

**Alte Termine löschen** ist standardmäßig deaktiviert. Bei Aktivierung werden abgelaufene Termine nach dem konfigurierten Löschalter bereinigt, einschließlich zugehöriger vom Plugin importierter Bilder. Bilder können an anderer Stelle eingebunden sein: vorher prüfen und sichern. **Löschalter** ist auf 0 bis 3650 Tage begrenzt.

Den Importzeitraum zu vergrößern stellt gelöschte Medien nicht aus einem Backup wieder her. Fehlende oder nicht mehr zugängliche Quelldaten können nicht erneut importiert werden.

## Umfang prüfen

Der **Modulstatus** zeigt, welche Datenbereiche verfügbar sind. Posts benötigen das separate Addon und dessen eigene Freigabe/Filter. Kalender-/Diensteauswahl und Terminimport sind unterschiedliche Schritte; eine gefüllte Kalenderliste belegt noch keinen erfolgreichen Terminimport. Bei Fehlern die [Diagnose](./probleme-loesen.md) nutzen, statt Reset-Aktionen auszuprobieren.
