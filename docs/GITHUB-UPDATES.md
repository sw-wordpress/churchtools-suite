# Konzept für verifizierte manuelle GitHub-Updates

Stand: 6. November 2026. **Architekturkonzept; die Implementierung und nötige Schlüssel-Einrichtung sind im [Betriebshandbuch](GITHUB-UPDATES-BETRIEB.md) beschrieben. Ohne eingerichtete Vertrauensschlüssel bleibt der Provider gesperrt.** Dieses Konzept beschreibt den temporären GitHub-Vertrieb von ChurchTools Suite und einen wiederverwendbaren Baustein für die anderen SW-Plugins.

Updates erscheinen in der normalen WordPress-Pluginübersicht. Verfügbarkeitsprüfungen laufen ausschließlich nach einem berechtigten Backend-Aufruf, höchstens einmal je 24 Stunden oder ausdrücklich manuell. Installiert wird ausschließlich nach einem manuellen Update-Auftrag. Empfohlen ist ein signiertes Manifest mit SHA-256 für jedes ZIP; ein fehlender oder ungültiger Nachweis blockiert die Installation.

## 1. Befund im bestehenden Plugin

| Bereich | Befund | Konsequenz |
| --- | --- | --- |
| `includes/class-churchtools-suite-auto-updater.php` | Haupt-Updater über `GITHUB_UPDATES_ENABLED = false` deaktiviert; enthält weiterhin Cron, automatische Installation, Upstream-/Fallback-Adressen und eigenen Entpack-/Kopierpfad | Nicht wieder einschalten; durch getrennte Prüfung und WordPress-Installation ersetzen |
| `includes/class-churchtools-suite-update-checker.php` | Ebenfalls deaktiviert; würde Caches beim Öffnen der Plugins-/Update-Seite löschen und externe Anfragen aus Transient-Filtern auslösen | Filter nur mit verifiziertem lokalem Zustand bedienen; kein HTTP aus Anzeige-Filtern |
| Elementor-/Posts-Sync-Addon-Updater | Eigene Prüfwege, Upstream-Adressen und Cache-Refresh-Hooks vorhanden; Elementor initialisiert seinen Updater selbst | In dieselbe Architektur überführen, sonst gilt die Tagesgrenze nicht für die gesamte Suite |
| Einstellungen und Dashboard | Auto-Update-Stufen, stündliche Intervalle und alte manuelle Update-Pfade vorhanden | Auto-Optionen entfernen; ausschließlich „Updates prüfen“ und WordPress-Updateaktionen anbieten |
| `.github/workflows/main.yml` | Erstellt getrennte ZIPs, aber noch kein eigenes signiertes Manifest | Build, Hashbildung und Signierung ergänzen; vollständigen Release erst anschließend veröffentlichen |

Verbindliches Repository ist `sw-wordpress/churchtools-suite`, Repository-ID **1163606817**. Keine Updates aus anderen Quellen, Source-Archiven, Tags ohne geprüfte Assets oder einer alternativen Download-Domain. Die Repository-ID wurde über die GitHub-API geprüft; sie wird zusammen mit dem Namen lokal festgelegt.

## 2. Nutzerablauf und WordPress-Integration

1. Eine berechtigte Person öffnet das Backend. Nach dem Seitenaufbau kann ein kleiner, authentifizierter Hintergrundaufruf die fällige Metadatenprüfung ausführen. Die angeforderte Backend-Seite wartet nicht auf GitHub.
2. Das Backend zeigt Zeitpunkt und Ergebnis der letzten Prüfung. „Jetzt prüfen“ startet dieselbe Prüfung mit Nonce und `update_plugins`; ein kurzer Mindestabstand verhindert versehentliche Doppelklicks. Diese explizite Prüfung darf die Tagesgrenze überschreiben.
3. Ein verifiziertes Angebot erscheint in **Plugins** und **Dashboard → Aktualisierungen**. Plugin-Detaildialog und Versionsinformationen stammen aus lokalem Cache; Release-Texte werden escaped beziehungsweise auf erlaubtes HTML begrenzt.
4. „Jetzt aktualisieren“ verwendet den normalen WordPress-Upgrader einschließlich dessen Berechtigungen, Nonces, Dateisystemabfrage und Fehlerrückmeldungen. Auch die native manuelle Sammelaktualisierung ist zulässig.
5. Vor Übergabe des ZIP an WordPress werden Metadaten, Signatur, Hash und Paketstruktur geprüft. Erst danach darf WordPress entpacken und installieren.
6. Bei Fehlern bleibt das installierte Plugin unangetastet, soweit der Fehler vor dem WordPress-Installationsschritt erkannt wird. Ein Installationsfehler wird als Fehler gemeldet; keine eigene rekursive Kopierlösung und keine falsche Erfolgsmeldung.

Die WordPress-Update-API unterstützt externe Angebote über einen `Update URI`-Header und den Filter `update_plugins_{$hostname}`. Dieser Adapter liefert **nur lokale** Angebote; für die erstmalige Registrierung eines frisch geprüften Angebots kann ein ebenfalls lokaler Adapter des `update_plugins`-Transients erforderlich sein. `plugins_api` liefert den Detaildialog. `upgrader_pre_download` erlaubt, ausschließlich ein bereits geprüftes temporäres ZIP oder einen Fehler an den Upgrader zurückzugeben. Siehe [WordPress Update-Prüfung](https://developer.wordpress.org/reference/functions/wp_update_plugins/) und [Download-Hook](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/).

Der Update-URI-Hostname allein ist kein Vertrauensnachweis: Bei gemeinsamem Host wie `github.com` muss jeder Callback zusätzlich die vollständige Update-URI und den exakten Plugin-Basename prüfen. Er liefert für fremde Plugins unverändert den eingehenden Filterwert zurück.

### Automatische Installation ausdrücklich ausschließen

- `auto_update_plugin` liefert für die verwalteten GitHub-Plugins `false`, unabhängig von früher gespeicherten Auto-Update-Optionen. Für andere Plugins bleibt der eingehende Wert erhalten.
- Die Auto-Update-Spalte dieser Plugins erklärt „Nur manuelle Aktualisierung“, statt einen wirkungslosen Einschalt-Link anzubieten (`plugin_auto_update_setting_html`).
- Der Download-Guard verweigert GitHub-Installationen in Cron, WP-CLI, REST und sonstigen nicht unterstützten Installationskontexten. Unterstützt sind die nativen, berechtigten interaktiven WordPress-Backend-Aktionen einschließlich `update-plugin`-AJAX und Sammelupdates. `is_admin()` allein genügt nicht; Core-Nonce-Prüfung, `update_plugins`, Dateimodifikationsrichtlinien und Kontext müssen zusammen stimmen.
- Kein eigener Hintergrundinstaller, kein öffentlicher/nicht authentifizierter AJAX-Endpunkt und kein „Prüfen und sofort installieren“.

Die nötigen lokalen Policy-Guards müssen auch im Cron-Kontext wirken, damit ein bereits im WordPress-Cache liegendes Angebot nicht automatisch installiert wird. PHP-Code mit vollständigen lokalen Rechten könnte solche Hooks verändern; die Grenze gilt für die unterstützten WordPress-Abläufe, nicht als Schutz gegen bereits kompromittierten Servercode. Siehe [Auto-Update-Filter](https://developer.wordpress.org/reference/hooks/auto_update_type/) und [Darstellung der Auto-Update-Spalte](https://developer.wordpress.org/reference/hooks/plugin_auto_update_setting_html/).

## 3. Ausführungs- und Performancevertrag

| Kontext | GitHub-Prüfung | Eigener Zustand / zusätzliche Arbeit |
| --- | --- | --- |
| Öffentliches Frontend, Feeds, öffentliche REST-Aufrufe | Nie | Kein Laden von API-Client, Cache-Service oder Verifier; keine eigenen Update-DB-Zugriffe |
| Cron und WP-CLI | Nie | Nur lokale Ablehnung einer automatischen GitHub-Installation |
| Backend ohne `update_plugins` | Nie | Kein Prüfauftrag |
| Berechtigtes Backend, Prüfung jünger als 24 Stunden | Nie | Lokalen Status anzeigen; kein Remote-Aufruf aus Transient-/Detailfiltern |
| Berechtigtes Backend, Prüfung fällig | Ein Metadaten-Prüflauf | Asynchron nach Seitenaufbau; ein Lock pro Repository und WordPress-Netzwerk |
| Manueller Prüfauftrag | Expliziter Prüflauf | Nonce, Capability, Lock, Mindestabstand von zunächst 60 Sekunden |
| Manueller Installationsauftrag | Kein zusätzlicher automatischer Versionscheck | Gespeichertes, noch gültiges Angebot prüfen und genau dessen ZIP herunterladen |

„Ein Prüflauf“ kann mehrere begrenzte HTTP-Requests umfassen: Release-Metadaten, Manifest und Signatur. Es bedeutet nicht einen Request pro Plugin. Im Monorepo teilen sich Hauptplugin und Addons einen Metadatensatz. Andere Repositorys haben getrennte Tagesgrenzen. Ohne Backend-Besuch findet keine tägliche Prüfung statt.

**Zustand dauerhaft, Cache nur als Beschleunigung:** `last_attempt_at`, `last_success_at`, nächster erlaubter Versuch, validiertes Angebot und letzter Fehler werden als nicht autoloadende Netzwerkoptionen gespeichert. Ein vorzeitig gelöschter Transient darf keinen zweiten automatischen Tagescheck verursachen. Der Zeitpunkt wird vor dem HTTP-Versuch gesetzt, auch Timeout, 404 und Rate-Limit verbrauchen den automatischen Tagesversuch. Keine sofortige Wiederholung bei Fehlern. Die Installation prüft die gültigen Metadaten erneut lokal; nach zunächst sieben Tagen ohne erfolgreiche Prüfung wird das Angebot als veraltet markiert und vor Installation eine manuelle Prüfung verlangt.

Ein atomarer Lock mit Eigentümerkennung und kurzer Lease verhindert parallele Anfragen und ist nach Prozessabbruch wieder übernehmbar. Freigabe und Übernahme dürfen einen inzwischen neu vergebenen Lock nicht löschen. Netzwerkausfälle verlängern keine Gültigkeit. Ein Timeout von zunächst fünf Sekunden je Metadatenrequest, begrenzte Antwortgrößen und maximal vier Weiterleitungen begrenzen die Arbeit. Im selben Request werden identische Zugriffe zusätzlich im Speicher wiederverwendet.

Null Rechenaufwand ist technisch nicht erreichbar. Die verbindliche Zusage ist: keine Update-Netzwerkanfragen und keine zusätzlichen Update-Datenbankzugriffe im Frontend; dort bleiben höchstens konstante lokale Guards für WordPress-Installationsaktionen. Hash- und ZIP-Prüfung erfolgen ausschließlich bei der ausdrücklich gestarteten Installation.

## 4. Vertrauensmodell und Release-Artefakte

| Verfahren | Erkennt | Grenze | Entscheidung |
| --- | --- | --- | --- |
| SHA-256 aus GitHub-Asset-Metadaten | Abweichung vom über HTTPS abgefragten GitHub-Artefakt | Wer Repository/Release kontrolliert, kann auch ein neues Paket samt Hash veröffentlichen | Nur ausdrücklich konfigurierbares Übergangsprofil, kein stiller Fallback |
| Ed25519-signiertes Manifest plus SHA-256 je ZIP | Unautorisierte Änderungen an Metadaten und Paket, solange der private Signierschlüssel geschützt bleibt | Ein kompromittierter Signierprozess kann weiterhin schädlichen Code autorisieren | Empfohlener Standard |
| GitHub Immutable Releases und Attestations | Zusätzliche Bindung von Release, Tag, Commit und Artefakten | GitHub-Vertrauen und eigener Verifikationsaufwand | Ergänzend zur Signatur, keine Pflicht für jeden WordPress-Server |

GitHub stellt SHA-256-Digests für hochgeladene Release-Assets bereit ([Ankündigung](https://github.blog/changelog/2025-06-03-releases-now-expose-digests-for-release-assets/)). **ZIP und eine danebenliegende unsignierte Prüfsummendatei sind keine unabhängige Herausgeberbestätigung.** Der signierte Standard darf bei fehlender Sodium-Unterstützung, Signatur oder bekanntem Schlüssel niemals auf das Hash-Profil zurückfallen. Das Plugin bleibt ohne diese Unterstützung nutzbar; lediglich die GitHub-Installation ist gesperrt.

Ed25519 lässt sich in PHP mit `sodium_crypto_sign_verify_detached()` prüfen ([PHP-Handbuch](https://www.php.net/manual/en/function.sodium-crypto-sign-verify-detached.php)). WordPress-Server erhalten nur öffentliche Schlüssel. Es werden weder GitHub-CLI, Shell-Kommandos noch externe Kryptografieprozesse auf dem Webserver benötigt.

### Signiertes Manifest

Ein Release veröffentlicht die gebauten Plugin-ZIPs, `sw-plugin-release.json` und eine detached Signatur `sw-plugin-release.json.sig`. Die Signatur gilt für die **exakten UTF-8-Bytes** der Manifestdatei, nicht für nachträglich neu serialisiertes JSON. Vor JSON-Auswertung: Größenlimit, Schlüsselauswahl aus lokalem Keyring und Signaturprüfung. Ein kleiner Signature-Envelope darf eine `key_id` nennen; diese ist ausschließlich ein Lookup in lokal festgelegte Schlüssel und darf keine URL enthalten.

Das folgende Schema ist eine Feldübersicht; Platzhalter sind keine gültigen Release-Werte:

```json
{
  "schema": 1,
  "repository": "sw-wordpress/churchtools-suite",
  "repository_id": 1163606817,
  "release_id": "<GitHub release ID>",
  "tag": "<exact release tag>",
  "commit": "<40-character commit SHA>",
  "issued_at": "<Unix timestamp as integer>",
  "packages": [
    {
      "slug": "churchtools-suite",
      "version": "<Major.Minor.Patch.Build>",
      "asset_id": "<GitHub asset ID>",
      "filename": "churchtools-suite-<version>.zip",
      "sha256": "<64 lowercase hex characters>",
      "bytes": "<positive integer>",
      "requires_php": "8.2",
      "requires_wp": "6.0",
      "main_file": "churchtools-suite.php"
    }
  ]
}
```

Für die Umsetzung wird das Schema maschinenlesbar festgelegt; Zahlenfelder müssen Zahlen sein, IDs haben eine eindeutig festgelegte Darstellung. Doppelte JSON-Schlüssel, unbekannte Schema-Versionen, doppelte Slugs und uneindeutige Assets werden abgelehnt. URL, Slug oder Repository aus einem UI-Formular werden nicht als Updatequelle akzeptiert.

Repository/ID, Release-ID, Tag, Asset-ID, ZIP-Name, Dateigröße und Version müssen zusammenpassen. Tag-Suffixe wie `-01` dürfen nur entsprechend der lokal festgelegten Build-Regel normalisiert werden; kein freies Abschneiden beliebiger Suffixe. Monorepo-Addons verwenden ihre **eigene** Version aus dem signierten Paketeintrag, nicht die Version des Hauptplugins. PHP-/WordPress-Mindestversionen werden vor der Installationsfreigabe geprüft und später mit dem Plugin-Header im Paket abgeglichen.

Der signierte Zeitpunkt `issued_at` ist der Erstellungszeitpunkt des Manifests, nicht das beim Draft noch unbekannte Veröffentlichungsdatum. Ein unplausibel in der Zukunft liegender Zeitpunkt wird mit einer kleinen festgelegten Uhrtoleranz abgelehnt.

Nur veröffentlichte stabile Releases sind zulässig. Kein Downgrade und keine Installation derselben Version über den Updatepfad; die zuletzt akzeptierte höchste Version pro Plugin wird persistent gehalten. Ein manueller Wiederherstellungs-/Downgrade-Prozess wäre ein eigener, hier nicht enthaltener Ablauf. Ohne zusätzliches signiertes, zeitlich gültiges Channel-Verzeichnis lässt sich das dauerhafte Vorenthalten einer neueren Version gegenüber einem erstmaligen Client nicht vollständig erkennen; die Tagesprüfung allein löst dieses Freeze-Problem nicht.

### Schlüssel und Veröffentlichung

- Ein dedizierter Release-Signierschlüssel pro Vertrauensbereich; öffentliche Schlüssel werden mit dem Plugin ausgeliefert. Ein angebotener neuer Schlüssel darf sich nicht selbst legitimieren. Rotation erfolgt durch eine mit dem bisherigen Schlüssel autorisierte Übergangsrelease mit überlappenden Key-IDs.
- Privatmaterial ausschließlich in einer geschützten Release-Umgebung oder einem externen Signierdienst. Keine Schlüssel in Repository, ZIP, Testfixtures, Logs oder WordPress-Optionen. Tests nutzen eigene Wegwerfschlüssel.
- Schutz von Release-Branches, Tags und Signierumgebung; Signieren benötigt eine getrennte Freigabe. Ein GitHub-Actions-Secret allein ist keine Sicherheit gegen kompromittierten Workflow-Code, der auf dieses Secret zugreifen darf.
- Veröffentlichung als Draft: ZIPs bauen und prüfen, hochladen, Asset-IDs erfassen, Manifest erstellen und signieren, Manifest und Signatur hochladen, alle Artefakte wieder verifizieren, **erst dann** veröffentlichen. Bei fehlendem Schlüssel, Hashfehler oder unvollständigem Satz kein veröffentlichter Release.
- Immutable Releases zusätzlich aktivieren, wenn verfügbar; Assets müssen vollständig am Draft hängen, bevor er veröffentlicht wird. GitHub dokumentiert diesen Ablauf und die zugehörigen Attestations unter [Immutable Releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases).

## 5. Download und Paketprüfung

`upgrader_pre_download` muss alle Pakete für die verwalteten Plugins prüfen, auch wenn ein anderes Updateangebot oder eine abweichende Paket-URL im WordPress-Transient steht. Ein unbekanntes oder manipuliertes Angebot darf nicht zum ungeprüften Core-Download durchgereicht werden. Bereits von anderen Filtern gelieferte lokale Dateien sind nicht automatisch vertrauenswürdig.

1. Manuellen WordPress-Installationskontext, `update_plugins`, Core-Autorisierung und `wp_is_file_mod_allowed()` prüfen. Bei Multisite ausschließlich Netzwerkadministration und Netzwerkzustand verwenden.
2. Rohmanifest und Signatur erneut aus dem lokalen geprüften Angebot verifizieren. Plugin-Identität, Version, Alter des letzten erfolgreichen Checks und installierte Mindestversion prüfen. Ist es abgelaufen, Prüfung anfordern statt heimlich einen neuen Versionscheck zu starten.
3. Exakt das signierte Asset über die konfigurierte GitHub-Quelle auflösen. Nur HTTPS/443; Repositorypfad und ID binden. Redirects einzeln prüfen, maximal vier; anfänglich nur `github.com`, `api.github.com`, `release-assets.githubusercontent.com` und `objects.githubusercontent.com`. Keine pauschale Freigabe aller `githubusercontent.com`-Subdomains, privaten IPs, Userinfo, abweichenden Ports, `file:`- oder sonstigen Schemes. Eine Änderung der GitHub-CDN kann dadurch eine bewusste Clientanpassung erfordern.
4. ZIP in eine zufällige, exklusive temporäre Datei streamen. Zunächst maximal 50 MiB komprimiert, 250 MiB entpackt und 10.000 Einträge; Limits sind lokal pro Plugin konfigurierbar und dürfen vom Manifest nicht angehoben werden. Kein komplettes ZIP als PHP-String halten. Download-Timeout und tatsächliche Dateigröße prüfen.
5. `hash_file('sha256', ...)` berechnen und mit `hash_equals()` gegen den signierten Hash vergleichen. SHA-256-Format vor dem Vergleich prüfen. Eine API-Prüfsumme ist nur eine zusätzliche Konsistenzprüfung, kein Ersatz für die Signatur.
6. Vor Extraktion ZIP-Inhaltsverzeichnis validieren: genau ein erwarteter Plugin-Stammordner, erwartete Hauptdatei, keine absoluten Pfade, `..`-Segmente, Windows-Laufwerke, NULs, Backslash-Ausweichpfade, Symlinks, doppelten/normalisiert kollidierenden Pfade oder verschlüsselten Einträge. Entpackgrößen und Kompressionsverhältnis begrenzen. Prüfer benötigt einen unterstützten ZIP-Reader; kein ungeprüfter Fallback bei fehlender Erweiterung.
7. Header-Version, Pluginidentität und Mindestversionen der Hauptdatei mit dem Manifest vergleichen. Erst jetzt den temporären Dateipfad an WordPress zurückgeben. Kein Laden von PHP aus dem ZIP zur Prüfung, kein `extractTo()` vor Validierung.
8. Datei auf jedem Fehlerpfad löschen, Erfolgspfad an Core-Cleanup anbinden; bei Abbruch zeitlich begrenzte Aufräumstrategie ausschließlich im Backend. Die geprüfte Datei darf vor der Core-Übernahme nicht durch einen fremden Benutzer austauschbar sein.

Offizielle WordPress-Uploads und fremde Plugins werden durch den Provider nicht pauschal blockiert. Für verwaltete GitHub-Plugins ist ein bewusst manuell hochgeladenes ZIP außerhalb dieses Updateangebots ein gesonderter WordPress-Installationsweg; eine solche Installation wird nicht fälschlich als durch den GitHub-Provider verifiziert bezeichnet.

## 6. Übertragbare Architektur

| Baustein | Verantwortung | Darf HTTP oder installieren? |
| --- | --- | --- |
| `PluginDescriptor` | Lokale Pluginidentität, Repository/ID, Basename, aktuelle Version, erlaubte Schlüssel und Limits | Nein |
| `CheckPolicy` / `StateStore` | Kontext, Tagesgrenze, negative Ergebnisse, Lock und Cachezustand | Nein |
| `GitHubReleaseSource` | Begrenzter Abruf von Release, Manifest und Signatur | Nur im autorisierten Prüflauf |
| `ManifestVerifier` | Bytegenaue Signatur und Manifestbindungen | Nein |
| `PackageVerifier` | Downloadregeln, Größe, SHA-256 und ZIP-Validierung | Nur im manuellen Installationsauftrag |
| `WordPressUpdateAdapter` | Lokales Angebot, Detaildialog, Download-Guard, Auto-Update-Sperre | Keine eigene Installation; Übergabe an Core |
| `AdminController` | Statusanzeige und manueller Prüfauftrag | Nur autorisierte Prüfung |

Der Baustein hat keine Abhängigkeit von ChurchTools-Zugangsdaten, Kalendern oder der aktuellen Admin-Shell. Andere Plugins registrieren ausschließlich ihren Descriptor und ein Status-/Prüf-UI. Dieselbe Vertrags-Testsuite läuft für jeden Descriptor.

Kurzfristig Umsetzung mit expliziten Includes unter `includes/update/` und zum Plugin passenden Klassenpräfixen. Ein später extrahiertes gemeinsames Paket wird pro Plugin versioniert und mit eigenem Namespace/Präfix ausgeliefert; keine global geteilte Klasse nach dem Prinzip „zuerst geladen gewinnt“. Keine Laufzeit-Composer-Installation und keine zentrale Zusatzplugin-Pflicht. Die Suite kann aktive Addons koordinieren; eigenständig betriebene Addons brauchen denselben Schutz unabhängig vom Hauptplugin. Ein zuständiger Coordinator pro Repository vermeidet doppelte Hooks und HTTP-Abrufe.

Für öffentliche Releases ist kein Benutzer-GitHub-Token erforderlich. Falls später private Repositorys unterstützt werden, wird dies separat spezifiziert: tokenbasierte Authentifizierung ausschließlich zur festgelegten API, keine Weitergabe des Authorization-Headers an CDN-Redirects und keine Tokenwerte in Browserdaten oder Logs.

## 7. Migration und Ausstieg Richtung WordPress.org

1. Alte Updater und Addon-Cache-Refresh-Hooks stilllegen, direkte Entpack-/Kopierinstallation entfernen. Alte Cron-Hooks und Auto-Update-Optionen einmalig durch eine versionierte Backend-Migration bereinigen. Der deaktivierte Haupt-Updater wird nicht als Zwischenlösung aktiviert.
2. Alte manuelle Endpunkte entweder entfernen oder kontrolliert auf reine Prüfung umstellen. Bestehende Nonces mit nur `manage_churchtools_suite` berechtigen nicht zu einer Updateinstallation; hierfür gilt `update_plugins`.
3. Alte Updateangebote dieser Plugins gezielt aus WordPress-Transients entfernen. Keine globalen Update-Caches oder Angebote fremder Plugins bei jedem Seitenaufruf löschen.
4. UI ersetzen: Prüfung täglich bei Backend-Nutzung, manuell prüfbar, ausschließlich manuell installierbar. Letzte erfolgreiche/fehlgeschlagene Prüfung und Verifikationsstatus anzeigen; keine Versionssprung-Automatik und keine stündliche Auswahl.
5. GitHub-Vertrieb ist eine explizite Build-/Vertriebsvariante. Der WordPress.org-Build enthält weder GitHub-Update-URI noch GitHub-Updater-Hooks. Die [Plugin-Richtlinie 8](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems) nennt Updates von Drittservern als unzulässigen Fall für das Verzeichnis.
6. Eine letzte signierte GitHub-Übergangsrelease setzt den endgültigen WordPress.org-Slug, entfernt den externen Update-URI und bereinigt Providerzustand/Hooks. Danach übernimmt WordPress.org. Diesen Wechsel mit bestehendem Pluginordner und Multisite gesondert testen. Ein externer Update-URI darf den Store-Übergang nicht dauerhaft verhindern.

## 8. Verbindliche Abnahmetests

Die Umsetzung ist erst fertig, wenn mindestens folgende Fälle automatisiert abgesichert sind. Die Suiten laufen weiterhin **nur auf Pull Requests**; der Release-Build validiert seine erzeugten Artefakte, startet aber keine Testsuite.

| Gruppe | Erforderliche Fälle |
| --- | --- |
| Kontext / Performance | Frontend, Cron, REST und WP-CLI erzeugen null GitHub-Anfragen; keine eigenen Update-DB-Zugriffe im Frontend; Backend ohne Capability ebenfalls null HTTP; frische lokale Anzeige bleibt ohne HTTP |
| Tagesgrenze | Erfolg, Timeout, 404 und Rate-Limit: kein weiterer automatischer Versuch vor 24 Stunden; Transient-Eviction ändert das nicht; manueller Nonce-Auftrag darf erneut prüfen; ungültige Nonce / fremder Benutzer nicht |
| Nebenläufigkeit | Zwei parallele Backend-Aufrufe ergeben einen Prüflauf; abgebrochener Lock läuft aus; alter Besitzer kann neuen Lock nicht freigeben; Hauptplugin plus Addons teilen einen Repository-Prüflauf |
| Signatur | Gültiges Testmanifest, geänderte Rohbytes, manipuliertes ZIP/Hash, unbekannter Schlüssel, falsches Repository/Asset, doppelte Schlüssel, ungültiges Schema und fehlendes Sodium; kein Fallback zum Hash-Profil |
| Versionspolitik | Vierstellige Versionen, erlaubte Tag-Suffixe, unterschiedliche Addon-Versionen, Draft/Prerelease, gleiche/ältere Version, falsche Mindestversion und abgelaufene Angebote |
| Download | Erlaubte Redirects sowie HTTP, private IP, falscher Host/Port, Token-Weitergabe, überschrittene Größe, Timeout und Hashabweichung; alle Abbruchpfade räumen temporäre Dateien auf |
| ZIP | Traversal, absoluter/Windows-Pfad, Backslash-Umgehung, Symlink, Pfadkollision, falscher Stammordner, fehlende Hauptdatei, falsche Header-Version, Zip-Bomb und beschädigtes Archiv |
| WordPress | Native Einzel- und Sammelinstallation mit Testpaketen in einer privaten WP-Installation; Detaildialog; Dateisystemfehler; kein falsches Erfolgsergebnis; fremde Plugins bleiben unverändert |
| Auto-Verbot | Gespeicherte Core-/Legacy-Auto-Optionen und gecachte Angebote können keine automatische Installation auslösen; manipulierte Paket-URL oder fremder Downloadfilter umgeht den Guard nicht |
| Migration / Multisite | Alte Cronjobs verschwinden einmalig; Netzwerklimit gilt über Sites hinweg; Site-Admin ohne Update-Rechte darf nicht installieren; Store-Übergang entfernt Providerzustand und Update-URI |
| Release | Signierendes Build-Skript mit Wegwerfschlüssel gegen PHP-Verifier testen; ZIP/Hash/Signatur vollständig; fehlender Produktionsschlüssel verhindert Veröffentlichung; kein Schlüsselmaterial im Paket |

Keine Änderungen an produktiven Plugins durch Integrationstests. Signier- und Repository-Sicherheit werden zusätzlich im Release-Prozess eingerichtet und überprüft; ein grüner Unit-Test ersetzt diese Betriebsmaßnahmen nicht.

## 9. Umsetzungsreihenfolge

1. Schema, Descriptor und Vertrauensprofil festlegen; Signierschlüsselbetrieb samt Rotation dokumentieren. Keine Produktionsschlüssel automatisch erzeugen oder veröffentlichen.
2. Verifier und Release-Manifest-Erstellung mit Wegwerfschlüsseln implementieren und gegeneinander testen.
3. Backend-Prüfpfad mit persistentem Tageslimit, atomarem Lock und ausschließlich lokalen Anzeige-Filtern implementieren.
4. Nativen WordPress-Updater anbinden, manuelle Installation erzwingen und ZIP-Guard testen.
5. Legacy-Migration, Addons und UI umstellen; private WordPress-Installation inklusive Multisite prüfen.
6. Geschützte Signierumgebung und ersten vollständigen signierten Draft-Release einrichten. Erst nach Ende-zu-Ende-Prüfung den Provider für echte Installationen freigeben.

Der empfohlene Standard ist **signiertes Manifest plus SHA-256, WordPress-Installer und Backend-Prüfung einmal je 24 Stunden**. Ein ausschließlich hashbasierter Start wäre ein bewusst schwächeres, separat ausgewiesenes Vertrauensprofil und wird nicht automatisch gewählt.
