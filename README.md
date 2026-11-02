# ChurchTools Integration Suite (Fork)

WordPress-Plugin, das Kalender, Termine und Dienste aus [ChurchTools](https://church.tools) nach WordPress synchronisiert und im Frontend darstellt.

Dieses Repository ist ein **Fork** von [feg-aschaffenburg/churchtools-suite](https://github.com/feg-aschaffenburg/churchtools-suite). Anders als im Original liegen die Addons hier weiterhin im selben Repository (Monorepo).

## Danke an das Upstream-Projekt

Dieses Plugin wäre ohne die Arbeit des Teams der **FEG Aschaffenburg** nicht möglich. Sie haben die ChurchTools Integration Suite entworfen, gebaut und über viele Releases gepflegt. Die Architektur mit Repository-Pattern, zweistufigem Event-Sync, Template-System und Shortcodes, die Dokumentation und die laufende Weiterentwicklung sind großartig umgesetzt. Dieser Fork baut direkt darauf auf und bleibt über den `upstream`-Remote mit dem Original verbunden. Vielen Dank dafür!

Wenn du das Plugin ohne die Anpassungen dieses Forks nutzen willst, schau im Original-Repository vorbei.

## Inhalt des Repositories

| Paket | Typ | Version | Pfad |
| --- | --- | --- | --- |
| ChurchTools Integration Suite | Hauptplugin | 1.3.0.14 | `churchtools-suite.php` |
| Elementor Integration | Addon | 1.0.0.1 | `addons/churchtools-suite-elementor/` |
| Posts Sync | Addon | 0.1.8 | `addons/churchtools-suite-posts-sync/` |
| Presentations | Addon | 0.1.1 | `addons/churchtools-suite-presentations/` |

Das Upstream-Projekt hat die Addons inzwischen in eigene Repositories ausgelagert ([Elementor](https://github.com/feg-aschaffenburg/churchtools-suite-elementor), [Posts Sync](https://github.com/feg-aschaffenburg/churchtools-suite-posts-sync), [Presentations](https://github.com/feg-aschaffenburg/churchtools-suite-presentations)). In diesem Fork bleiben sie im Monorepo. Änderungen aus den Upstream-Addons werden bei Bedarf von Hand übernommen.

### Unterschiede zum Upstream

- **Addons im Monorepo:** Die drei Addons liegen unter `addons/`.
- **Posts Sync:** Der Fork enthält eigene Erweiterungen (Markdown-Parser, Bildausrichtung in Posts, Beitragsablauf, archivierte Posts werden automatisch auf privat gesetzt). Der Stand weicht deshalb von `churchtools-suite-posts-sync` im Upstream ab.
- **Bild-Import:** Bilder aus ChurchTools werden mit der gespeicherten ChurchTools-Session heruntergeladen und lokal in die Mediathek importiert. Die geschützten Bild-URLs liefern sonst nur eine Login-Seite.
- **Kalender-Darstellung:** Eigene CSS-Anpassungen im Monatskalender (gleiche Spaltenbreiten, Zeilenhöhe).
- **Updater deaktiviert:** Der GitHub-Updater ist abgeschaltet (`ChurchTools_Suite_Auto_Updater::GITHUB_UPDATES_ENABLED = false`). Updates werden manuell eingespielt.

### Abhängigkeiten

- Hauptplugin: WordPress >= 6.0, PHP >= 8.2
- Elementor-Addon: ChurchTools Integration Suite, Elementor, PHP >= 8.2
- Posts-Sync-Addon: ChurchTools Integration Suite, PHP >= 8.0
- Presentations-Addon: ChurchTools Integration Suite, PHP >= 8.0

## Funktionen

- Kalender-, Termin-, Dienst-, Gruppen- und Gruppenpost-Synchronisation
- Shortcodes und Gutenberg-Views für Liste, Grid, Kalender, Countdown und Carousel
- Kalenderfilter über `calendar`, `calendars` und `calendar_ids`
- Custom CSS pro Shortcode, Preset und Block-Instanz, begrenzt über eine stabile Instanz-ID
- Automatische Synchronisation über WordPress-Cron
- Löschen abgelaufener Termine (einstellbar) inklusive der importierten Bilder
- Template-System mit Theme-Override unter `{theme}/churchtools-suite/`

Eine Übersicht aller Shortcode-Parameter steht in [docs/SHORTCODES.md](docs/SHORTCODES.md).

## Beispiele

Moderne Liste von Events mit Bildern:

```
[cts_list view="list-modern-with-images" limit="20" link_search="false" show_location="true" show_services="true" show_appointment_description="true" show_event_description="true" use_calendar_colors="true"]
```

Kalenderansicht:

```
[cts_calendar view="monthly-simple" use_calendar_colors="true"]
```

## SW CloudCalendar

Die Suite kann ihre ausgewählten Kalender direkt an CloudCalendar liefern, ohne einen zweiten Event-Speicher.
Einrichtung und Tests: [CloudCalendar-Anbindung](docs/CLOUDCALENDAR.md).

## Entwicklung

### Code-Qualität

PHPCS mit WordPress-Standard, PHPStan Level 8:

```bash
composer install
composer cs      # PHP_CodeSniffer
composer cs:fix  # automatisch behebbare Verstöße korrigieren
composer stan    # PHPStan
composer check   # cs + stan
```

### Syntaxcheck

```bash
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | xargs -n50 php -l | grep -v "No syntax errors"
```

### Lokale Testumgebung

`@wordpress/env` ist konfiguriert (`.wp-env.json`):

```bash
npm install
npm run env:start
```

### Upstream-Änderungen übernehmen

```bash
git fetch upstream
git checkout -b merge-upstream
git merge upstream/main
```

Bei Konflikten in `addons/` die eigene Version behalten und Änderungen aus den Upstream-Addon-Repositories gezielt übernehmen.

## Weitere Dokumentation

- [CLAUDE.md](CLAUDE.md): Architektur und Konventionen
- [ROADMAP.md](ROADMAP.md): Roadmap
- [docs/CHANGELOG.md](docs/CHANGELOG.md): Änderungen
- [docs/CHURCHTOOLS-API-INTEGRATION.md](docs/CHURCHTOOLS-API-INTEGRATION.md): ChurchTools-API
