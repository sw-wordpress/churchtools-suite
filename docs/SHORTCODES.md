# Shortcodes Reference

This page documents all shortcodes registered by **ChurchTools Suite** and its
add-ons, together with their parameters and the accepted values for each.

> **Boolean values:** Wherever a parameter is marked as *boolean*, you may pass
> `true`/`false`, `1`/`0`, `yes`/`no`, or `on`/`off`. Anything else is treated as
> `false`. (See `ChurchTools_Suite_Shortcodes::parse_boolean()`.)

## Overview

| Shortcode | Purpose | Source |
|-----------|---------|--------|
| `[churchtools_events]` | Generic router — dispatches to list/grid/calendar via the `view` prefix | Core |
| `[cts_list]` | Event list views | Core |
| `[cts_grid]` | Event grid / card views | Core |
| `[cts_calendar]` | Monthly calendar view | Core |
| `[cts_countdown]` | Live countdown to the next (or a specific) event | Core |
| `[cts_carousel]` | Horizontal swipeable event carousel | Core |
| `[cts_next_event]` | The single next upcoming event, in a professional-modal-style card | Core |
| `[cts_event_search]` | Event search form + results | Core |
| `[cts_posts]` | List synced ChurchTools posts | Add-on: *Posts Sync* |
| `[cts_presentation]` | Auto-rotating presentation slider for a page | Add-on: *Presentations* |

---

## Common parameters

These parameters are shared by most of the core event shortcodes
(`cts_list`, `cts_grid`, `cts_calendar`, `cts_countdown`, `cts_carousel`).
Shortcode-specific parameters and the available `view` values are listed in each
section further below.

### Data / filtering

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `calendar` | string | `''` | Single calendar ID, or comma-separated list. Falls back to the calendars selected in admin settings if empty. |
| `calendars` | string | `''` | Comma-separated calendar IDs. Takes precedence over `calendar`. |
| `tags` | string | `''` | Comma-separated tag names to filter by (e.g. `Gottesdienst,Alpha`). |
| `limit` | int | varies | Maximum number of events. Defaults differ per shortcode (see below). |
| `from` | string | `''` | Start of date range (e.g. `2026-01-01`). |
| `to` | string | `''` | End of date range. |
| `show_past_events` | boolean | `false` | Include events that already started. |
| `class` | string | `''` | Extra CSS class added to a wrapper `<div>`. |
| `link_search` | boolean | `true` | Apply the active front-end search term (from the URL) to this view. |

### Display toggles

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_event_description` | boolean | `true` | Show description for events. |
| `show_appointment_description` | boolean | `true` | Show description for appointments. |
| `show_location` | boolean | `true` | Show the event location. |
| `show_services` | boolean | `false` | Show assigned services. |
| `show_time` | boolean | `true` | Show start time. |
| `show_tags` | boolean | `true` | Show event tags. |
| `show_calendar_name` | boolean | `true` | Show the calendar name. |
| `show_images` | boolean | `true` | Show event images (views that support images). |
| `image_fit` | string | `cover` | Image scaling mode. Options: `cover`, `contain`. |
| `event_action` | string | `modal` | What happens on click. Options: `modal` (open popup), `page` (link to single-event page), `none`. |
| `show_description` | boolean | — | **Deprecated.** Legacy alias that sets both `show_event_description` and `show_appointment_description`. |

### Style management

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `style_mode` | string | `theme` | Styling source. Options: `theme` (inherit theme), `plugin` (built-in defaults), `custom` (use the `custom_*` values below). |
| `use_calendar_colors` | boolean | `false`¹ | Color events using their ChurchTools calendar color. |
| `custom_primary_color` | color | `#2563eb`² | Primary/accent color (used when `style_mode="custom"`). |
| `custom_text_color` | color | `#1e293b`² | Text color. |
| `custom_background_color` | color | `#ffffff`² | Background color. |
| `custom_border_radius` | int (px) | `6`² | Corner radius. |
| `custom_font_size` | int (px) | `14`² | Base font size. |
| `custom_padding` | int (px) | `12`² | Inner padding. |
| `custom_spacing` | int (px) | `8`² | Spacing between items. |

¹ Default is `true` for `cts_carousel`.
² Defaults vary per shortcode — see the per-shortcode notes (countdown and
carousel use different defaults).

---

## `[churchtools_events]` — Generic router

Routes to the list, grid, or calendar handler based on the **prefix** of the
`view` value. Accepts the common parameters above plus `columns` (for grid).

```text
[churchtools_events view="list-classic"]
[churchtools_events view="grid-simple" columns="3"]
[churchtools_events view="calendar-monthly-simple"]
```

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `view` | string | `list-classic` | Must start with `list-`, `grid-`, or `calendar-`. The prefix selects the handler; the rest selects the template. |
| `columns` | int | `3` | Forwarded to the grid handler. |

If `event_id` is present in the URL, this shortcode renders the single-event
page instead (template from the `template` URL parameter).

**`view` options:** any value valid for `[cts_list]`, `[cts_grid]`, or
`[cts_calendar]` (see below), prefixed accordingly — e.g. `list-modern`,
`grid-modern`, `calendar-monthly-simple`.

---

## `[cts_list]` — List views

```text
[cts_list view="classic"]
[cts_list view="modern" limit="10"]
[cts_list view="classic" calendar="2" show_filter="true"]
```

**`view` options** (German IDs are canonical; English/short aliases also work):

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `list-einfach` | `einfach`, `list-simple`, `simple` | Simple |
| `list-klassisch` | `klassisch`, `list-classic`, `classic` | Classic *(default)* |
| `list-klassisch-mit-bildern` | `list-classic-with-images`, `classic-with-images` | Classic with images |
| `list-minimal` | `minimal` | Minimal |
| `list-modern` | `modern` | Modern |
| `list-modern-with-images` | — | Modern with images |

**Specific parameters** (in addition to the common ones):

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `5` | Maximum number of events. |
| `show_month_separator` | boolean | `true` | Insert month heading rows. |
| `show_filter` | boolean | `false` | Render front-end calendar/tag filter controls above the list. |
| `order` | string | `asc` | Sort order. Options: `asc`, `desc`. |
| `date_from` | string | `''` | Alternative to `from`. |
| `date_to` | string | `''` | Alternative to `to`. |
| `filter_tags` | string | `''` | Alternative to `tags`. |

---

## `[cts_grid]` — Grid / card views

```text
[cts_grid view="simple"]
[cts_grid view="modern" columns="3" limit="9"]
```

**`view` options:**

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `grid-klassisch` | `grid-classic`, `classic` | Classic (hero image) |
| `grid-einfach` | `grid-simple`, `simple` | Simple — all details *(default)* |
| `grid-minimal` | `minimal` | Minimal (compact) |
| `grid-modern` | `modern` | Modern (card style) |

**Specific parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `columns` | int | `3` | Number of columns. Clamped to `1`–`6`. |
| `limit` | int | `9` | Maximum number of events. |

---

## `[cts_calendar]` — Monthly calendar

```text
[cts_calendar]
[cts_calendar view="monthly-simple" calendar="2"]
```

Navigation between months uses the `cts_month` / `cts_year` URL parameters.

**`view` options:**

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `calendar-monatlich-einfach` | `calendar-monthly-simple`, `monthly-simple` | Monthly (simple) *(default)* |

**Specific parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `100` | Higher default so a full month of events is loaded. |

> Style defaults for the calendar: `custom_padding=8`, `custom_spacing=0`
> (other `custom_*` defaults as in the common table).

---

## `[cts_countdown]` — Countdown to next event

```text
[cts_countdown]
[cts_countdown view="countdown-klassisch"]
[cts_countdown event_id="1234" show_event_description="true"]
```

Always shows exactly **one** event (`limit` is forced to `1`).

**`view` options:**

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `countdown-klassisch` | `countdown-classic`, `classic` | Classic (split layout) *(default)* |

**Specific parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `event_id` | int | `''` | Show a specific event instead of the next upcoming one. |
| `hero_title_font_size` | int (px) | `0` | Override hero title size (`0` = automatic). Clamped to `0`–`120`. |
| `hero_layout_preset` | string | `standard` | Hero layout. Options: `compact`, `standard`, `hero`. |
| `hero_mobile_optimize` | boolean | `true` | Apply mobile-optimized hero layout. |

> Style defaults for countdown differ: `custom_primary_color=#3b82f6`,
> `custom_text_color=#ffffff`, `custom_background_color=#2d3748`,
> `custom_border_radius=8`, `custom_font_size=16`, `custom_padding=24`,
> `custom_spacing=16`.

---

## `[cts_carousel]` — Event carousel

```text
[cts_carousel]
[cts_carousel view="carousel-klassisch" slides_per_view="3"]
[cts_carousel autoplay="true" loop="true" autoplay_delay="4000"]
```

**`view` options:**

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `carousel-klassisch` | `carousel-classic`, `classic` | Classic (swipe) *(default)* |
| `carousel-einzel-event` | `carousel-single-hero`, `single-hero` | Single event (hero slider) |

**Specific parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `12` | Maximum number of events. |
| `slides_per_view` | int | `3` | Slides visible at once. Clamped to `1`–`6`. |
| `autoplay` | boolean | `false` | Auto-advance slides. |
| `autoplay_delay` | int (ms) | `5000` | Delay between slides. Clamped to `1000`–`10000`. |
| `loop` | boolean | `true` | Loop back to the start. |
| `show_title` | boolean | `true` | Show event title. |
| `hero_title_font_size` | int (px) | `0` | Hero title size for the single-event view. Clamped to `0`–`120`. |

> Carousel defaults differ: `use_calendar_colors=true`, `custom_text_color=#111827`,
> `custom_border_radius=0`, `custom_padding=16`, `custom_spacing=16`.
> `show_tags` defaults to `false`.

---

## `[cts_next_event]` — Next upcoming event

Shows the **single next upcoming event** from a list of calendar IDs (or all
calendars selected in admin). The layout is modelled on the *professional modal*
UI: a main area (title, calendar badge, descriptions, services) next to a
sidebar (image, date, time, tags, location).

```text
[cts_next_event]
[cts_next_event calendars="2,5,7"]
[cts_next_event calendar="2" show_services="true"]
[cts_next_event event_action="page"]
```

- Pass `calendars` (or `calendar`) with comma-separated IDs to restrict the
  source calendars. Leave both empty to use **all** calendars selected in
  **Admin → Calendars**.
- Always renders exactly **one** event (`limit` is forced to `1`); when nothing
  is upcoming, a friendly empty-state card is shown instead.

**`view` options:**

| Value | Alias(es) | Description |
|-------|-----------|-------------|
| `professional` | `classic` | Professional modal-style card *(default)* |
| `main` | — | Horizontal feature card — image left, content right (from Claude Design). Best for main content areas. |
| `sidebar` | — | Compact vertical card — image on top with overlay badge, stacked details (from Claude Design). Best for sidebars/widgets. |

The `main` and `sidebar` cards include a "Mehr erfahren" call-to-action button
that appears only when `event_action` is `modal` or `page`. They use a green
accent by default; set `use_calendar_colors="true"` (the default) to tint the
accent, badge, icons, and button with the event's calendar color.

**Specific parameters** (also accepts the common data/display/style parameters):

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `event_action` | string | `none` | Click behaviour. Options: `none` (default — all details already shown), `modal`, `page`. |
| `empty_message` | string | `''` | Custom text shown when there is no upcoming event. |
| `use_calendar_colors` | boolean | `true` | Tint the title bar, badge, tags, and icons with the event's calendar color. |

> Differs from other shortcodes: `event_action` defaults to `none` and
> `use_calendar_colors` defaults to `true`. Style defaults: `custom_border_radius=12`,
> `custom_padding=28`, `custom_spacing=16`.

---

## `[cts_event_search]` — Event search

```text
[cts_event_search]
[cts_event_search view="classic" custom_class="my-search"]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `view` | string | `classic` | Search template. Only `search-classic` (alias `classic`) is available. |
| `custom_class` | string | `''` | Extra CSS class for the search container. |

The submitted search term is read from the URL query and passed to the template.

---

## `[cts_posts]` — Synced posts list *(Posts Sync add-on)*

Lists WordPress posts that were synced from ChurchTools. Requires the
**ChurchTools Suite – Posts Sync** add-on to be active.

```text
[cts_posts]
[cts_posts limit="5" show_excerpt="true" excerpt_words="40"]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `10` | Maximum number of posts (minimum `1`). |
| `post_type` | string | `''` | Restrict to a specific post type (empty = default). |
| `show_date` | boolean | `true` | Show the publication date. |
| `show_excerpt` | boolean | `true` | Show an excerpt. |
| `excerpt_words` | int | `28` | Number of words in the excerpt. |
| `only_new` | boolean | `false` | Only show posts flagged as new. |
| `only_synced` | boolean | `true` | Only show posts that originate from ChurchTools. |

> A matching Gutenberg block (*ChurchTools Posts*) exposes the same options as
> block attributes: `limit`, `postType`, `showDate`, `showExcerpt`,
> `excerptWords`, `onlyNew`, `onlySynced`.

---

## `[cts_presentation]` — Presentation slider *(Presentations add-on)*

Renders an auto-rotating presentation for the current page. Requires the
**ChurchTools Suite – Presentations** add-on, and the page must have
presentation mode enabled (configured via page meta, not shortcode attributes).

```text
[cts_presentation]
[cts_presentation page_id="42"]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page_id` | int | current page | Render the presentation configured on another page. |

All other behavior (event ID, special tags, seconds per slide, and the two slide
views) is taken from the page's presentation settings (post meta), not from
shortcode attributes. Slide view defaults: slide 1 = `list-classic`,
slide 2 = `grid-modern`; minimum slide duration is `3` seconds.

---

## Notes

- **Presets:** For `cts_list`, `cts_grid`, and `cts_calendar`, passing a saved
  preset slug as the `view` value loads that preset's stored configuration. The
  preset's parameters always override parameters given in the shortcode.
- **Calendar fallback:** When neither `calendar` nor `calendars` is given,
  events come from the calendars selected in **Admin → Calendars**.
- **Single-event pages:** Any event view linked with `event_action="page"`
  points to the single-event page, rendered with the
  `churchtools_suite_single_template` option (default `professional`).

### Gemeinsame Darstellung mit CloudCalendar

Die bestehenden `cts_*`-Shortcodes bleiben unverändert. Für eine gemeinsame Darstellung mehrerer Kalender mit
anderen Plugin-Quellen können stattdessen CloudCalendar-Ansichten eingebunden werden; siehe [Anbindung](CLOUDCALENDAR.md).
