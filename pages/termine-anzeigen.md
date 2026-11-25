# Termine anzeigen

Vor der Darstellung müssen Kalender ausgewählt und Termine [synchronisiert](./synchronisation.md) sein. Standardmäßig verwenden die Ansichten die in der Administration ausgewählten Kalender.

## Shortcodes

Im WordPress-Editor einen **Shortcode**-Block einfügen. Die folgenden Beispiele sind unabhängig voneinander:

```text
[cts_list view="list-modern-with-images" limit="5"]
[cts_grid view="modern" columns="3" limit="9"]
[cts_calendar view="monthly-simple" use_calendar_colors="true"]
[cts_countdown]
[cts_carousel slides_per_view="3" autoplay="false"]
[cts_next_event calendars="2,5" event_action="page"]
[cts_event_search]
```

| Shortcode | Zweck / wichtige Vorgabe |
| --- | --- |
| `cts_list` | Terminliste, standardmäßig höchstens 5 Termine |
| `cts_grid` | Raster, standardmäßig 3 Spalten und höchstens 9 Termine |
| `cts_calendar` | Monatskalender; Monatsnavigation, standardmäßig bis zu 100 Termine |
| `cts_countdown` | Genau ein nächster Termin; alternativ `event_id` für einen bestimmten Termin |
| `cts_carousel` | Schiebbare Terminansicht; standardmäßig bis zu 12 Termine, Autoplay aus |
| `cts_next_event` | Genau der nächste anstehende Termin; Klickaktion standardmäßig `none` |
| `cts_event_search` | Suchansicht für Termine |

Die IDs `2,5` sind Beispiele. Verwende die **ChurchTools-ID** aus deiner Kalenderliste, nicht eine WordPress-Seiten-ID.

### Kalender, Details und Klickverhalten

```text
[cts_list calendars="2,5" limit="20" show_location="true" show_services="true" show_appointment_description="true" show_event_description="true" event_action="modal"]
```

- `calendar` und `calendars` erlauben bei `cts_list` kommagetrennte Kalender-IDs. `calendar_ids` wird von `cts_calendar` unterstützt, nicht von `cts_list`; bei der Liste wird dieses unbekannte Attribut ignoriert. Ohne unterstützten Filter gilt die Auswahl in der Administration.
- `limit` begrenzt die Ausgabe; es erweitert nicht den synchronisierten Zeitraum.
- `show_services`, `show_location` und die Beschreibungsparameter steuern Inhalte, soweit die jeweilige View sie unterstützt.
- `event_action="modal"` öffnet ein Overlay, `"page"` eine Einzelseite, `"none"` verhindert diese Detailaktion. `cts_next_event` hat anders als viele andere Ansichten standardmäßig keine Klickaktion.
- `use_calendar_colors="true"` verwendet Kalenderfarben. Vor der Veröffentlichung Lesbarkeit prüfen.

Alle Parameter, View-Namen und abweichenden Standardwerte stehen in der bestehenden [technischen Shortcode-Referenz](https://github.com/sw-wordpress/churchtools-suite/blob/main/docs/SHORTCODES.md). Sie enthält auch spezielle Einzelseiten-/Addon-Parameter; nicht jede Option ist bei jedem Shortcode wirksam.

## Gutenberg-Block

1. Im Block-Inserter **ChurchTools Events** suchen (Kategorie **ChurchTools Suite**).
2. Unter **Ansicht → Ansichtstyp** die gewünschte Darstellung wählen.
3. Unter **Filter & Anzahl** Kalender und Anzahl anpassen, unter **Anzeige-Optionen** sichtbare Inhalte festlegen.
4. Unter **Allgemein → Bei Event-Klick** das Detailverhalten einstellen.
5. Vorschau und veröffentlichte Seite prüfen, auch mobil und ohne Anmeldung.

Shortcode-Bezeichnungen und Block-Einstellungen sind nicht überall dieselben technischen Attributnamen. Verwende im Block die angebotenen Steuerelemente, statt Shortcode-Attribute in Block-HTML zu übertragen.

## Shortcode-Vorlagen und aktuelle Preset-Grenze

Unter **Übersichten → Shortcode-Vorlagen** lassen sich Standardbeispiele ansehen und eigene Presets mit Namen, Shortcode-Typ und Parametern speichern. Die Oberfläche bietet dafür **Als Preset**, **Neues Preset erstellen**, **Preset speichern** und **Kopieren** an.

::: warning Eigene Preset-Slugs werden derzeit nicht geladen
Die Frontend-Handler von `cts_list`, `cts_grid` und `cts_calendar` lösen gespeicherte Preset-Slugs derzeit nicht auf. Ein erzeugter Shortcode mit einem eigenen Slug in `view` kann deshalb **View nicht verfügbar** melden; die gespeicherten Parameter werden nicht angewendet. Das Bearbeiten des Presets behebt diese Grenze nicht.
:::

Verwende bis zur Umsetzung dieser Funktion eine unterstützte View und gib die benötigten Parameter direkt im Shortcode an, wie in den Beispielen oben. Prüfe die veröffentlichte Ausgabe, statt aus einem erfolgreich gespeicherten Preset auf funktionierende Frontend-Einbindung zu schließen.

Entwickler können Templates im Theme unter `churchtools-suite/` überschreiben; die Architektur ist in [CLAUDE.md](https://github.com/sw-wordpress/churchtools-suite/blob/main/CLAUDE.md#frontend-display) beschrieben. Für normale Einrichtung sind keine PHP-Änderungen nötig.
