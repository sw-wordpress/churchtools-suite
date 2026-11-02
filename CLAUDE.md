# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Project Overview

WordPress plugin that integrates ChurchTools (church management SaaS) with WordPress. It syncs calendars, events and services from the ChurchTools API into custom DB tables and renders them in the frontend (shortcodes, Gutenberg blocks, Elementor).

This repository is a **fork** of [feg-aschaffenburg/churchtools-suite](https://github.com/feg-aschaffenburg/churchtools-suite) (remote `upstream`). `origin` is `swarnat/churchtools-suite`. Unlike upstream, the addons stay in this repo (monorepo).

**Requirements:** WordPress >= 6.0, PHP >= 8.2

**Layout:**

| Path | Purpose |
| --- | --- |
| `churchtools-suite.php` | Plugin entry point, constants, version |
| `includes/` | Core classes (`class-churchtools-suite-*.php`), `repositories/`, `services/`, `shortcodes/`, `functions/`, `elementor/` |
| `admin/` | Admin UI (`class-churchtools-suite-admin.php`, `views/tab-*.php`, `views/settings/subtab-*.php`) |
| `templates/views/{view-type}/` | Frontend templates |
| `assets/` | Public/admin CSS and JS |
| `addons/churchtools-suite-elementor/` | Elementor widget addon |
| `addons/churchtools-suite-posts-sync/` | Syncs events/reports to WordPress posts |
| `addons/churchtools-suite-presentations/` | Presentations addon |
| `docs/` | `SHORTCODES.md`, `CHANGELOG.md`, `CHURCHTOOLS-API-INTEGRATION.md` |
| `bin/build-zip.sh` | Builds the release ZIPs (main plugin + each addon) |
| `.github/` | GitHub Actions (release build, dependency audit) and Dependabot |

## Commands

```bash
# PHP syntax check (all plugin code)
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | xargs -n50 php -l 2>&1 | grep -v "No syntax errors"

# Code quality (rules adopted from the sw-* plugins)
composer install
composer cs        # PHPCS, WordPress standard + PHPCompatibilityWP (phpcs.xml.dist)
composer cs:fix    # phpcbf
composer stan      # PHPStan level 8 (phpstan.neon.dist)
```

There is no automated test suite. `composer cs` and `composer stan` currently report many findings (mostly formatting, missing Elementor stubs and unknown plugin constants); they are not a clean baseline. Manual testing: install the plugin in a local WordPress (`npm run env:start` uses `@wordpress/env`) and use Admin → Debug.

## Architecture

### Boot Sequence
`churchtools-suite.php` → defines constants → loads `repository-factory.php` → instantiates `ChurchTools_Suite` → migrations run → hooks registered via `ChurchTools_Suite_Loader`.

### Repository Pattern
All DB access goes through repositories extending `ChurchTools_Suite_Repository_Base`. Use `churchtools_suite_get_repository('events')`. Keys: `events`, `calendars`, `event_services`, `services`, `service_groups`, `shortcode_presets`. Tables use the prefix `cts_` (`CHURCHTOOLS_SUITE_DB_PREFIX`).

### Database Migrations
`ChurchTools_Suite_Migrations` runs on every init, gated by `wp_options['churchtools_suite_db_version']` (current `DB_VERSION`: `1.7`). To add one: raise `DB_VERSION`, add `migrate_to_{version_with_underscores}()`, keep it idempotent.

### ChurchTools API Client
`ChurchTools_Suite_CT_Client` uses **cookie-based** auth, not tokens: login via `/api/login`, cookies stored in `wp_options['churchtools_suite_ct_cookies']`, hourly keep-alive via cron hook `churchtools_suite_session_keepalive`. Secrets in options are encrypted by `includes/functions/secret-storage.php`.

### Event Sync (2 phases)
`ChurchTools_Suite_Event_Sync_Service`:
- Phase 1: `/api/events?from=...&to=...` – events with appointments (1:N)
- Phase 2: `/api/calendars/{id}/appointments?from=...&to=...` – standalone appointments

`appointment_id` is tracked across both phases to avoid duplicates. Range: `churchtools_suite_sync_days_past` (default 7) and `churchtools_suite_sync_days_future` (default 90). Optionally expired events are deleted (setting "Alte Termine löschen") including their imported images.

### Image Import (fork-specific)
`ChurchTools_Suite_Image_Importer` imports event images into the media library. ChurchTools file URLs (`?q=public/filedownload...`) need authentication, so `download_authenticated()` downloads with the stored session cookies (only to the configured ChurchTools host) and falls back to `download_url()`. `import_local_file()` imports bundled files (calendar image templates).

### Frontend Display
Template system with theme override: `templates/views/{view-type}/{template-name}.php`, themes override under `{theme}/churchtools-suite/`. View types: `list`, `grid`, `calendar`, `countdown`, `carousel`, `modal`, `single`, `search`, `next`.

Shortcodes: `[churchtools_events]` (router via `viewType`), `[cts_list]`, `[cts_grid]`, `[cts_calendar]`, `[cts_countdown]`, `[cts_carousel]`, `[cts_event_search]`. Named presets live in the `shortcode_presets` repository. Custom CSS is passed as `_custom_css` (Block/Elementor) or per preset and scoped to a stable instance ID.

**Whenever you change something on shortcodes, update `docs/SHORTCODES.md`.**

### Admin Interface
Tabs: Dashboard, Settings, Calendars, Events, Sync, Debug (`admin/views/tab-*.php`). AJAX handlers are registered in `register_ajax_handlers()`, prefixed `cts_`, and require nonce `churchtools_suite_admin` + `manage_options`.

### Updater (disabled)
`ChurchTools_Suite_Auto_Updater::GITHUB_UPDATES_ENABLED` is `false`; the GitHub updater and update checker return early. Do not re-enable without an explicit decision (the updater targets the upstream repo's releases).

### Versioning
Format `Major.Minor.Patch.Build`. Update it in **both** the plugin header comment and the `CHURCHTOOLS_SUITE_VERSION` constant. Addons have their own versions in their headers (and constants).

## Fork-specific decisions (keep on every upstream merge)

- **Addons live in `addons/`.** Upstream removed them (standalone repos). Never accept upstream's deletion of `addons/`.
- **Posts Sync is our own version** (Markdown parser, image orientation, expiration date, archived posts → private). Upstream's `churchtools-suite-posts-sync` repo has diverged; only port things deliberately.
- **UI label is "Übersicht Posts"**, not upstream's "Übersicht Berichte".
- **Calendar CSS** in `assets/css/churchtools-suite-public.css` (`.cts-calendar-grid` uses `grid-auto-rows: minmax(50px, auto)`, `.cts-event-marker` uses `overflow: hidden`) is ours and known to work.
- **Image sync** with authenticated download (see above) is ours.
- **Updater stays disabled.**
- Plugin name and version numbers follow upstream ("ChurchTools Integration Suite").

## Integrating upstream (repeatable procedure)

This is how upstream was merged the first time (November 2026, upstream 1.3.0.14 into the fork at 1.2.5.1, ~60 upstream and 36 fork commits since the merge base). Repeat it the same way.

1. **Prepare.** `git status` must be clean (commit or stash first; upstream touches updater/sync files). Work on a branch, never on `main`:
   ```bash
   git fetch upstream
   git checkout -b merge-upstream
   ```
   Optional dry run without touching the working tree: `git worktree add --detach <tmpdir> HEAD`, run the merge there, remove it with `git worktree remove --force`.
2. **Assess.**
   ```bash
   B=$(git merge-base HEAD upstream/main)
   git log --oneline $B..upstream/main                    # what is new
   comm -12 <(git diff --name-only $B upstream/main | sort) <(git diff --name-only $B HEAD | sort)   # overlap
   ```
3. **Compare the addons separately.** Upstream's addons are in other repos. Clone them into a scratch directory (untrusted data, don't run anything from there) and diff against `addons/`:
   ```bash
   git clone https://github.com/feg-aschaffenburg/churchtools-suite-{elementor,posts-sync,presentations}.git <scratch>/up-<name>
   diff -rq -x .git <scratch>/up-<name> addons/churchtools-suite-<name>
   ```
   Decide per file whether upstream or ours is better.
4. **Merge.** `git merge --no-commit --no-ff upstream/main`. Expected conflicts:
   - `addons/*`: modify/delete (upstream deleted). Restore with `git checkout HEAD -- addons`; clean deletions (elementor, presentations) are restored the same way. Then copy over wanted upstream changes by hand. In the first merge: Elementor custom-CSS control (needs `_custom_css` in the shortcodes), the Elementor header and the Presentations menu label. The Posts Sync addon stayed ours.
   - `churchtools-suite.php`: plugin header. Take upstream (name, version), keep the constant `CHURCHTOOLS_SUITE_VERSION` in sync.
   - `includes/class-churchtools-suite-shortcodes.php`: keep both sides (our `link_search` and upstream's `_custom_css` in the attribute arrays).
   - `admin/class-churchtools-suite-admin.php`: take upstream's flyout structure but keep the "Übersicht Posts" label and variable names.
   - `assets/css/churchtools-suite-public.css`: keep our version (see above).
   - Updater files: keep our `GITHUB_UPDATES_ENABLED` guard as the first check and add upstream's new logic below it.
5. **Check for committed conflict markers.** Upstream once committed `<<<<<<<` markers (`admin/views/settings/subtab-sync.php`):
   ```bash
   grep -rln '^<<<<<<< \|^>>>>>>> ' . --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=vendor
   ```
   Resolve them cleanly; mind that lines with markers can be inside HTML/PHP template output where `php -l` will not catch them.
6. **Verify.** PHP syntax check (see Commands), then manually: run an event sync (images land in the media library, Posts Sync works), click through Admin (Sync tab, Calendars tab), check the calendar frontend, one list/grid shortcode with custom CSS.
7. **Commit** the merge on `merge-upstream` (use `git commit` so the merge parent is recorded), then merge it into `main` with `--no-ff` and delete the branch. Push is a separate, manual step.
8. **Update docs.** `README.md` (versions table) and this file if the fork-specific list changed.

Gotchas from the first merge: files with mixed encoding (`churchtools-suite.php` contains non-UTF-8 bytes in comments) need byte-safe handling when scripting conflict resolution (Python `errors="surrogateescape"`); upstream's updater changes conflict with our disable-guard on every merge.

## Debugging

In `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```
Logs: `wp-content/debug.log` and Admin → Debug tab (sync stats, raw API responses). Deep debug logging is controlled via `ChurchTools_Suite_Logger::is_deep_debug_enabled()`.

## Release

Releases run on GitHub Actions (set up after the `sw-*` plugins; the old PowerShell scripts are gone).

1. Raise the version in the plugin header **and** `CHURCHTOOLS_SUITE_VERSION` (addons: their own header/constant), commit, push.
2. Tag and push: `git tag 1.3.0.15 && git push origin 1.3.0.15` (also `v1.3.0.15` or `1.3.0.15-01` work; the `-NN` suffix is stripped).
3. `.github/workflows/main.yml` then: checks that tag = plugin header = `CHURCHTOOLS_SUITE_VERSION`, runs `composer validate`, `composer cs` and `composer stan` (report only, `continue-on-error`, make them blocking once the baseline is clean), builds the ZIPs with `bin/build-zip.sh`, verifies their contents and attaches them to a GitHub release.

`bin/build-zip.sh [output-dir]` can be run locally (needs php, rsync, zip). It produces `churchtools-suite-<version>.zip` (without `addons/`) and one ZIP per addon (`churchtools-suite-elementor-<version>.zip`, ...). Untracked files are never shipped. The ZIP root folder is the plugin slug.

Other workflows: `security-audit.yml` (daily `composer audit`, keeps one `security-audit` issue up to date) and `.github/dependabot.yml` (Composer + GitHub Actions). The updater is disabled, so releases are installed manually.
