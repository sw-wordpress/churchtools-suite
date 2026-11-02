# CloudCalendar-Anbindung

Dieses Plugin implementiert API v1 der lokalen Plugin-Quellen von SW CloudCalendar. Der Adapter liegt vollständig
in `includes/integrations/`; CloudCalendar enthält keine ChurchTools-spezifische Implementierung. Ohne CloudCalendar
wird die Adapterdatei nicht geladen und ChurchTools arbeitet wie bisher.

## Verwendung

In ChurchTools Suite die zu exportierenden Kalender auswählen. Anschließend in CloudCalendar eine Verbindung vom
Typ **Lokales Plugin → ChurchTools Integration Suite** erstellen, Kalender als Quellen hinzufügen und deren
Sichtbarkeit ausdrücklich freigeben. Mehrere Kalender lassen sich in einer Ansicht mit jeweils eigener Farbe
kombinieren. Kalenderfarben werden als Vorgabe übernommen; Quelle und Ansicht können sie überschreiben.

Liste mit Bildern und Monatsraster können dieselbe Auswahl verwenden. Darstellung und Beispiel-Shortcodes:
[CloudCalendar-Vertrag](../../sw-cloudcalendar/docs/local-sources.md). Dieser relative Link gilt im gemeinsamen
Checkout; die Vertragsdokumentation wird mit CloudCalendar ausgeliefert.

Es werden vorhandene Zeilen der Event-Tabelle gelesen, einschließlich importierter Events anderer Systeme in dieser
Tabelle. Die vorbereitete `schedule`-Tabelle wird nicht als zusätzliche Terminquelle interpretiert. Es gibt weder einen
zweiten Sync noch duplizierte Events in CloudCalendar. Das Abwählen eines Kalenders entzieht die Ausgabe sofort.

Die Zuordnung verwendet die lokale Event-Zeilen-ID. Verschiedene Wiederholungsinstanzen bleiben getrennt. Datumswerte
zeitgebundener Events sind wie beim bestehenden Import UTC; Ganztagswerte sind reine UTC-Mitternachtsdaten mit
exklusivem Ende. Fehlendes/ungültiges Ende wird als ein Tag (ganztägig) beziehungsweise eine Sekunde (zeitgebunden)
normalisiert. Eine Verschiebung, die der Import durch Löschen und Neuanlegen abbildet, erzeugt eine neue Referenz.

Vorschaubilder stammen ausschließlich aus vorhandenen WordPress-Medien (`image_attachment_id`). Metadaten werden
gebündelt geladen. Gespeicherte externe Bild-URLs, rohe API-Payloads, Dienst-/Personendaten und Authentifizierungsdaten
werden nicht exportiert. Private Medien-Downloads sind nicht unterstützt.

## Öffentliche PHP-Leseschnittstelle

`ChurchTools_Suite_Calendar_Reader::calendars()` liefert ausgewählte Kalender.
`query($calendar_ids, $query)` liefert `['items' => [...], 'total' => N]`. Optionen: UTC-Strings `start`/`end`,
`floating_start`/`floating_end`, `search`, `location`, `exclude` (Zeilen-IDs), `descending`, `limit`, `offset`,
`sort_offset` (Sekunden), `ended_before_range_end`. `id` erlaubt eine scoped Detailabfrage. SQL verwendet vorbereitete
Parameter und einen Join auf die aktuell ausgewählten Kalender. Der Adapter übersetzt diese Ausgabe in die
CloudCalendar-DTOs. Eine Schemaänderung bleibt so innerhalb dieses Plugins.

## Integrationstests

Benötigt PHP 8.2+, MariaDB und das benachbarte CloudCalendar-Repository mit dessen `tests/wp`-Abhängigkeiten:

```sh
# Im CloudCalendar-Checkout einmalig: bin/test-db.sh start
# Im ChurchTools-Checkout:
../sw-cloudcalendar/tests/wp/vendor/bin/phpunit -c tests/phpunit.xml.dist
XDEBUG_MODE=coverage ../sw-cloudcalendar/tests/wp/vendor/bin/phpunit -c tests/phpunit.xml.dist --coverage-clover coverage/clover.xml
```

Ein anderer Checkout-Pfad kann über `SW_CLOUDCALENDAR_DIR` gesetzt werden. Der Harness nutzt die private Testdatenbank
und kontaktiert keine ChurchTools-API. Er prüft echte Registrierung, gemeinsame Darstellung, Bilder, Zeitzonen/
Zeitraumüberschneidungen, Filter, Seiten, Rechteentzug und fehlende Event-Kopien. Der Coverage-Bericht umfasst den
gesamten vorhandenen Plugin-Code; diese neue Suite ersetzt keine umfassende Testsuite für die übrigen Funktionen.
