# Erweiterungen

Das Hauptplugin funktioniert ohne Addons. Die drei Addons werden in diesem Repository gepflegt, aber als eigene WordPress-Plugins installiert. Lade das jeweilige installierbare ZIP aus den [Releases](https://github.com/sw-wordpress/churchtools-suite/releases), installiere es über **Plugins → Installieren → Plugin hochladen** und aktiviere es erst nach dem Hauptplugin. Mit dem Hauptplugin bleibt PHP ab 8.2 die gemeinsame Voraussetzung.

## Elementor

Das ZIP `churchtools-suite-elementor-<version>.zip` ergänzt ein Elementor-Widget für Terminansichten. Zusätzlich muss Elementor aktiv sein. Nach Aktivierung eine Seite im Elementor-Editor öffnen, das ChurchTools-Widget einfügen und Ansicht, Kalenderfilter und Anzeigeoptionen einstellen. Die Daten stammen aus dem vorhandenen Import; Elementor synchronisiert keine eigenen Kalender.

Die technische [Addon-Readme](https://github.com/sw-wordpress/churchtools-suite/blob/main/addons/churchtools-suite-elementor/readme.txt) enthält weitere Hinweise. Prüfe die veröffentlichte Seite; eine Editor-Vorschau allein belegt nicht die richtige öffentliche Ausgabe.

## Posts Sync

Dieses Addon übernimmt ChurchTools-Gruppenposts in WordPress-Inhalte. Es ist kein Export von WordPress nach ChurchTools.

1. `churchtools-suite-posts-sync-<version>.zip` installieren und aktivieren.
2. **Einstellungen → Posts** öffnen. Ohne aktives Addon zeigt diese Seite nur den entsprechenden Hinweis und den Link zur Plugin-Verwaltung.
3. **Ziel in WordPress** und **Status der Zielinhalte** festlegen. Vorgabe ist der Addon-Inhaltstyp `ct_post` und **Entwurf** (`draft`); **Sync-Limit** steht auf **200**.
4. Über **Post-Gruppen jetzt synchronisieren** verfügbare Gruppen laden; danach gewünschte **Post-Gruppen-IDs** auswählen. Bei Bedarf **Zeitraum ab (lokale Zeit)**, **Zeitraum bis (lokale Zeit)**, **Campus-IDs**, **Personen-IDs** und Sichtbarkeitsfilter einschränken.
5. **Posts-Sync aktivieren** einschalten und **Posts-Einstellungen speichern**. Unter **Übersichten → Übersicht Posts** auf **Jetzt manuell synchronisieren** klicken und Ergebnis sowie importierte Inhalte kontrollieren.
6. Erst nach Prüfung gewünschte WordPress-Inhalte veröffentlichen.

Zusätzliche API-Optionen (`only_my_groups`, Kommentare, Verknüpfungen und Reaktionen) sind standardmäßig ausgeschaltet. Sie können den Umfang importierter Daten verändern; nur bei tatsächlichem Bedarf aktivieren. Die Quell-Sichtbarkeit ist kein Ersatz für den WordPress-Status. Der Fork unterstützt außerdem Markdown, Bildausrichtung, Beitragsablauf und setzt archivierte Posts auf privat; eine spätere Synchronisation kann deshalb den Zustand importierter Inhalte ändern.

Eine Ausgabe synchronisierter Inhalte ist auch mit Shortcode möglich:

```text
[cts_posts limit="5" show_excerpt="true"]
```

Der Block **ChurchTools Posts** bietet passende Optionen. Mehr zu Parametern steht in der [Shortcode-Referenz](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/SHORTCODES.md).

## Presentations

`churchtools-suite-presentations-<version>.zip` stellt eine Präsentationsseite mit wechselnden Folien bereit.

1. In der Addon-Verwaltung die Seite **ChurchTools Präsentation** öffnen.
2. **Leit-Termin** auswählen; er steuert die Folie „Heute Dienste“.
3. **Slide-Dauer (Sekunden)**, **Folie 1: Nächste Termine (View)**, **Folie 2: Besondere Termine (View)** und optional **Tags für Besondere Termine** festlegen.
4. **Einstellungen speichern**, anschließend **Präsentations-Seite jetzt erstellen/aktualisieren** verwenden.
5. Mit **Seite öffnen** die erzeugte Seite kontrollieren; insbesondere Inhalte und Dienstzuordnungen auf öffentliche Sichtbarkeit prüfen.

Vorgaben: 10 Sekunden pro Folie, mindestens 3 Sekunden, erste View `list-classic`, zweite View `grid-modern`. **Automatisch Seite erstellen/aktualisieren** ist zunächst aus. Die zusätzliche Bedingung **Nur wenn unterstützter Page Builder aktiv ist** ist zunächst an.

Die erzeugte Seite enthält `[cts_presentation]` und die nötigen seitenspezifischen Einstellungen. Den Shortcode allein in eine beliebige neue Seite zu kopieren aktiviert dort noch keinen Präsentationsmodus. Vorhandene Präsentationsseiten können mit `page_id` referenziert werden; siehe [Referenz](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/SHORTCODES.md).

## SW CloudCalendar

Mit zusätzlich installiertem SW CloudCalendar können vorhandene Termine zusammen mit anderen Kalenderquellen dargestellt werden:

1. In ChurchTools Suite die gewünschten Kalender auswählen und Termine synchronisieren.
2. In CloudCalendar eine Verbindung vom Typ **Lokales Plugin → ChurchTools Integration Suite** erstellen.
3. Kalender als Quellen hinzufügen und deren Sichtbarkeit ausdrücklich freigeben.
4. Quellen in einer CloudCalendar-Ansicht zusammenstellen und diese einbinden.

Es entsteht weder ein zweiter ChurchTools-Sync noch eine zweite Termin-Kopie in CloudCalendar. Das Abwählen eines Kalenders in ChurchTools Suite entzieht die Ausgabe über diese Quelle sofort. Bestehende `cts_*`-Shortcodes bleiben nutzbar.

Der Adapter liefert Termindaten und bereits vorhandene lokale Mediathek-Vorschaubilder, aber keine rohen API-Payloads, Zugangsdaten oder Dienst-/Personendaten. Externe geschützte Bild-Downloads sind über diesen Adapter nicht unterstützt. Kalenderfarben sind die Vorgabe, können aber in Quelle oder Ansicht überschrieben werden. Details: [CloudCalendar-Anbindung](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/CLOUDCALENDAR.md).
