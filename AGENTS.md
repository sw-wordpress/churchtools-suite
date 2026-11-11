# Repository instructions

These instructions apply to every coding agent and harness working in this repository.
Read `CLAUDE.md` for architecture, security and testing guidance. The release rules
below take precedence over older versioning instructions in other documents.

## Versions and pending addon releases

- New versions use **Major.Minor.Patch** only. Never introduce a fourth component.
- During development, do not bump addon headers, version constants or `Stable tag`.
  Instead update the tracked `release-pending.json` in the same change.
- Its keys are full addon directory names, for example:
  `"churchtools-suite-posts-sync": "minor"`.
- Every change under an addon directory needs an entry, including assets, documentation,
  additions and deletions. Keep at most one entry per addon. Preserve other entries.
- Choose `patch` for fixes and compatible maintenance, `minor` for compatible new
  functionality, `major` for incompatible changes. For versions below 1.0, still
  explicitly use `major` for incompatible changes.
- Merge multiple changes by keeping the highest requested level:
  `major > minor > patch`. Never downgrade an existing entry or clear it during development.
- Main-plugin changes outside `addons/` do not require an addon entry. The user
  chooses the main-plugin version explicitly when requesting a release.
- Run `make release-check` before handing off changes. It compares addon directories
  with the latest reachable release tag and rejects missing entries.
- Only a user-requested release runs `make release VERSION=X.Y.Z`. This command
  commits, tags and pushes. It applies each pending addon bump exactly once, updates
  header/constant/readme together and resets `release-pending.json` to `{}` in the
  release commit. **Never empty AGENTS.md**; it contains permanent instructions.
- Existing four-part addon versions are migrated on their next bump: patch from
  `1.0.0.3` becomes `1.0.1`, minor becomes `1.1.0`, major becomes `2.0.0`.
- Do not manually empty pending entries after a failed push: the local release commit
  and tag already contain the prepared release. Retry that atomic push after resolving
  the failure; do not prepare another release for the same changes.

The initial pending entries migrate Elementor's legacy version and reconcile the
inconsistent Posts Sync header, constant and readme on the next release.
