<?php
/** Release tooling regressions using disposable repositories and a simulated GitHub CLI. */

abstract class CTS_Release_TestCase extends \PHPUnit\Framework\TestCase {
    protected function workflow_step(string $name): string {
        $workflow = file_get_contents(CHURCHTOOLS_SUITE_PATH . '.github/workflows/main.yml');
        self::assertSame(1, preg_match('/      - name: ' . preg_quote($name, '/') . '\n.*?        run: \|\n(.*?)(?=\n      - name:|\z)/s', $workflow, $match));
        return preg_replace('/^          /m', '', $match[1]);
    }

    protected function execute(array $command, ?string $cwd = null, ?array $env = null): array {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    protected function remove_tree(string $root): void {
        if (!is_dir($root)) return;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($root);
    }
}

final class ReleaseCommandTest extends CTS_Release_TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cts-release-command-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/checkout/bin', 0700, true);
        $source = rtrim(CHURCHTOOLS_SUITE_PATH, '/');
        copy($source . '/Makefile', $this->root . '/checkout/Makefile');
        copy($source . '/bin/release.sh', $this->root . '/checkout/bin/release.sh');
        file_put_contents($this->root . '/checkout/churchtools-suite.php', "<?php\n/**\n * Version:           0.8.0\n */\ndefine( 'CHURCHTOOLS_SUITE_VERSION', '0.8.0' );\n");
        $this->runCommand(['git', 'init', '--bare', $this->root . '/origin.git']);
        $this->runCommand(['git', 'init', '-b', 'main']);
        $this->runCommand(['git', 'config', 'user.name', 'Release Test']);
        $this->runCommand(['git', 'config', 'user.email', 'release@example.invalid']);
        $this->runCommand(['git', 'config', 'commit.gpgsign', 'false']);
        $this->runCommand(['git', 'config', 'tag.gpgsign', 'false']);
        $this->runCommand(['git', 'add', '.']);
        $this->runCommand(['git', 'commit', '-m', 'Initial fixture']);
        $this->runCommand(['git', 'remote', 'add', 'origin', $this->root . '/origin.git']);
        $this->runCommand(['git', 'push', 'origin', 'main']);
    }

    protected function tearDown(): void
    {
        $this->remove_tree($this->root);
    }

    private function runCommand(array $command, int $expectedExit = 0): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root . '/checkout');
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame($expectedExit, proc_close($process), $output);
        return trim($output);
    }

    public static function releaseVersions(): array {
        return [['0.8.1'], ['0.8.0.1']];
    }

    /** @dataProvider releaseVersions */
    public function testReleaseCommitsBothVersionsAndPushesBranchAndAnnotatedTag(string $version): void
    {
        $this->runCommand(['make', 'release', 'VERSION=' . $version]);
        $source = file_get_contents($this->root . '/checkout/churchtools-suite.php');
        self::assertStringContainsString('Version:           ' . $version, $source);
        self::assertStringContainsString("define( 'CHURCHTOOLS_SUITE_VERSION', '$version' );", $source);
        self::assertSame('', $this->runCommand(['git', 'status', '--porcelain']));
        self::assertSame('Release ' . $version, $this->runCommand(['git', 'log', '-1', '--format=%s']));
        self::assertSame('tag', $this->runCommand(['git', 'cat-file', '-t', 'refs/tags/' . $version]));
        $head = $this->runCommand(['git', 'rev-parse', 'HEAD']);
        self::assertSame($head, $this->runCommand(['git', '--git-dir=' . $this->root . '/origin.git', 'rev-parse', 'main']));
        self::assertSame($head, $this->runCommand(['git', '--git-dir=' . $this->root . '/origin.git', 'rev-parse', $version . '^{}']));
    }

    public static function rejectedReleases(): array
    {
        return [
            'missing version' => ['missing', ''],
            'invalid version' => ['invalid', 'v0.8.1'],
            'downgrade' => ['downgrade', '0.7.0'],
            'dirty tree' => ['dirty', '0.8.1'],
            'staged changes' => ['staged', '0.8.1'],
            'inconsistent versions' => ['inconsistent', '0.8.1'],
            'existing local tag' => ['local-tag', '0.8.1'],
            'existing remote tag' => ['remote-tag', '0.8.1'],
            'detached head' => ['detached', '0.8.1'],
            'unreachable remote' => ['no-remote', '0.8.1'],
        ];
    }

    /** @dataProvider rejectedReleases */
    public function testRejectedReleaseDoesNotChangePluginOrCommit(string $scenario, string $version): void
    {
        if ($scenario === 'dirty') {
            file_put_contents($this->root . '/checkout/untracked.txt', 'uncommitted work');
        } elseif ($scenario === 'staged') {
            file_put_contents($this->root . '/checkout/staged.txt', 'staged work');
            $this->runCommand(['git', 'add', 'staged.txt']);
        } elseif ($scenario === 'inconsistent') {
            $path = $this->root . '/checkout/churchtools-suite.php';
            file_put_contents($path, str_replace("'0.8.0'", "'0.7.0'", file_get_contents($path)));
            $this->runCommand(['git', 'add', 'churchtools-suite.php']);
            $this->runCommand(['git', 'commit', '-m', 'Inconsistent fixture']);
        } elseif (in_array($scenario, ['local-tag', 'remote-tag'], true)) {
            $this->runCommand(['git', 'tag', '0.8.1']);
            if ($scenario === 'remote-tag') {
                $this->runCommand(['git', 'push', 'origin', '0.8.1']);
                $this->runCommand(['git', 'tag', '-d', '0.8.1']);
            }
        } elseif ($scenario === 'detached') {
            $this->runCommand(['git', 'checkout', '--detach']);
        } elseif ($scenario === 'no-remote') {
            $this->runCommand(['git', 'remote', 'remove', 'origin']);
        }
        $before = file_get_contents($this->root . '/checkout/churchtools-suite.php');
        $head = $this->runCommand(['git', 'rev-parse', 'HEAD']);
        $this->runCommand(['make', 'release', 'VERSION=' . $version], 2);
        self::assertSame($before, file_get_contents($this->root . '/checkout/churchtools-suite.php'));
        self::assertSame($head, $this->runCommand(['git', 'rev-parse', 'HEAD']));
    }

    public function testCurrentVersionCanBeTaggedWithoutAnEmptyCommit(): void
    {
        $head = $this->runCommand(['git', 'rev-parse', 'HEAD']);
        $this->runCommand(['make', 'release', 'VERSION=0.8.0']);
        self::assertSame($head, $this->runCommand(['git', 'rev-parse', 'HEAD']));
        self::assertSame($head, $this->runCommand(['git', '--git-dir=' . $this->root . '/origin.git', 'rev-parse', '0.8.0^{}']));
    }

    public function testRejectedBranchPushDoesNotPublishTheTag(): void
    {
        $head = $this->runCommand(['git', 'rev-parse', 'HEAD']);
        // Advance origin independently, so the release branch cannot be pushed without force.
        $tree = $this->runCommand(['git', 'rev-parse', 'HEAD^{tree}']);
        $remoteHead = $this->runCommand(['git', 'commit-tree', $tree, '-p', $head, '-m', 'Remote commit']);
        $this->runCommand(['git', 'push', 'origin', $remoteHead . ':refs/heads/main']);
        $this->runCommand(['make', 'release', 'VERSION=0.8.1'], 2);
        self::assertSame($remoteHead, $this->runCommand(['git', '--git-dir=' . $this->root . '/origin.git', 'rev-parse', 'main']));
        self::assertSame('', $this->runCommand(['git', 'ls-remote', '--tags', 'origin', 'refs/tags/0.8.1']));
        self::assertSame('tag', $this->runCommand(['git', 'cat-file', '-t', 'refs/tags/0.8.1']));
    }
}

final class ReleaseWorkflowTest extends CTS_Release_TestCase
{
    public static function releaseStates(): array
    {
        return [
            'new draft' => ['missing', 0],
            'existing draft' => ['draft', 0],
            'published release' => ['published', 1],
            'metadata request fails' => ['api-error', 1],
        ];
    }

    /** @dataProvider releaseStates */
    public function testUploadResolvesDraftMetadataAfterUpload(string $state, int $expectedExit): void
    {
        $workflow = file_get_contents(rtrim(CHURCHTOOLS_SUITE_PATH, '/') . '/.github/workflows/main.yml');
        self::assertIsString($workflow);
        self::assertSame(1, preg_match('/      - name: Upload packages to draft release\n.*?        run: \|\n(.*?)(?=\n      - name:)/s', $workflow, $match));
        $script = preg_replace('/^          /m', '', $match[1]);
        self::assertIsString($script);
        $root = sys_get_temp_dir() . '/cts-release-workflow-' . bin2hex(random_bytes(6));
        mkdir($root . '/dist', 0700, true);
        try {
            file_put_contents($root . '/dist/churchtools-suite-0.8.0.zip', 'disposable test package');
            file_put_contents($root . '/gh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$RUNNER_TEMP/commands"
case "$*" in
  'release view 0.8.0 --json isDraft')
    case "$TEST_RELEASE_STATE" in
      missing) exit 1 ;;
      published) printf '{"isDraft":false}' ;;
      *) printf '{"isDraft":true}' ;;
    esac ;;
  'release create 0.8.0 --draft --verify-tag --title 0.8.0')
    test "$TEST_RELEASE_STATE" = missing ;;
  "release upload 0.8.0 $RUNNER_TEMP/dist/churchtools-suite-0.8.0.zip --clobber")
    touch "$RUNNER_TEMP/uploaded" ;;
  'release view 0.8.0 --json apiUrl --jq .apiUrl')
    test -f "$RUNNER_TEMP/uploaded"
    printf 'https://api.github.com/repos/sw-wordpress/churchtools-suite/releases/42\n' ;;
  'api https://api.github.com/repos/sw-wordpress/churchtools-suite/releases/42')
    test -f "$RUNNER_TEMP/uploaded"
    if [ "$TEST_RELEASE_STATE" = api-error ]; then exit 1; fi
    printf '{"id":42,"draft":true,"assets":[{"id":56,"name":"churchtools-suite-0.8.0.zip"}]}' ;;
  *) printf 'Unexpected GitHub request: %s\n' "$*" >&2; exit 1 ;;
esac
BASH
            );
            chmod($root . '/gh', 0700);
            $process = proc_open(['bash', '-e', '-o', 'pipefail', '-c', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
                'PATH' => $root . ':' . getenv('PATH'),
                'RUNNER_TEMP' => $root,
                'RELEASE_TAG' => '0.8.0',
                'GITHUB_REPOSITORY' => 'sw-wordpress/churchtools-suite',
                'TEST_RELEASE_STATE' => $state,
            ]);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame($expectedExit, proc_close($process), $output);
            if ($expectedExit === 0) {
                $release = json_decode(file_get_contents($root . '/release.json'), true, 32, JSON_THROW_ON_ERROR);
                self::assertSame(42, $release['id']);
                self::assertSame(56, $release['assets'][0]['id']);
            }
            if ($state === 'published') {
                self::assertFileDoesNotExist($root . '/uploaded');
                self::assertFileDoesNotExist($root . '/release.json');
                self::assertStringContainsString('already published', $output);
            }
        } finally {
            $this->remove_tree($root);
        }
    }
}

final class ReleaseSigningKeyTest extends CTS_Release_TestCase {
    public static function key_states(): array {
        return ['new key' => ['new', 0], 'same key' => ['same', 0], 'conflicting key' => ['conflict', 1], 'missing secret' => ['missing', 1]];
    }

    /** @dataProvider key_states */
    public function test_public_key_export_preserves_trust(string $state, int $expected): void {
        $root = sys_get_temp_dir() . '/cts-key-test-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        $pair = sodium_crypto_sign_keypair();
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));
        $historical = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
        $keys = ['historical' => $historical];
        if ($state === 'same') $keys['build'] = $public;
        if ($state === 'conflict') $keys['build'] = $historical;
        $file = $root . '/keys.php';
        $before = '<?php return ' . var_export($keys, true) . ';';
        file_put_contents($file, $before);
        try {
            [$exit, $output] = $this->execute([PHP_BINARY, CHURCHTOOLS_SUITE_PATH . 'bin/release-manifest.php', 'public-key', $file], null, [
                'SW_PLUGIN_RELEASE_SIGNING_KEY' => $state === 'missing' ? '' : $secret,
                'SW_PLUGIN_RELEASE_KEY_ID' => 'build',
            ]);
            self::assertSame($expected, $exit, $output);
            self::assertStringNotContainsString($secret, $output);
            if ($expected !== 0) {
                self::assertSame($before, file_get_contents($file));
            } else {
                $actual = require $file;
                self::assertSame(['historical' => $historical, 'build' => $public], $actual);
            }
        } finally {
            $this->remove_tree($root);
        }
    }

    public static function packaged_keys(): array {
        return ['matching key' => [true, 0], 'different key' => [false, 1]];
    }

    /** @dataProvider packaged_keys */
    public function test_workflow_verifies_the_key_inside_the_main_zip(bool $matches, int $expected): void {
        $root = sys_get_temp_dir() . '/cts-packaged-key-' . bin2hex(random_bytes(6));
        mkdir($root . '/dist', 0700, true);
        mkdir($root . '/includes/update', 0700, true);
        $public = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
        $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
        $keys = '<?php return ' . var_export(['build' => $public], true) . ';';
        file_put_contents($root . '/includes/update/trusted-keys.php', $keys);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($root . '/dist/churchtools-suite-0.8.0.zip', ZipArchive::CREATE));
        $zip->addFromString('churchtools-suite/churchtools-suite.php', '<?php /* Package fixture */');
        $zip->addFromString('churchtools-suite/includes/update/trusted-keys.php', $matches ? $keys : str_replace($public, $other, $keys));
        $zip->close();
        try {
            $script = str_replace('${{ steps.metadata.outputs.version }}', '0.8.0', $this->workflow_step('Verify ZIP contents'));
            [$exit, $output] = $this->execute(['bash', '-e', '-o', 'pipefail', '-c', $script], $root, [
                'PATH' => getenv('PATH'), 'RUNNER_TEMP' => $root, 'KEY_ID' => 'build',
            ]);
            self::assertSame($expected, $exit, $output);
        } finally {
            $this->remove_tree($root);
        }
    }

    public static function tags(): array {
        return ['plain' => ['1.4.0.8', 0], 'prefixed build' => ['v1.4.0.8-01', 0], 'prerelease' => ['1.4.0.8-rc1', 1], 'five parts' => ['1.4.0.8.1', 1], 'arbitrary build' => ['1.4.0.8-001', 1]];
    }

    /** @dataProvider tags */
    public function test_workflow_rejects_tags_the_runtime_cannot_verify(string $tag, int $expected): void {
        $root = sys_get_temp_dir() . '/cts-release-tag-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        try {
            [$exit, $output] = $this->execute(['bash', '-e', '-o', 'pipefail', '-c', $this->workflow_step('Determine version')], $root, [
                'PATH' => getenv('PATH'), 'GITHUB_REF_NAME' => $tag, 'GITHUB_OUTPUT' => $root . '/output',
            ]);
            self::assertSame($expected, $exit, $output);
            if ($expected === 0) self::assertSame("version=1.4.0.8\n", file_get_contents($root . '/output'));
            else self::assertFileDoesNotExist($root . '/output');
        } finally {
            $this->remove_tree($root);
        }
    }
}
