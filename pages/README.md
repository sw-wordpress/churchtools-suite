# Dokumentationspflege (nicht Teil der Website)

## Seitenplan – vor der Umsetzung festgehalten

Zielgruppe sind WordPress-Administratoren und Redakteure, die ChurchTools-Kalender und Dienste veröffentlichen oder Gruppenposts übernehmen. Die vorhandene README und die meisten Betriebs-/Projekttexte sind deutsch; die Nutzerdokumentation bleibt deshalb deutsch. Die englische technische Shortcode-Referenz bleibt verlinkt.

| Seite | Begründung und geprüfte Quellen |
| --- | --- |
| `index.md` | Sachliche Funktionsübersicht mit Einstiegen; README, Plugin-Header, Screen-Registry und Shortcode-Implementierung. |
| `erste-schritte.md` | Voraussetzungen, getrennte Plugin-ZIPs, Verbindung, Kalenderauswahl und erste Ausgabe; Plugin-Header, Build-Skript, API-/Kalender-/Sync-Views, ConnectionSettings-/ConnectionPreview-Tests. |
| `einstellungen.md` | Anmeldemethoden, Kalender, Fallback-Bilder, Dienste und Darstellung; tatsächliche Settings-Views, AdminSettingsTest, SyncRepositoryTest. |
| `synchronisation.md` | Importzeitraum, manueller/automatischer Sync, Cron und Löschfolgen; Sync-Views, Cron-Klassen, Einstellungen-Tests. |
| `termine-anzeigen.md` | Shortcodes, Blöcke, Vorlagen und Kalenderfilter als zentraler Nutzerablauf; Shortcode-/Block-Code und `docs/SHORTCODES.md`. |
| `erweiterungen.md` | Separate Elementor-, Posts-Sync- und Presentations-Plugins sowie optionale CloudCalendar-Verbindung; Addon-Admin/Frontend/Renderer, vorhandene Readmes, `docs/CLOUDCALENDAR.md`, CloudCalendarTest. Keine separate Seite für jede kleine Integration. |
| `probleme-loesen.md` | Verbindungsfehler, leere Ausgaben, Cron, Bilder, Rechte, Diagnose und sichere Updates; Connection-/Admin-/VerifiedUpdates-Tests, Diagnose-Views und Update-Betriebshandbuch. |
| `Changelog.md` | Nachvollziehbare, nutzerbezogene Auswahl vorhandener Release Notes aus `docs/CHANGELOG.md` und Root-`CHANGELOG.md`, absteigend; keine aus Versionskonstanten abgeleiteten Änderungen. |

Keine Architektur-, API- oder Marketingseiten: vorhandene technische Dokumente werden im Repository verlinkt. Keine eigenen Screenshots oder Platzhalter; echte, bereinigte Aufnahmen ergänzt die übergeordnete Bearbeitung separat.

## Herkunft der eingebundenen Screenshots

Die übergeordnete Bearbeitung hat `public/screenshots/churchtools-suite-dashboard.png` und `public/screenshots/churchtools-suite-connection.png` als echte Aufnahmen aus der vorhandenen WordPress-Demo `wp-demo.stefanwarnat.de` bereitgestellt und visuell auf sensible Inhalte geprüft. Zu sehen ist ChurchTools Suite 1.4.4 im unkonfigurierten Zustand: keine importierten Datensätze, Dashboard „Nicht konfiguriert“, sichtbare API-Eingabefelder vollständig leer. Es wurden nur lesende Ansichten geöffnet, keine Zugangsdaten eingetragen und weder Speichern noch Synchronisation ausgelöst. WordPress-Toolbar und globale Navigation sind abgeschnitten. Die Aufnahmen zeigen keine nachgebaute Oberfläche; sie wurden hier zusätzlich visuell geprüft und unverändert in „Erste Schritte“ bzw. „Einstellungen“ mit deutschen Alternativtexten und Bildunterschriften eingebunden. Sie belegen keinen erfolgreichen ChurchTools-Import.

## Lokal isoliert bauen

Voraussetzung: Node.js 22 und npm. Im Repository-Root ausführen:

```sh
DOCS_BUILD="$(mktemp -d "${TMPDIR:-/tmp}/cts-docs.XXXXXX")"
cp -R pages "$DOCS_BUILD/pages"
npm install --prefix "$DOCS_BUILD" --no-save --package-lock=false --ignore-scripts --no-audit --no-fund vitepress@1.6.4
"$DOCS_BUILD/node_modules/.bin/vitepress" build "$DOCS_BUILD/pages"
# Optional ansehen:
"$DOCS_BUILD/node_modules/.bin/vitepress" preview "$DOCS_BUILD/pages" --host 127.0.0.1
# Nach Ende der Vorschau:
rm -rf "$DOCS_BUILD"
```

Es entstehen weder zusätzliche package.json-/Lockdateien noch Abhängigkeiten im Checkout. Die feste VitePress-Version sperrt nicht sämtliche transitiven Abhängigkeiten. Ausgabe und Cache liegen ausschließlich in der temporären Kopie. `README.md` ist über `srcExclude` vom Website-Build ausgeschlossen; VitePress prüft interne Links ohne Ausnahmeregel.

Der exakte gemeinsame Footer wird über den `layout-bottom`-Slot des kleinen Custom-Themes gerendert. Nicht auf `themeConfig.footer` zurückbauen: Der Standard-Footer ist auf Seiten mit Sidebar verborgen. Das Custom-Theme erzeugt auf Start- und Inhaltsseiten genau einen sichtbaren gemeinsamen Footer.

## GitHub Pages

Der ermittelte Default-Branch ist `main`; es ist keine eigene Pages-Domain bestätigt. Projekt-Basispfad: `/churchtools-suite/`. Die bestehende Pages-Konfiguration ließ sich nicht bestätigen (API: 404); das ist kein Beleg für ausreichende Rechte oder eine aktive Website. Nötiger einmaliger Schritt durch einen Repository-Administrator: **Settings → Pages → Source → GitHub Actions**. Einstellungen werden nicht automatisch geändert.

`.github/workflows/pages.yml` baut dokumentationsrelevante Pull Requests, Änderungen auf `main` sowie manuelle Starts. Nur Push/manueller Start auf `main` darf veröffentlichen; PRs bauen ohne Deployment. Der Build besitzt nur Leserechte. Nur der Deployment-Job erhält Pages-/OIDC-Schreibrechte und verwendet die Umgebung `github-pages`. Deployments sind serialisiert. Kein PAT und kein `gh-pages`-Branch.

Nach dem Merge Workflow und Deployment-URL prüfen. Ein erfolgreicher lokaler/PR-Build bedeutet nicht, dass Pages bereits veröffentlicht ist. Bei Branch-Wechsel Trigger, Deployment-Bedingung und Bearbeitungslinks gemeinsam anpassen. Bei bestätigter eigener Domain auch `base` und gegebenenfalls CNAME prüfen.

## Aktualisieren und prüfen

- Oberfläche/Code und relevante Tests als Quelle verwenden; UI-Bezeichnungen beibehalten.
- Bei jeder Änderung `Changelog.md` gemäß AGENTS.md mitpflegen. Versionsdaten ausschließlich aus belegten Release Notes übernehmen; bestehende historische vierteilige Versionen nicht umbenennen.
- Navigation/Sidebar bei neuen Seiten ergänzen, isolierten Produktionsbuild ausführen und Ausgabe unter `/churchtools-suite/` prüfen.
- Footer unverändert lassen: Open source by Stefan Warnat – free to use, free to change, better together.
- Echte Screenshots mit synthetischen Daten nach `public/screenshots/` legen, sinnvoll beschreiben und vor Veröffentlichung auf Geheimnisse/Personenbezug prüfen. Keine nachgebauten Produktbilder.
- `bin/build-zip.sh` schließt `/pages` und `.vitepress` aus; `node_modules` und `dist` bleiben ebenfalls ausgeschlossen. ZIP-Inhaltsprüfung und `make release-check` ausführen. Keine Produktversion für reine Dokumentationsänderungen erhöhen.
