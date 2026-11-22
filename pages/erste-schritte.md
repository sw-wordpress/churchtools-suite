# Erste Schritte

Diese Anleitung richtet sich an die Administration einer WordPress-Website. Die Suite liest ChurchTools-Daten in lokale WordPress-Tabellen ein. Die öffentliche Ausgabe erfolgt anschließend über Ansichten auf deiner Website, nicht durch das Einbetten der ChurchTools-Verwaltung.

## Voraussetzungen und Installation

- WordPress ab **6.0**, PHP ab **8.2** und die Berechtigung, Plugins zu installieren und zu aktivieren.
- Eine ChurchTools-Instanz unter `INSTANZ.church.tools` und ein Account mit Leserechten für die gewünschten Kalender, Termine und gegebenenfalls Dienste/Gruppenposts. Die Suite ersetzt keine ChurchTools-Berechtigungen.
- Ausgehende HTTPS-Verbindungen vom WordPress-Server zu dieser Instanz sowie Speicherplatz für importierte Medien.

1. Lade das installierbare Hauptplugin-ZIP `churchtools-suite-<version>.zip` aus den [Releases dieses Forks](https://github.com/sw-wordpress/churchtools-suite/releases). Die GitHub-Archive „Source code“ sind nicht das für WordPress gebaute Installationspaket.
2. Öffne **Plugins → Installieren → Plugin hochladen**, wähle das ZIP und installiere und aktiviere es.
3. Öffne **ChurchTools Integration** im WordPress-Menü. Installiere nur bei Bedarf die getrennten [Addon-ZIPs](./erweiterungen.md); das Hauptplugin-ZIP enthält diese nicht.

Erstelle vor Aktualisierungen und größeren Import-/Löschläufen eine Sicherung deiner WordPress-Datenbank und Medien.

![Dashboard der ChurchTools Integration Suite mit Status „Nicht konfiguriert“, Cronjob-Karten und null importierten Terminen](/screenshots/churchtools-suite-dashboard.png)

*Echte Demoaufnahme der Version 1.4.4 vor der ChurchTools-Einrichtung. Es sind keine Kalender ausgewählt und keine Termine importiert; angezeigte Cron-Planungen sind noch kein erfolgreicher Import.*

## ChurchTools verbinden

1. Öffne **Einstellungen → API & Verbindung**.
2. Trage bei **ChurchTools-Instanz** nur den Instanznamen ein: für `https://beispiel.church.tools` also `beispiel`. Das Formular ist auf diese ChurchTools-Domainform beschränkt; keine vollständige URL eingeben.
3. Wähle **Benutzername & Passwort** (Vorgabe) oder **API-Token**. Bei Passwortanmeldung ist der Benutzername die E-Mail-Adresse. Ein persönliches API-Token findest du in ChurchTools über das Profilbild unter **API-Token**; es benötigt die passenden Kalender-/Event-Rechte.
4. Klicke **Verbindung testen** und lies die Rückmeldung direkt am Formular. Der Test prüft die aktuellen Eingaben, speichert sie aber nicht und ersetzt keine aktive Sitzung.
5. Klicke bei erfolgreichem Test **Speichern**.

Gespeicherte Geheimnisse werden nicht in den Eingabefeldern angezeigt. Leer lassen behält das Passwort nur für dieselbe Instanz und denselben Benutzer, das Token nur für dieselbe Instanz. Nach Instanz-/Benutzerwechsel neue Zugangsdaten eingeben. Mehr dazu unter [Einstellungen](./einstellungen.md).

## Erste Termine importieren

1. Öffne **Einstellungen → Kalender** und klicke **Kalender jetzt synchronisieren**.
2. Markiere nur die Kalender, deren Inhalte auf dieser Website verwendet werden sollen. Klicke **Auswahl speichern**. Notiere bei Bedarf die angezeigte **ChurchTools-ID** für einen späteren Filter.
3. Lass unter **Einstellungen → Sync-Einstellungen** für den ersten Test Auto-Sync ausgeschaltet. Die Vorgaben importieren **7 vergangene** und **90 zukünftige Tage**.
4. Öffne **Synchronisation** und klicke **Termine jetzt synchronisieren**. Warte die Rückmeldung ab.
5. Kontrolliere **Übersichten → Termine**. Die Liste beginnt standardmäßig heute; für ältere Termine das Startdatum bewusst leeren oder ändern und den Filter anwenden.

## Erste Ausgabe auf einer Seite

1. Öffne oder erstelle eine WordPress-Seite.
2. Füge einen **Shortcode**-Block ein und trage ein:

```text
[cts_list view="list-modern-with-images" limit="5"]
```

3. Vorschau öffnen. Prüfe Titel, Datum, Bilder und Beschreibungen; kontrolliere die Seite auch ohne Anmeldung. Veröffentliche erst, wenn die Ausgabe für die Öffentlichkeit bestimmt ist.

Wenn keine Termine erscheinen, prüfe zuerst Kalenderauswahl, Importzeitraum und [Fehlerbehebung](./probleme-loesen.md). Anschließend kannst du [Auto-Sync einrichten](./synchronisation.md) oder [weitere Darstellungen](./termine-anzeigen.md) verwenden.
