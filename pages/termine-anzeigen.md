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
[cts_list calendar_ids="2,5" limit="20" show_location="true" show_services="true" show_appointment_description="true" show_event_description="true" event_action="modal"]
```

- `calendar`, `calendars` und `calendar_ids` erlauben kommagetrennte Kalender-IDs. Ohne Filter gilt die Auswahl in der Administration.
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

## Wiederverwendbare Shortcode-Vorlagen

1. **Übersichten → Shortcode-Vorlagen** öffnen.
2. Unter **Standard-Shortcodes** die passende Vorlage auswählen und **Als Preset** verwenden oder **Neues Preset erstellen** öffnen.
3. **Preset-Name**, **Shortcode-Typ** und Parameter festlegen, optional **Eigenes CSS** ergänzen und **Preset speichern**.
4. Unter **Meine Presets** den erzeugten Shortcode mit **Kopieren** übernehmen.

Bei `cts_list`, `cts_grid` und `cts_calendar` kann ein gespeicherter Preset-Slug als `view` geladen werden. **Gespeicherte Preset-Parameter überschreiben gleichnamige Angaben im Shortcode.** Ändere bei unerwarteter Ausgabe daher die gespeicherte Vorlage, nicht nur den eingefügten Text.

Eigenes CSS wird auf die jeweilige Instanz bzw. das Preset beschränkt. Änderungen weiterhin mit mehreren Ansichten auf derselben Seite prüfen. Entwickler können Templates im Theme unter `churchtools-suite/` überschreiben; die Architektur ist in [CLAUDE.md](https://github.com/sw-wordpress/churchtools-suite/blob/main/CLAUDE.md#frontend-display) beschrieben. Für normale Einrichtung sind keine PHP-Änderungen nötig.
