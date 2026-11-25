# Probleme lösen

Zuerst die konkrete Rückmeldung lesen und mit einem einzelnen überschaubaren Import prüfen. Nicht mit produktiven Daten experimentieren oder zur Fehlersuche pauschal Reset-/Löschaktionen ausführen.

## Verbindungstest schlägt fehl

- **ChurchTools-Instanz** darf nur den Namen enthalten, ohne Protokoll, Pfad und `.church.tools`. Das Formular unterstützt keine beliebige eigene Host-URL.
- Gewählte **Anmeldemethode** prüfen: E-Mail/Passwort oder persönliches API-Token mit passenden Leserechten.
- Bei Instanz-/Benutzerwechsel neue Geheimnisse eingeben. Ein leeres Feld heißt nur beim selben gespeicherten Kontext „beibehalten“.
- **Verbindung testen** verwendet ungespeicherte Formularwerte. Nach Erfolg separat **Speichern** wählen, sonst nutzt der spätere Sync weiterhin die alte Konfiguration.
- Bei Timeout, HTTP- oder Berechtigungsfehlern die Erreichbarkeit vom WordPress-Server und die ChurchTools-Rechte prüfen lassen. Ein erfolgreicher Browserlogin am eigenen Rechner beweist keine ausgehende Serververbindung.

## Kalender vorhanden, aber keine Termine

1. Unter **Einstellungen → Kalender** mindestens einen Kalender markieren und **Auswahl speichern**.
2. Prüfen, ob der betreffende Termin im Importzeitraum liegt (Vorgabe: 7 Tage zurück und 90 Tage voraus).
3. **Synchronisation** ausführen und die Rückmeldung prüfen. Eine Kalender-Synchronisation allein lädt keine Termine.
4. Unter **Übersichten → Termine** Datumsfilter kontrollieren: standardmäßig ab heute. Für alte Termine das Startdatum leeren oder ändern.
5. Im Shortcode korrekte ChurchTools-Kalender-IDs und die für den konkreten Shortcode unterstützten Filter prüfen. Bei `cts_list` etwa `calendars="2,5"` verwenden, nicht `calendar_ids`. Benutzerdefinierte Preset-Slugs werden derzeit nicht als `view` aufgelöst; stattdessen eine unterstützte View und die Filter direkt als Attribute angeben.
6. Erst wenn Auswahl/Rechte/Zeitraum stimmen, bei Bedarf **Vollständigen Sync erzwingen** nutzen.

## Manueller Sync deaktiviert oder automatische Daten veraltet

Mit aktivem Auto-Sync ist die manuelle Termin-Synchronisation auf der Seite deaktiviert. Für einen manuellen Lauf Auto-Sync in **Sync-Einstellungen** vorübergehend deaktivieren und speichern.

Bei Auto-Sync im Dashboard **Nächster geplanter Auto-Sync** und **Letzter erfolgreicher Auto-Sync** unterscheiden: eine Planung ist noch kein Erfolg. WordPress-Cron wird normalerweise durch Besucher ausgelöst. Bei `DISABLE_WP_CRON` bzw. wenig Traffic regelmäßig extern `wp-cron.php` aufrufen lassen. Siehe [Cron-Einrichtung](./synchronisation.md#auto-sync-und-wordpress-cron).

## Bilder fehlen oder sind zu klein

- In **Einstellungen → Kalender** über **Bild wählen** ein Fallback-Bild aus der Mediathek zuordnen und speichern.
- Ein eigenes Terminbild muss importiert werden können; geschützte ChurchTools-Bilder benötigen die passende Anmeldung. Serververbindung, Quellrechte und Mediathek prüfen.
- Bereits importierte Bilder sind lokale Dateien. Keine privaten Quell-URLs oder Sitzungscookies in öffentliche Fehlerberichte übernehmen.
- Für Posts-Sync-Beitragsbilder gilt laut bestehender unveröffentlichter Fehlerkorrektur: zum erneuten Import eines zu kleinen Bildes dessen **Beitragsbild-Zuordnung im Editor entfernen**, dann erneut synchronisieren. Das vorhandene Bild bleibt sonst erhalten. Nicht als ersten Schritt die ganze Mediathek löschen.

## Einstellungen oder Menüpunkte fehlen

Die Suite verwendet eigene WordPress-Berechtigungen; Verwaltung und API-Konfiguration sind getrennte Rechte. Eine Person mit Backend-Zugriff besitzt nicht automatisch Konfigurationsrechte. Lass einen WordPress-Administrator die Rechte prüfen, statt Nonces oder Zugriffsschutz zu umgehen.

**Posts** benötigt das aktive Posts-Sync-Addon. **Erweiterte Diagnose** wird erst nach **Einstellungen → Erweitert → Erweiterter Modus → Speichern** angezeigt. Bei abgelaufener Sicherheitsprüfung Seite neu laden und Aktion erneut ausführen.

## Diagnose und Support

Im erweiterten Modus enthält **Erweiterte Diagnose** unter anderem **Übersicht**, **Manuelle Trigger**, **Logs** sowie **Reset & Aufräumen**. Status und Systeminformationen zuerst lesen. Der Diagnose-JSON-Export enthält lokale Zustände und Zähler, nicht Zugangsdaten oder Termininhalte; prüfe trotzdem jeden Export vor der Weitergabe.

Für einen [Fehlerbericht](https://github.com/sw-wordpress/churchtools-suite/issues) genügen normalerweise WordPress-/PHP-/Pluginversion, genauer Bildschirm, Schritte, Fehlermeldung und erwartetes Ergebnis. Logs können API-Inhalte oder personenbezogene Informationen betreffen: nur benötigte bereinigte Ausschnitte weitergeben, niemals Passwort, Token, Cookies oder private Termindetails. Destruktive Aktionen nur nach Sicherung und mit geklärter Wirkung verwenden.

## Updates

Unter **Plugins → Installierte Plugins** kann **Jetzt auf Updates prüfen** den gemeinsamen GitHub-Release prüfen. Installation erfolgt manuell über WordPress; automatische Installation ist für diesen Provider gesperrt. Backend-Prüfungen erfolgen höchstens einmal je 24 Stunden; manuelle Prüfungen haben einen Mindestabstand von 60 Sekunden.

Ohne eingerichteten öffentlichen Vertrauensschlüssel bleibt der neue GitHub-Provider gesperrt. Das ist kein Grund, ungeprüfte ZIPs zuzulassen oder alte Updater einzuschalten. Die Server-/Repository-Administration richtet signierte Releases anhand der [Betriebsanleitung](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/GITHUB-UPDATES-BETRIEB.md) ein. Alternativ installierbare Release-ZIPs nach Sicherung manuell über WordPress einspielen; keine Source-Archive als Ersatz.
