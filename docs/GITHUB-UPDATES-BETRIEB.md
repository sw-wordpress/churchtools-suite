# Betrieb der verifizierten GitHub-Updates

Der neue Provider ist implementiert. Die bisherigen Haupt- und Addon-Updater sowie die eigenen Entpack-/Kopierinstaller sind stillgelegt. Ohne eingerichteten öffentlichen Schlüssel werden weder GitHub-Releases geprüft noch GitHub-Updates installiert. Es gibt keinen Rückfall auf unsignierte Downloads.

## Einmalige Einrichtung vor der ersten signierten Veröffentlichung

1. Ein dediziertes Ed25519-Schlüsselpaar außerhalb des Repositorys und außerhalb von WordPress erzeugen. Der Signierer benötigt den **64 Byte langen Sodium Secret Key**, Base64-kodiert; kein GitHub-Token und kein SSH-Schlüssel. Schlüsselmaterial nicht im Terminalprotokoll ausgeben. Die Schlüsselgenerierung und Ablage erfolgen bewusst durch den Betreiber, nicht beim Installieren des Plugins.
2. In GitHub die Umgebung `plugin-releases` mit Freigaben und geschützten Release-Tags einrichten. In dieser Umgebung das Secret `SW_PLUGIN_RELEASE_SIGNING_KEY` und die Variable `SW_PLUGIN_RELEASE_KEY_ID` setzen. Der öffentliche Schlüssel ist kein Geheimnis; der private Schlüssel darf weder ins Repository noch auf den WordPress-Server gelangen.
3. Den öffentlichen Schlüssel im vertrauenswürdig ausgelieferten `includes/update/trusted-keys.php` hinterlegen: Rückgabearray `key_id => Base64(public_key)`. Alternativ kann eine lokal administrierte WordPress-Konfiguration die Array-Konstante `SW_PLUGIN_RELEASE_PUBLIC_KEYS` setzen. Keine über das Backend eingetragene Downloadadresse oder remote geladene Schlüsselliste wird vertraut.
4. **Vertrauensstart:** Bereits installierte Versionen ohne Schlüssel müssen den öffentlichen Schlüssel einmal über einen separat vertrauenswürdigen Weg erhalten, etwa durch manuelle Installation des ersten kontrollierten ZIPs oder die lokale Konstante. Der erste heruntergeladene Release darf seinen eigenen Schlüssel nicht legitimieren.
5. Erst einen Draft mit Testschlüssel in einer privaten WordPress-Installation prüfen. Anschließend einen echten Versions-Tag veröffentlichen; der Release-Workflow bettet den öffentlichen Schlüssel ein, baut ZIPs, lädt diese in einen Draft, signiert das Manifest mit den tatsächlichen Asset-IDs und veröffentlicht erst den vollständigen Satz.

Ohne Secret oder Key-ID bricht der Release-Build vor dem Upload/Publish ab. Diese Konfiguration wurde durch die Codeänderung nicht automatisch im GitHub-Konto angelegt. Ein Repository-/Workflow-Administrator mit Zugriff auf eine ungeschützte Signierumgebung könnte weiterhin bösartigen Code signieren; die geschützte Umgebung ist deshalb Teil der Betriebsanforderung.

## Copy & Paste: Schlüssel für ChurchTools Suite einrichten

Diese Befehle sind für Bash/Zsh auf dem lokalen Entwicklungs-/Release-Rechner mit PHP und Sodium gedacht, nicht für den produktiven WordPress-Webserver. Der Checkout liegt hier unter `/var/www/html/wordpress_dev/wp-content/plugins/churchtools-suite`. Private Dateien landen ausschließlich unter `$HOME/.local/share/churchtools-suite-release-keys`, außerhalb dieses Checkouts und Webroots. Diesen Ordner separat verschlüsselt sichern; er gehört nicht in Git, Release-ZIPs oder öffentlich synchronisierte Ordner. Die Befehle wurden mit Wegwerfschlüsseln geprüft; produktive Schlüssel werden nicht durch die Dokumentation angelegt.

**1. Einmalig Schlüssel erzeugen.** Der Block verweigert einen bereits vorhandenen Schlüsselordner, damit ein erneutes Einfügen keinen bestehenden Schlüssel ersetzt. Die Key-ID ist eine öffentliche Kennung; beim späteren Schlüsselwechsel eine neue Kennung und einen neuen Ordner verwenden.

```bash
(
  set -eu
  umask 077
  export CTS_RELEASE_KEYS_DIR="$HOME/.local/share/churchtools-suite-release-keys"
  php <<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('sodium_crypto_sign_keypair')) {
    throw new RuntimeException('PHP-Sodium fehlt.');
}
$dir = getenv('CTS_RELEASE_KEYS_DIR');
if (!$dir || file_exists($dir) || is_link($dir)) {
    throw new RuntimeException('Schlüsselordner existiert bereits oder Pfad fehlt. Keine Dateien verändert.');
}
if (!mkdir($dir, 0700, true)) {
    throw new RuntimeException('Schlüsselordner konnte nicht angelegt werden.');
}
$id = 'churchtools-suite-2026-11';
$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);
$files = [
    'signing-key.base64' => base64_encode($secret) . "\n",
    'public-keys.php' => "<?php\nreturn " . var_export([$id => base64_encode($public)], true) . ";\n",
    'key-id.txt' => $id . "\n",
];
foreach ($files as $name => $contents) {
    $handle = fopen($dir . '/' . $name, 'x');
    if (!$handle || fwrite($handle, $contents) !== strlen($contents)) {
        throw new RuntimeException('Schlüsseldatei konnte nicht vollständig geschrieben werden.');
    }
    fclose($handle);
}
sodium_memzero($secret);
sodium_memzero($pair);
echo "Schlüsseldateien erzeugt; privater Schlüssel wurde nicht ausgegeben.\n";
PHP
)
```

**2. Nur den öffentlichen Schlüssel ins Plugin übernehmen.** Dieser Block erhält bereits vorhandene öffentliche Schlüssel und lehnt eine abweichende Belegung derselben Key-ID ab. Dafür werden keine privaten Schlüssel in eine Umgebungsvariable oder nach WordPress kopiert.

```bash
(
  set -eu
  export CTS_PLUGIN_DIR='/var/www/html/wordpress_dev/wp-content/plugins/churchtools-suite'
  export CTS_RELEASE_KEYS_DIR="$HOME/.local/share/churchtools-suite-release-keys"
  php <<'PHP'
<?php
declare(strict_types=1);
$target = getenv('CTS_PLUGIN_DIR') . '/includes/update/trusted-keys.php';
$existing = require $target;
$incoming = require getenv('CTS_RELEASE_KEYS_DIR') . '/public-keys.php';
if (!is_array($existing) || !is_array($incoming) || count($incoming) !== 1) {
    throw new RuntimeException('Ungültiger öffentlicher Schlüsselring.');
}
foreach ($incoming as $id => $key) {
    $decoded = is_string($key) ? base64_decode($key, true) : false;
    if (!is_string($decoded) || strlen($decoded) !== 32 || (isset($existing[$id]) && $existing[$id] !== $key)) {
        throw new RuntimeException('Ungültiger öffentlicher Schlüssel oder widersprüchliche Key-ID.');
    }
    $existing[$id] = $key;
}
$contents = "<?php\n/** Public Ed25519 verification keys. */\nreturn " . var_export($existing, true) . ";\n";
if (file_put_contents($target, $contents, LOCK_EX) !== strlen($contents)) {
    throw new RuntimeException('Öffentlicher Schlüsselring konnte nicht geschrieben werden.');
}
echo "Öffentlicher Schlüsselring aktualisiert.\n";
PHP
  php -l "$CTS_PLUGIN_DIR/includes/update/trusted-keys.php"
  git -C "$CTS_PLUGIN_DIR" diff -- includes/update/trusted-keys.php
)
```

Die angezeigte Änderung an `includes/update/trusted-keys.php` enthält ausschließlich den öffentlichen Schlüssel. Diese Datei nach Review regulär committen. Bestehende Installationen benötigen sie einmal über einen vertrauenswürdigen manuellen Upload oder eine lokale Konfiguration; erst dann können sie signierte Folgeupdates akzeptieren.

**3. GitHub-Secret und Key-ID selbst in der Oberfläche hinterlegen.** Diese Einrichtung übernimmt ausschließlich der Betreiber im Browser. Der Harness darf dafür keine CLI- oder API-Aufrufe ausführen. Die beiden vorherigen Copy-&-Paste-Blöcke betreffen nur die lokalen Dateien.

Öffne die [Environment-Einstellungen dieses Repositorys](https://github.com/sw-wordpress/churchtools-suite/settings/environments), also **sw-wordpress/churchtools-suite → Settings → Environments**. Wähle `plugin-releases` aus oder lege die Umgebung über **New environment** mit genau diesem Namen an. Richte zunächst die vorgesehenen Freigaben und erlaubten Release-Tags ein. Zum Konfigurieren der Umgebung benötigst du Administratorrechte für das Repository.

Öffne auf deinem lokalen Rechner die Datei `~/.local/share/churchtools-suite-release-keys/signing-key.base64` in einem vertrauenswürdigen lokalen Texteditor. Kopiere die eine Base64-Zeile ohne Anführungszeichen oder zusätzliche Leerzeichen. Klicke in der GitHub-Umgebung unter **Environment secrets → Add secret**. Trage als Namen exakt `SW_PLUGIN_RELEASE_SIGNING_KEY` ein und füge den Dateiinhalt als Secret-Wert ein. Speichere mit **Add secret**. Gemeint ist der private, Base64-kodierte 64-Byte-Sodium-Schlüssel aus dieser Datei; nicht der öffentliche Schlüssel, nicht die PHP-Schlüsseldatei und nicht ein GitHub-Token. Den Wert weder in den Chat noch in ein Issue oder Protokoll kopieren; danach die Zwischenablage leeren.

Bleibe in derselben Umgebung `plugin-releases` und wähle unter **Environment variables → Add variable** den Namen `SW_PLUGIN_RELEASE_KEY_ID`. Übernimm als Wert die Kennung aus `~/.local/share/churchtools-suite-release-keys/key-id.txt`, ohne Zeilenumbruch oder Anführungszeichen. Beim unveränderten Generierungsblock lautet sie `churchtools-suite-2026-11`. Speichere mit **Add variable**. Diese Kennung ist öffentlich und muss exakt mit dem Eintrag im öffentlichen Schlüsselring `includes/update/trusted-keys.php` übereinstimmen.

Prüfe anschließend, dass in `plugin-releases` das Secret `SW_PLUGIN_RELEASE_SIGNING_KEY` und die Variable `SW_PLUGIN_RELEASE_KEY_ID` aufgeführt sind. Der Secret-Wert ist nach dem Speichern nicht lesbar; die Key-ID bleibt sichtbar. Lege beide Einträge ausdrücklich in der Umgebung und nicht lediglich unter den allgemeinen Repository-Secrets an. Ein vorhandenes Secret nur bewusst im Rahmen des beschriebenen Schlüsselwechsels ersetzen. Diese Einrichtung erzeugt keinen Release-Tag und veröffentlicht kein ZIP; der Tag-Workflow übernimmt später Build, Signierung und Veröffentlichung.

Referenzen: [GitHub: Environment-Secrets anlegen](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets#creating-secrets-for-an-environment), [Environment-Variablen anlegen](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-variables#creating-configuration-variables-for-an-environment), [PHP: Ed25519-Schlüsselpaar erzeugen](https://www.php.net/manual/en/function.sodium-crypto-sign-keypair.php).

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

## Kopierbarer Implementierungsauftrag für andere AI-Harnesses

Den folgenden Block vollständig in den Harness des Zielplugins kopieren. Er soll die Zielwerte aus dessen Repository ermitteln; Schlüssel und GitHub-Schutzregeln richtet der Betreiber separat ein. Die Referenz ist eine zu adaptierende Implementierung, keine bereits veröffentlichte gemeinsame Bibliothek. PHP 8.2, Sodium und ZipArchive werden vorausgesetzt; bei älteren Zielplattformen ist eine bewusst getestete Portierung erforderlich.

```text
Implementiere im aktuellen WordPress-Plugin verifizierte GitHub-Updates mit
ausschließlich manueller Installation. Führe die Umsetzung einschließlich Tests,
Release-Build und Betriebsdokumentation durch.

Referenz:
https://github.com/sw-wordpress/churchtools-suite/tree/60cb2e4
Lies dort docs/GITHUB-UPDATES.md, docs/GITHUB-UPDATES-BETRIEB.md und die unten
genannten Quellen. Falls lokal vorhanden, kannst du dieselben Quellen aus einem
ChurchTools-Suite-Checkout lesen. Lade keinen Code zur Laufzeit von dort nach.

1. Ziel ermitteln und Referenz übernehmen
- Lies die Arbeitsregeln und vorhandenen Updater, Installer, Tests und Workflows
  des Zielplugins. Ermittle das tatsächliche Release-Repository samt numerischer
  GitHub-Repository-ID, Plugin-Slug, Hauptdatei/Basename und Versionskonstante.
  Übernimm keine ChurchTools-Repository-ID, Slugs oder Zugangsdaten.
- Adaptiere includes/update/*, assets/js/churchtools-suite-updates.js,
  bin/release-manifest.php sowie Release-/PR-Test-Workflow der Referenz.
  Integriere die Prüfschaltfläche und Statusausgabe in die vorhandene Admin-UI;
  die ChurchTools-UI muss dafür nicht übernommen werden.
- Konfiguriere source.php, descriptors.php und trusted-keys.php für das Ziel.
  Ein Einzelplugin hat nur seinen eigenen Slug; Addons nur bei bewusst gemeinsam
  verwaltetem Release-Repository aufnehmen.
- Verwende eigene Klassen/Namespaces, PHP-Konstanten, Filter, AJAX-Actions,
  Nonce-Actions, Options-/Lock-/Migrationsnamen, Skripthandles, JS-Globals und
  DOM-IDs. Passe auch Legacy-STATE/LOCK-Konstanten und zugehörige Tests an.
  Mehrere SW-Plugins müssen gleichzeitig ohne Kollisionen laufen können.
  Insbesondere die optionale SW_PLUGIN_RELEASE_PUBLIC_KEYS-Konstante durch eine
  pluginspezifische Konstante ersetzen; keine gemeinsame globale Vertrauensliste.
  Vorhandene Plugin-Konstanten für Pfad, URL und Version korrekt anbinden.

2. Unveränderliche Update- und Sicherheitsregeln
- Nur berechtigte interaktive Backend-Sitzungen dürfen GitHub prüfen:
  maximal einmal je 24 Stunden, erst nach dem Seitenaufbau. Auch Fehler zählen
  als Versuch; Transient-Löschung darf die persistente Sperrfrist nicht aufheben.
  Manuell mit Nonce, update_plugins und mindestens 60 Sekunden Abstand.
  Kein GitHub-Abruf im Frontend, Cron, REST oder WP-CLI. Multisite-Netzwerkrechte
  einschließlich admin-ajax.php beachten; dessen Netzwerkmarkierung fehlt.
- Anzeigen verwenden nur lokalen Cache. Installieren ausschließlich manuell
  über die native WordPress-Pluginübersicht: Einzel-, AJAX- und Sammelupdate.
  Update URI setzen; gemeinsame WordPress-Hooks müssen fremde Plugins unberührt
  lassen. Automatische Installation auch bei alten gecachten Angeboten sperren.
- Nur lokal verankerte öffentliche Ed25519-Schlüssel akzeptieren. Ohne Schlüssel,
  Signatur oder nötige Erweiterungen sicher sperren; kein Hash-only-/Unsigned-
  Fallback und kein erstmals remote geladener Schlüssel als Vertrauensanker.
- Signatur über die exakten Manifestbytes; Repository/ID, Release/Tag, Asset-ID,
  Dateiname, Slug, Version, Größe und SHA-256 strikt binden. Schema, doppelte
  JSON-Schlüssel, stabile Releases und Systemvoraussetzungen validieren.
  HTTPS-/Host-/Redirect-/Downloadlimits, atomaren Lock mit Eigentümerprüfung,
  Rollback-Schutz und maximal sieben Tage gültigen Cache erhalten.
- Vor Core-Extraktion Signatur erneut prüfen und die temporäre ZIP auf Hash,
  Größe, genau einen erwarteten Plugin-Wurzelordner, sichere Pfade/Dateitypen,
  Duplikate, Symlinks, Entpacklimits und passende Plugin-Header prüfen.
  Keine eigene Entpack-/Kopierinstallation; temporäre Dateien bereinigen.
- Alte Updater/Installer vollständig stilllegen. Eigene alte Cronjobs, Optionen,
  Angebote und Auto-Update-Auswahlen gezielt bereinigen. Beim Deaktivieren eigene
  Angebote entfernen, bevor Schutz-Hooks entfallen; fremde Angebote erhalten.

3. Build, Schlüssel und späterer Store-Wechsel
- Eigene installierbare ZIPs bauen. Referenz erwartet <slug>-<version>.zip und
  <slug>/<slug>.php; abweichende Hauptdateien/Versionsformate durchgängig im
  Build, Signierer, Manifestprüfer und Tests adaptieren, nicht nur im Adapter.
  Bei Monorepos behält jedes Addon seine eigene Version.
- Release-Workflow: geschütztes Environment plugin-releases, vorhandenen
  öffentlichen Schlüsselring um den Build-Schlüssel ergänzen, ZIPs bauen und
  in einen Draft hochladen, Manifest anhand tatsächlicher Asset-IDs signieren,
  Signatur hochladen und erst dann veröffentlichen. Fehlende Schlüssel müssen
  vor Veröffentlichung abbrechen; veröffentlichte Releases nicht überschreiben.
- Private Schlüssel weder selbst für Produktion erzeugen noch committen,
  protokollieren oder auf WordPress ablegen. Benötigte Betreiberkonfiguration
  dokumentieren: eigener Ed25519-Schlüssel pro Plugin/Release-Quelle,
  SW_PLUGIN_RELEASE_SIGNING_KEY (64 Byte Sodium Secret Key, Base64) als
  Environment-Secret und SW_PLUGIN_RELEASE_KEY_ID als Environment-Variable.
  Diese CI-Namen dürfen in getrennten Repositories gleich heißen.
- Dokumentiere im Zielplugin dieselben zwei direkt kopierbaren Bash/Zsh-Blöcke
  wie im Referenzabschnitt "Copy & Paste: Schlüssel für ChurchTools Suite
  einrichten": Schlüssel erzeugen und ausschließlich öffentliche Schlüssel ins
  Plugin übernehmen. Ergänze denselben dritten Schritt als Prosa-Anleitung für
  die manuelle GitHub-Oberfläche: Environment, Freigaben/Release-Tags, Secret- und
  Variablennamen, konkrete lokale Quelldateien sowie Prüfung der Key-ID.
  Secret und Variable legt ausschließlich der Betreiber selbst im Browser an;
  keine CLI-/API-Befehle dafür dokumentieren oder ausführen. Passe alle absoluten
  Pluginpfade, privaten Schlüsselordner außerhalb des Webroots, Repository-Namen,
  Key-IDs und Variablenpräfixe an das konkrete Zielplugin an. Keine ungelösten
  Platzhalter und kein bloßer Verweis auf die ChurchTools-Anleitung.
  Behalte Dateirechte 0700/0600, Schutz vor Überschreiben, Erhalt bestehender
  öffentlicher Schlüssel und Ablehnung widersprüchlicher Key-IDs bei. Der private
  Schlüssel darf nicht in Chat, Befehlsargumenten oder Protokollen erscheinen.
  Dokumentiere die vorherige
  Einrichtung der geschützten GitHub-Umgebung. Prüfe die lokalen Befehle isoliert
  mit Wegwerfschlüsseln; führe dabei keine produktive Schlüsselgenerierung oder
  Änderung von GitHub-Secrets aus.
- Öffentlichen Schlüssel einmal vertrauenswürdig an bestehende Installationen
  verteilen, etwa per kontrolliertem manuellem ZIP-Upload. Der erste Release darf
  seinen eigenen Schlüssel nicht legitimieren. Rotation mit Übergangsrelease
  und beiden öffentlichen Schlüsseln sowie Wechsel zu WordPress.org beschreiben.
  Store-Builds müssen den temporären GitHub-Provider und Update URI entfernen.

4. Verifikation und Übergabe
- Adaptiere tests/wp/tests/VerifiedUpdatesTest.php und die Browserprüfung der
  Referenz. Nutze Wegwerfschlüssel, simuliertes HTTP und isoliertes WordPress/DB;
  echte Upgrader-Tests dürfen niemals die produktiven Plugin-Dateien ersetzen.
- Prüfe positive und negative Signaturen, falsche Identität/Assets, manipulierte
  ZIPs, Redirects/Limits, Nonce/Berechtigungen, Frontend/Cron-Sperre, Zeitlimit
  auch nach Fehler/Cache-Löschung, Rollback, Lock-Übernahme, Deaktivierung und
  Multisite. Teste native Einzel-/AJAX-/Sammelupdates sowie parallele Aktivierung
  mehrerer SW-Plugins ohne Hook-, Schlüssel- oder Speicherkollisionen.
- Signiere auch die echten Build-ZIPs mit Wegwerfschlüssel und verifiziere sie
  mit dem Laufzeitprüfer. Prüfe Abbruch ohne Signier-Secret und Paketvollständigkeit.
- Testsuiten ausschließlich auf Pull Requests ausführen. Der Release-Workflow
  validiert/signiert Artefakte, startet aber keine Testsuite. DB-Suiten mit
  gemeinsamem Testzustand sequenziell ausführen. Qualitätsschwellen nicht senken
  und produktiven Code nicht zur Verbesserung der Coverage ausschließen.
- Dokumentiere Änderungen, Testergebnisse, verbleibende Grenzen und ausstehende
  Betreiberkonfiguration. Behaupte keine produktiv aktive Updateversorgung,
  solange Vertrauensanker, GitHub-Schutzregeln und signierter Release fehlen.
```

## Schlüsselwechsel, Ausfälle und WordPress.org

Für Rotation zunächst eine mit dem bisherigen Schlüssel signierte Übergangsrelease mit beiden öffentlichen Schlüsseln ausliefern; erst danach mit der neuen Key-ID signieren. Ein kompromittierter Schlüssel benötigt einen separat vertrauenswürdigen Wiederherstellungsweg. Ohne zusätzliches signiertes zeitlich gültiges Channel-Verzeichnis kann ein erstmaliger Client nicht zuverlässig erkennen, dass eine neuere Version absichtlich vorenthalten wird.

GitHub-Redirects sind auf konkrete HTTPS-Hosts begrenzt. Ein Wechsel des CDN kann deshalb ein Client-Update nötig machen. Metadaten haben Größen- und Zeitlimits; der Paketdownload erfolgt ausschließlich beim manuellen Installationsauftrag. Die Unterstützung fremder privater Repositorys und GitHub-Tokens ist nicht Teil dieser Implementierung.

Vor Einreichung bei WordPress.org sind GitHub-Update-URI und Provider aus dem Store-Build zu entfernen. Eine letzte signierte Übergangsrelease muss diesen Wechsel für bestehende Installationen durchführen; dabei gezielt eigene Updateangebote und Zustände bereinigen. Das wird nicht bereits beim bloßen Vorhandensein einer vermeintlich gleichnamigen Store-Version automatisch umgeschaltet.

## Verifikation

`VerifiedUpdatesTest` verwendet einen privaten WordPress-/MariaDB-Aufbau, Wegwerfschlüssel und vollständig simulierte HTTP-Antworten. Darunter sind echte WordPress-Einzel- und Sammelinstallationen ausschließlich im Vendor-Verzeichnis der Testinstallation. Produktive Plugin-Dateien werden nicht installiert oder überschrieben. Die Browserprüfung sichert den manuellen Prüfdialog und die unveränderte WordPress-Navigation ab.

Die Suiten bleiben auf Pull Requests beschränkt. Der Tag-Build prüft und signiert seine Artefakte; er führt keine Testsuite aus. Ohne eingerichtete produktive Schlüssel kann ein erfolgreicher Testlauf keine echte signierte Veröffentlichung ersetzen.
