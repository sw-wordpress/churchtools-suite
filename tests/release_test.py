"""Exercise release tooling in disposable Git repositories, never the working repo."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]


class ReleaseTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="cts-release-")
        self.addCleanup(self.temp.cleanup)
        self.repo = Path(self.temp.name) / "repo"
        self.repo.mkdir()
        (self.repo / "bin").mkdir()
        for name in ("addon-versions.php", "release.sh"):
            shutil.copyfile(ROOT / "bin" / name, self.repo / "bin" / name)
        shutil.copyfile(ROOT / "Makefile", self.repo / "Makefile")
        self.run_cmd("git", "init", "-b", "main")
        self.run_cmd("git", "config", "user.email", "test@example.invalid")
        self.run_cmd("git", "config", "user.name", "Release test")
        self.run_cmd("git", "config", "commit.gpgsign", "false")
        self.run_cmd("git", "config", "tag.gpgsign", "false")
        self.write("churchtools-suite.php", "<?php\n/**\n * Version: 1.0.0\n */\ndefine( 'CHURCHTOOLS_SUITE_VERSION', '1.0.0' );\n")
        for slug, version in (("alpha", "1.0.0.3"), ("beta", "0.1.13")):
            self.write(f"addons/{slug}/{slug}.php", f"<?php\n/**\n * Version: {version}\n */\ndefine( 'CTS_{slug.upper()}_VERSION', '{version}' );\n")
            self.write(f"addons/{slug}/readme.txt", f"Stable tag: {version}\n")
            self.write(f"addons/{slug}/asset.js", "original\n")
        self.pending({"alpha": "patch"})
        self.commit()
        self.run_cmd("git", "tag", "1.0.0")

    def run_cmd(self, *args, ok=True):
        result = subprocess.run(args, cwd=self.repo, text=True, capture_output=True)
        if ok:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        else:
            self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)
        return result.stdout + result.stderr

    def write(self, file, text):
        path = self.repo / file
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text)

    def pending(self, entries):
        self.write("release-pending.json", json.dumps(entries) + "\n")

    def commit(self):
        self.run_cmd("git", "add", ".")
        self.run_cmd("git", "commit", "-qm", "fixture")

    def tool(self, mode, ok=True):
        return self.run_cmd("php", "bin/addon-versions.php", mode, ok=ok)

    def test_changed_asset_requires_entry_and_check_does_not_mutate(self):
        self.write("addons/beta/asset.js", "changed\n")
        self.assertIn("needs an entry", self.tool("check", ok=False))
        self.pending({"alpha": "patch", "beta": "patch"})
        before = self.run_cmd("git", "diff")
        self.tool("check")
        self.assertEqual(self.run_cmd("git", "diff"), before)

    def test_apply_batches_changes_and_synchronizes_metadata(self):
        self.write("addons/beta/asset.js", "first change\n")
        self.pending({"alpha": "patch", "beta": "minor"})
        self.commit()
        self.write("addons/beta/new.php", "<?php // second change\n")
        self.commit()
        self.tool("apply")
        self.assertEqual(json.loads((self.repo / "release-pending.json").read_text()), {})
        for slug, version in (("alpha", "1.0.1"), ("beta", "0.2.0")):
            source = (self.repo / f"addons/{slug}/{slug}.php").read_text()
            self.assertIn(f"Version: {version}\n", source)
            self.assertIn(f"_VERSION', '{version}'", source)
            self.assertEqual((self.repo / f"addons/{slug}/readme.txt").read_text(), f"Stable tag: {version}\n")
        self.tool("apply", ok=False)  # Cannot silently apply the same changes twice.
        self.commit()
        self.run_cmd("git", "tag", "1.0.1")
        self.tool("verify")

    def test_major_and_legacy_minor(self):
        self.pending({"alpha": "minor", "beta": "major"})
        self.tool("apply")
        self.assertIn("Version: 1.1.0", (self.repo / "addons/alpha/alpha.php").read_text())
        self.assertIn("Version: 1.0.0", (self.repo / "addons/beta/beta.php").read_text())

    def test_invalid_plan_never_partially_writes(self):
        self.pending({"alpha": "patch", "beta": "invalid"})
        before = self.run_cmd("git", "diff")
        self.tool("apply", ok=False)
        self.assertEqual(self.run_cmd("git", "diff"), before)

    def test_deleted_file_requires_entry(self):
        (self.repo / "addons/beta/asset.js").unlink()
        self.assertIn("needs an entry", self.tool("check", ok=False))

    def test_untracked_file_requires_entry(self):
        self.write("addons/beta/new.css", "body { color: red; }\n")
        self.assertIn("needs an entry", self.tool("check", ok=False))

    def test_broken_later_addon_does_not_partially_apply_earlier_bump(self):
        self.pending({"alpha": "patch", "beta": "patch"})
        self.write("addons/beta/beta.php", "<?php // Missing header and constant\n")
        before = self.run_cmd("git", "diff")
        self.tool("apply", ok=False)
        self.assertEqual(self.run_cmd("git", "diff"), before)

    def test_new_addon_is_detected(self):
        self.write("addons/gamma/gamma.php", "<?php\n/**\n * Version: 0.1.0\n */\ndefine( 'CTS_GAMMA_VERSION', '0.1.0' );\n")
        self.commit()
        self.assertIn("gamma needs an entry", self.tool("check", ok=False))
        self.pending({"alpha": "patch", "gamma": "patch"})
        self.tool("check")

    def test_verify_rejects_missing_bump_and_pending_entries(self):
        # Start from a release whose addons already use three-part versions.
        self.pending({"alpha": "patch"})
        self.tool("apply")
        self.commit()
        self.run_cmd("git", "tag", "1.0.1")
        self.write("addons/beta/asset.js", "changed\n")
        self.commit()
        self.assertIn("higher version", self.tool("verify", ok=False))
        self.pending({"alpha": "patch", "beta": "patch"})
        self.assertIn("still contains pending", self.tool("verify", ok=False))

    def test_real_release_commits_clears_tags_and_pushes_to_local_remote(self):
        remote = Path(self.temp.name) / "origin.git"
        self.run_cmd("git", "init", "--bare", str(remote))
        self.run_cmd("git", "remote", "add", "origin", str(remote))
        self.run_cmd("git", "push", "origin", "main", "--tags")
        self.pending({"alpha": "patch", "beta": "patch"})
        # Include an inconsistent legacy constant/readme: release reconciles both.
        source = (self.repo / "addons/beta/beta.php").read_text()
        self.write("addons/beta/beta.php", source.replace("_VERSION', '0.1.13'", "_VERSION', '0.1.12'"))
        self.write("addons/beta/readme.txt", "Stable tag: 0.1.7\n")
        self.commit()
        self.run_cmd("make", "release", "VERSION=1.0.1")
        self.assertEqual(self.run_cmd("git", "status", "--porcelain"), "")
        self.assertEqual(json.loads((self.repo / "release-pending.json").read_text()), {})
        self.assertEqual(self.run_cmd("git", "rev-parse", "HEAD"), self.run_cmd("git", "rev-parse", "1.0.1^{}"))
        self.assertIn("refs/tags/1.0.1", self.run_cmd("git", "ls-remote", "--tags", "origin"))
        self.assertIn("Version: 0.1.14", (self.repo / "addons/beta/beta.php").read_text())
        self.tool("verify")
        self.run_cmd("make", "release", "VERSION=1.0.1.1", ok=False)


if __name__ == "__main__":
    unittest.main()
