# Betrieb der verifizierten GitHub-Updates

Der neue Provider ist implementiert. Die bisherigen Haupt- und Addon-Updater sowie die eigenen Entpack-/Kopierinstaller sind stillgelegt. Ohne eingerichteten öffentlichen Schlüssel werden weder GitHub-Releases geprüft noch GitHub-Updates installiert. Es gibt keinen Rückfall auf unsignierte Downloads.

## Einmalige Einrichtung vor der ersten signierten Veröffentlichung

1. Ein dediziertes Ed25519-Schlüsselpaar außerhalb des Repositorys und außerhalb von WordPress erzeugen. Der Signierer benötigt den **64 Byte langen Sodium Secret Key**, Base64-kodiert; kein GitHub-Token und kein SSH-Schlüssel. Schlüsselmaterial nicht im Terminalprotokoll ausgeben. Die Schlüsselgenerierung und Ablage erfolgen bewusst durch den Betreiber, nicht beim Installieren des Plugins.
2. In GitHub die Umgebung `plugin-releases` mit Freigaben und geschützten Release-Tags einrichten. In dieser Umgebung das Secret `SW_PLUGIN_RELEASE_SIGNING_KEY` und die Variable `SW_PLUGIN_RELEASE_KEY_ID` setzen. Der öffentliche Schlüssel ist kein Geheimnis; der private Schlüssel darf weder ins Repository noch auf den WordPress-Server gelangen.
3. Den öffentlichen Schlüssel im vertrauenswürdig ausgelieferten `includes/update/trusted-keys.php` hinterlegen: Rückgabearray `key_id => Base64(public_key)`. Alternativ kann eine lokal administrierte WordPress-Konfiguration die Array-Konstante `SW_PLUGIN_RELEASE_PUBLIC_KEYS` setzen. Keine über das Backend eingetragene Downloadadresse oder remote geladene Schlüsselliste wird vertraut.
4. **Vertrauensstart:** Bereits installierte Versionen ohne Schlüssel müssen den öffentlichen Schlüssel einmal über einen separat vertrauenswürdigen Weg erhalten, etwa durch manuelle Installation des ersten kontrollierten ZIPs oder die lokale Konstante. Der erste heruntergeladene Release darf seinen eigenen Schlüssel nicht legitimieren.
5. Erst einen Draft mit Testschlüssel in einer privaten WordPress-Installation prüfen. Anschließend einen echten Versions-Tag veröffentlichen; der Release-Workflow bettet den öffentlichen Schlüssel ein, baut ZIPs, lädt diese in einen Draft, signiert das Manifest mit den tatsächlichen Asset-IDs und veröffentlicht erst den vollständigen Satz.

Ohne Secret oder Key-ID bricht der Release-Build vor dem Upload/Publish ab. Diese Konfiguration wurde durch die Codeänderung nicht automatisch im GitHub-Konto angelegt. Ein Repository-/Workflow-Administrator mit Zugriff auf eine ungeschützte Signierumgebung könnte weiterhin bösartigen Code signieren; die geschützte Umgebung ist deshalb Teil der Betriebsanforderung.

## Prüfungen und Installation

- Berechtigte Backend-Besuche können nach dem Seitenaufbau einen Prüflauf starten; höchstens einmal je 24 Stunden pro konfigurierter Quelle und WordPress-Netzwerk. Nach Netzwerk- und Verifikationsfehlern gilt dieselbe Sperrfrist.
- Ein manueller Klick auf „Jetzt auf Updates prüfen“ darf das Tageslimit umgehen, hat aber einen Mindestabstand von 60 Sekunden und benötigt Nonce plus `update_plugins`.
- Das Frontend, Cron, WP-CLI und REST lösen keine GitHub-Prüfung aus. Ohne Backend-Besuch gibt es keine tägliche Anfrage.
- Die Pluginübersicht und der Plugin-Detaildialog zeigen ausschließlich lokale Angebote. Die Installation verwendet WordPress’ normale Einzel-/AJAX-/Sammelaktualisierung. Die Auto-Update-Spalte erklärt, dass nur manuell installiert wird.
- Vor dem WordPress-Download wird das gespeicherte Manifest erneut verifiziert. Danach wird genau das gebundene Asset in eine exklusive temporäre Datei geladen und auf Größe, SHA-256, ZIP-Pfade, Dateitypen, Entpacklimits und Plugin-Header geprüft. Fehler blockieren die Übergabe an WordPress.
- Erfolgreich geprüfte Angebote sind höchstens sieben Tage ohne erneute Prüfung installierbar. Die zuletzt akzeptierte höchste Version verhindert das Zurücksetzen auf einen älteren geprüften Stand.
- Erstinstallationen erfolgen über einen bewussten ZIP-Upload in WordPress. Die alte Funktion „Addon direkt von einem beliebigen GitHub-ZIP installieren“ ist entfernt; ein Upload wird nicht als vom GitHub-Provider verifiziert ausgewiesen.

Lokale, nicht autoloadende Zustände liegen bei Single-Site in WordPress-Optionen; Multisite verwendet Netzwerkoptionen. Die Tagesgrenze überlebt eine Transient-Löschung. Ein abgelaufener Lock ist übernehmbar; ein alter Besitzer darf den neuen Lock nicht freigeben oder ein verspätetes Ergebnis veröffentlichen. Alte Cronjobs und Optionen werden einmalig beim berechtigten Backend-Besuch der jeweiligen Site bereinigt. Die eigene Auswahl in WordPress’ Auto-Update-Liste wird entfernt. Beim Deaktivieren der Suite werden eigene zwischengespeicherte Updateangebote entfernt, bevor ihre Schutz-Hooks entfallen. Nicht besuchte Sites behalten gegebenenfalls einen wirkungslosen alten Cron-Eintrag, der keinen alten Installer mehr aufrufen kann.

## Dateien und Übernahme in andere Plugins

| Datei | Verantwortung |
| --- | --- |
| `includes/update/source.php` | Lokal festgelegtes Repository, numerische Repository-ID, erlaubte Slugs, Hauptplugin und eindeutige Speicher-/Locknamen |
| `includes/update/descriptors.php` | Tatsächliche WordPress-Basenames, Slugs und installierte Versionen der verwalteten Plugins |
| `includes/update/trusted-keys.php` | Ausschließlich öffentliche Ed25519-Schlüssel |
| `class-churchtools-suite-update-manifest.php` | Signatur, Schema und Bindung an Release/Asset |
| `class-churchtools-suite-update-transport.php` | HTTPS, erlaubte Hosts/Pfade, Redirect- und Größenlimits |
| `class-churchtools-suite-update-package.php` | Hash, ZIP-Struktur, Dateitypen, Entpacklimits und Header |
| `class-churchtools-suite-update-store.php` | Persistenter Zustand, atomarer Lock mit Eigentümerprüfung |
| `class-churchtools-suite-update-service.php` | Prüfpolitik, Quellenabruf und verifizierter Download |
| `class-churchtools-suite-update-provider.php` | Native WordPress-Hooks, Backend-Berechtigungen, Migration und UI-Anbindung |
| `bin/release-manifest.php` | Public-Key-Export für den Build und Signierung der fertig hochgeladenen ZIPs |

Bei Übernahme werden `source.php`, `descriptors.php`, Schlüssel und der Plugin-spezifische Adapter konfiguriert. Klassen-, Hook- und Optionspräfixe müssen pro ausgelieferter Kopie eindeutig sein. Kein „erste geladene Bibliotheksversion gewinnt“ und keine Laufzeit-Composer-Abhängigkeit. Das Hauptplugin koordiniert seine Addons; deren frühere separaten Updater sind deaktiviert.

Die signierten Pakete tragen ihre **eigene** Pluginversion. Ein Monorepo-Tag bestimmt die Hauptplugin-Version; Addons müssen nicht dieselbe Versionsnummer haben. Der Signierer und der Laufzeitprüfer werden mit Wegwerfschlüsseln gegeneinander getestet.

## Schlüsselwechsel, Ausfälle und WordPress.org

Für Rotation zunächst eine mit dem bisherigen Schlüssel signierte Übergangsrelease mit beiden öffentlichen Schlüsseln ausliefern; erst danach mit der neuen Key-ID signieren. Ein kompromittierter Schlüssel benötigt einen separat vertrauenswürdigen Wiederherstellungsweg. Ohne zusätzliches signiertes zeitlich gültiges Channel-Verzeichnis kann ein erstmaliger Client nicht zuverlässig erkennen, dass eine neuere Version absichtlich vorenthalten wird.

GitHub-Redirects sind auf konkrete HTTPS-Hosts begrenzt. Ein Wechsel des CDN kann deshalb ein Client-Update nötig machen. Metadaten haben Größen- und Zeitlimits; der Paketdownload erfolgt ausschließlich beim manuellen Installationsauftrag. Die Unterstützung fremder privater Repositorys und GitHub-Tokens ist nicht Teil dieser Implementierung.

Vor Einreichung bei WordPress.org sind GitHub-Update-URI und Provider aus dem Store-Build zu entfernen. Eine letzte signierte Übergangsrelease muss diesen Wechsel für bestehende Installationen durchführen; dabei gezielt eigene Updateangebote und Zustände bereinigen. Das wird nicht bereits beim bloßen Vorhandensein einer vermeintlich gleichnamigen Store-Version automatisch umgeschaltet.

## Verifikation

`VerifiedUpdatesTest` verwendet einen privaten WordPress-/MariaDB-Aufbau, Wegwerfschlüssel und vollständig simulierte HTTP-Antworten. Darunter sind echte WordPress-Einzel- und Sammelinstallationen ausschließlich im Vendor-Verzeichnis der Testinstallation. Produktive Plugin-Dateien werden nicht installiert oder überschrieben. Die Browserprüfung sichert den manuellen Prüfdialog und die unveränderte WordPress-Navigation ab.

Die Suiten bleiben auf Pull Requests beschränkt. Der Tag-Build prüft und signiert seine Artefakte; er führt keine Testsuite aus. Ohne eingerichtete produktive Schlüssel kann ein erfolgreicher Testlauf keine echte signierte Veröffentlichung ersetzen.
