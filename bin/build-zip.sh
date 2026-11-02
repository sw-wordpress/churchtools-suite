#!/usr/bin/env bash
# Builds installable WordPress plugin ZIPs: the main plugin and every addon under addons/ (each its own plugin).
# Usage: bin/build-zip.sh [output-dir]      (needs php, rsync, zip; no Composer runtime dependencies are shipped)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$OUT"

# Reads the plugin version from the header comment of a plugin file.
plugin_version() {
  grep -m1 -E '^\s*\*?\s*Version:' "$1" | awk '{print $NF}' | tr -d '\r'
}

# package <slug> <source dir> <main plugin file> [rsync exclude...]
package() {
  local slug="$1" src="$2" main="$3"
  shift 3
  local dest="$STAGE/$slug"
  mkdir -p "$dest"

  # Untracked, not ignored files (local notes, experiments) are never shipped.
  local untracked="$STAGE/untracked-$slug.txt"
  if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    local prefix="${src#"$ROOT"/}"
    [ "$src" = "$ROOT" ] && prefix=""
    git -C "$ROOT" ls-files --others --exclude-standard -- "${prefix:-.}" \
      | { [ -n "$prefix" ] && sed "s|^$prefix/||" || cat; } | sed 's|^|/|' > "$untracked" || true
  else
    : > "$untracked"
  fi

  rsync -a --exclude-from="$untracked" --exclude-from=- "$@" "$src/" "$dest/" <<'EXCLUDES'
.git
.gitignore
.editorconfig
.claude
.DS_Store
*.cache
*.log
*.zip
/.github
/vendor
/node_modules
/dist
/bin
/package.json
/package-lock.json
/.wp-env.json
/composer.json
/composer.lock
/phpcs*.xml.dist
/phpstan.neon*
/CLAUDE.md
/ROADMAP.md
EXCLUDES

  # Every PHP file in the package must parse; the main file must carry a version.
  while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
  done < <(find "$dest" -name '*.php' -print0)
  local version
  version="$(plugin_version "$dest/$main")"
  [ -n "$version" ] || { echo "No Version header in $slug/$main" >&2; exit 1; }

  local zip="$OUT/$slug-$version.zip"
  rm -f "$zip"
  (cd "$STAGE" && zip -qr "$zip" "$slug")
  echo ">> $zip"
}

# Main plugin: addons are separate plugins and are packaged on their own.
package churchtools-suite "$ROOT" churchtools-suite.php --exclude=/addons

for addon in "$ROOT"/addons/*/; do
  slug="$(basename "$addon")"
  [ -f "$addon/$slug.php" ] || continue
  package "$slug" "${addon%/}" "$slug.php"
done
