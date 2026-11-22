# Changelog

Nutzerbezogene Auswahl der vorhandenen [ausführlichen Release Notes](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/CHANGELOG.md) und des [ursprünglichen Changelogs](https://github.com/sw-wordpress/churchtools-suite/blob/main/CHANGELOG.md). Historische Versionsbezeichnungen und Daten bleiben erhalten. Aus einer neueren Versionsnummer im Plugin-Header wird hier keine fehlende Release-Historie abgeleitet.

## Unreleased

### Bugs

- Posts Sync ergänzt beim Download von Beitragsbildern fehlende Größenparameter; vorhandene Parameter und Beitragsbilder bleiben erhalten. Für den erneuten Import eines zu kleinen Bildes die Beitragsbild-Zuordnung im Editor entfernen.

### Other topics

- Deutsche Nutzerdokumentation mit Einrichtung, Einstellungen, Synchronisation, Terminansichten, Erweiterungen, Fehlerbehebung und Changelog sowie echten bereinigten Demoaufnahmen ergänzt; Veröffentlichung über GitHub Pages vorbereitet.

## 1.4.0.7 – 7. November 2026

### Bugs

- Die Terminübersicht beginnt standardmäßig beim heutigen lokalen Datum. Leeres Startdatum zeigt auch vergangene Termine; explizite Filter bleiben erhalten, Tagesgrenzen berücksichtigen die WordPress-Zeitzone.

### Other topics

- Betriebsanleitung um sichere Befehle zur Einrichtung der Release-Signierschlüssel ergänzt.

## 1.4.0.6 – 6. November 2026

### Feature

- Signierte GitHub-Releases lassen sich über die native WordPress-Pluginübersicht prüfen und manuell installieren. Signatur, Prüfsumme und ZIP-Inhalt werden vor der Installation geprüft.

### Other topics

- Alte Updater und ungeprüfte Downloadpfade stillgelegt. Automatische Installation bleibt gesperrt; ohne öffentlichen Vertrauensschlüssel ist der neue Provider nicht verfügbar.

## 1.4.0.5 – 6. November 2026

### Other topics

- Oberfläche und Übersetzungen verwenden einheitlich „Dienste“ und „Dienstgruppen“; technische Parameter bleiben kompatibel.

## 1.4.0.4 – 5. November 2026

### Bugs

- Beschriftungen von Tabellen und Schaltern verbessert; Einstellungen und vorhandene Verwaltungs-URLs bei zusammengeführten Bildschirmdefinitionen beibehalten.

### Other topics

- Zusätzliche Integrations- und Browserprüfungen für Einstellungen, Wiederholungstermine und Zugangsdaten ergänzt.

## 1.4.0.3 – 5. November 2026

### Feature

- Erweiterte Diagnose zeigt Umgebung, Verbindungszustand, Datenbestand und Cron-Hinweise mit einem Export ohne Zugangsdaten oder Termininhalte.

### Bugs

- Breite Termin-, Dienste- und Posts-Tabellen bleiben innerhalb ihres Bereichs scrollbar; Terminfilter und Ergebniszähler stimmen überein.

### Other topics

- Mitgelieferte Vorlagenbilder entfernt; vorhandene Mediathek- und eigene Kalenderbilder bleiben erhalten.
- Posts-Einstellungen erklären ohne aktives Addon die Voraussetzung und verlinken die Plugin-Verwaltung.

## 1.4.0.2 – 5. November 2026

### Bugs

- Admin-Skript wird wieder geladen, sodass Anmeldemethoden umschaltbar sind und „Verbindung testen“ ausgeführt wird.
- Nicht benötigte Anmeldefelder sind von Beginn an verborgen; ungültige Eingaben zeigen Rückmeldungen am Formular.

## 1.4.0.1 – 5. November 2026

### Feature

- „Verbindung testen“ prüft aktuelle Formulareingaben, ohne gespeicherte Zugangsdaten oder die aktive Sitzung zu ändern; Fortschritt und Fehler erscheinen direkt am Formular.

### Bugs

- Formulare nutzen die verfügbare Breite, Feldbeschriftungen und Preset-Bedienung per Tastatur wurden verbessert.
- Gespeicherte Geheimnisse erscheinen nicht im HTML und können bei unverändertem Account durch leere Felder beibehalten werden.

## 1.4.0.0 – 4. November 2026

### Feature

- Verwaltungsoberfläche mit linker Navigation, Karten, Statusanzeigen, Hell-/Dunkeldarstellung und einklappbarer mobiler Navigation eingeführt. Bestehende Verwaltungs-URLs bleiben kompatibel.

### Bugs

- Rekursive Diagnoseseite und doppelte Hintergrundaktionen behoben; die Posts-Übersicht verwendet die gemeinsame Verwaltungsoberfläche.

## 1.3.0.11 – 28. August 2026

### Bugs

- Veralteten Skript-Callback entfernt, der in der WordPress-Pluginverwaltung einen Fatal Error auslöste.

## 1.3.0.10 – 28. August 2026

### Bugs

- „+N mehr“ im Monatskalender klappt weitere Tagestermine auf und zu; die vorhandene Termin-Klickaktion bleibt erhalten.

## 1.3.0.9 – 28. August 2026

### Bugs

- Der damalige Updater verwendet das geprüfte WordPress-Release-ZIP statt GitHub-Source-Archiven oder beliebigen ZIP-Fallbacks. Der alte Updater ist seit 1.4.0.6 stillgelegt.

## 1.3.0.8 – 27. August 2026

### Bugs

- Shortcode-Parameter `calendar_ids` unterstützt kommagetrennte Kalender-IDs; `calendar` und `calendars` bleiben kompatibel.

## 1.2.5 – 15. September 2026

### Bugs

- Doppelte AJAX-Anfragen beim Anklicken von Terminen in Listen verhindert.
- Kalenderansicht nutzt die volle Breite; Textfarbe berücksichtigt die verwendete Kalenderfarbe.

Die historischen Versionsnummern und Daten der beiden Quelldokumente sind nicht überall chronologisch konsistent. Diese Seite sortiert nach Versionen, nicht nach dem Datum; ältere Einträge stehen weiterhin in den verlinkten Originalen.
