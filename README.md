# ChurchTools Integration Suite (Fork)

WordPress-Plugin, das Kalender, Termine und Dienste aus [ChurchTools](https://church.tools) nach WordPress synchronisiert und im Frontend darstellt.

Dieses Repository ist ein **Fork** von [feg-aschaffenburg/churchtools-suite](https://github.com/feg-aschaffenburg/churchtools-suite). Anders als im Original liegen die Addons hier weiterhin im selben Repository (Monorepo).

## Danke an das Upstream-Projekt

Dieses Plugin wäre ohne die Arbeit des Teams der **FEG Aschaffenburg** nicht möglich. Sie haben die ChurchTools Integration Suite entworfen, gebaut und über viele Releases gepflegt. Die Architektur mit Repository-Pattern, zweistufigem Event-Sync, Template-System und Shortcodes, die Dokumentation und die laufende Weiterentwicklung sind großartig umgesetzt. Dieser Fork baut direkt darauf auf und bleibt über den `upstream`-Remote mit dem Original verbunden. Vielen Dank dafür!

Wenn du das Plugin ohne die Anpassungen dieses Forks nutzen willst, schau im Original-Repository vorbei.

## Inhalt des Repositories

| Paket | Typ | Version | Pfad |
| --- | --- | --- | --- |
| ChurchTools Integration Suite | Hauptplugin | 1.4.0.7 | `churchtools-suite.php` |
| Elementor Integration | Addon | 1.0.0.3 | `addons/churchtools-suite-elementor/` |
| Posts Sync | Addon | 0.1.12 | `addons/churchtools-suite-posts-sync/` |
| Presentations | Addon | 0.1.2 | `addons/churchtools-suite-presentations/` |

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
- Automatische Synchronisation über WordPress-Cron, auch bei deaktiviertem Besucher-Trigger durch regelmäßigen externen Aufruf von `wp-cron.php` (z. B. per curl). Einstellungen und Dashboard zeigen den nächsten geplanten und den letzten erfolgreichen Auto-Sync an.
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

### Tests

WordPress-Integrationstests und Browserprüfungen nutzen die wegwerfbare Datenbank von `bin/test-db.sh`, niemals die Entwicklungsinstallation:

```bash
composer install --working-dir=tests/wp
npm ci
npx playwright-core install chromium
bin/test-db.sh start
composer test
npm run test:ui
bin/test-db.sh stop
```

Die Browserprüfungen rendern echte WordPress-Views in der isolierten Testinstallation. ChurchTools-/AJAX-Antworten sind Fixtures; die Tests starten keine echten Synchronisationen und löschen keine produktiven Daten. PHP- und Browserläufe nacheinander ausführen, da der Browserexport dieselbe Testdatenbank neu installiert. Für vorhandenes Chromium kann `CHROME=/pfad/zu/chrome` gesetzt werden.

Abgedeckt sind Seitenauflösung und alte URLs, Menüs, Rechte/Nonces, Speichern der Einstellungen, mehrfaches Rendern, aktive/inaktive Posts-Integration, wiederkehrende Termine mit getrennten Vorkommen, Erhalt von Kalenderauswahl und Fallback-Bildern beim Sync, Tenant-Validierung und Geheimniswiederverwendung, einzelne AJAX-Aufrufe, Verbindungstests mit ungespeicherten Formulardaten und gespeicherten Geheimnissen, Fehlerfälle, Löschbestätigungen, Light/Dark und alle Screens bei 390/1440 Pixeln. `.github/workflows/tests.yml` führt beide Suiten ausschließlich bei Pull Requests aus (auch bei neuen Commits auf einem offenen PR); neuere PR-Läufe ersetzen laufende ältere Prüfungen. Branch-Pushes ohne PR und Release-Tags starten die Tests nicht; riskante PHPUnit-Tests führen zum Fehlschlag.

```bash
bin/test-db.sh start
XDEBUG_MODE=coverage composer test:coverage
npm run test:ui:coverage
composer coverage:check
```

Reports: `coverage/php.xml`, `coverage/ui.json`. `coverage:check` verlangt für PHP und JavaScript jeweils **>80 %** aller produktiven Dateien. Stand dieser Überarbeitung: PHP gesamt **19,78 %**, JavaScript gesamt **18,21 %**; Screen-Registry **>96 %**, neues Shell-JavaScript **89,45 %**. Die Gesamtvorgabe ist weiterhin **nicht erfüllt**. Insbesondere echte Sync-/API-Abläufe und Frontend-Varianten benötigen weitere Tests. CI meldet diese bestehende Lücke ausdrücklich; der Coverage-Schritt ist bis dahin berichtend, die Funktionstests sind blockierend. `composer cs`/`composer stan` melden weiterhin Befunde im Altbestand.

### GitHub-Updates

Unter **Plugins → Installierte Plugins** steht bei ChurchTools Suite und seinen Addons „Jetzt auf Updates prüfen“ samt letztem Prüfstatus. Ein Klick prüft das gemeinsame Release einmal; anschließend lässt sich die Plugin-Liste neu laden, um verfügbare Aktualisierungen anzuzeigen. Die Installation erfolgt über WordPress. Bei einer abgelaufenen Sicherheitsprüfung fordert die Meldung zum Neuladen auf; eine GitHub-HTTP-Fehlermeldung bleibt als solche sichtbar.

Das [Sicherheits- und Umsetzungskonzept](docs/GITHUB-UPDATES.md) beschreibt einen übertragbaren GitHub-Update-Provider mit signierten Manifesten, Backend-Prüfung höchstens einmal je 24 Stunden und ausschließlich manueller Installation über WordPress. Die Umsetzung und [Einrichtung der Signierschlüssel](docs/GITHUB-UPDATES-BETRIEB.md) sind dokumentiert. Die alten Updater sind stillgelegt. Ohne öffentlichen Vertrauensschlüssel bleibt die neue GitHub-Funktion gesperrt; Produktionsschlüssel werden nicht automatisch erzeugt.

### Release

```bash
make release-check
make release VERSION=1.4.3
```

Neue Versionen verwenden ausschließlich `Major.Minor.Patch`. Die verbindlichen Regeln für alle Coding-Agenten stehen in [AGENTS.md](AGENTS.md). Änderungen an Addons werden während der Entwicklung in `release-pending.json` vorgemerkt, beispielsweise `"churchtools-suite-posts-sync": "minor"`. Pro Addon bleibt nur die höchste Stufe (`major > minor > patch`) stehen. `make release-check` prüft gegen den letzten erreichbaren Release-Tag, ob alle geänderten Addons einen Eintrag besitzen.

`make release` aktualisiert Hauptplugin-Header und Versionskonstante sowie die vorgemerkten Addon-Versionen (Header, Konstante und vorhandener `Stable tag`). Jedes Addon wird genau einmal erhöht; anschließend wird die Vormerkdatei auf `{}` zurückgesetzt. Die dauerhafte `AGENTS.md` bleibt erhalten. Die initialen Einträge migrieren Elementor von `1.0.0.3` nach `1.0.1` und vereinheitlichen Posts Sync auf `0.1.14`.

Der Befehl erstellt den Versionscommit und einen annotierten Tag und pusht aktuellen Branch plus Tag atomar nach `origin`. Der Tag startet den signierten GitHub-Release-Build, der höhere Versionen geänderter Addons und eine leere Vormerkdatei nochmals prüft. Ein sauberer Arbeitsbaum inklusive unversionierter Dateien ist erforderlich. Bestehende Tags, nicht erhöhte Hauptplugin-Versionen und widersprüchliche Hauptplugin-Versionsangaben werden abgelehnt. Bei fehlgeschlagenem Push bleiben Release-Commit und Tag lokal erhalten; nach Beheben der Ursache denselben Branch und Tag atomar pushen, statt die Versionen erneut zu erhöhen.

Nur ZIPs lokal bauen:

```bash
bash bin/build-zip.sh            # -> dist/*.zip (Hauptplugin und jedes Addon separat)
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
