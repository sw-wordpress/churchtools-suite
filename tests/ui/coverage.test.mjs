import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const check = resolve(import.meta.dirname, '../../bin/check-coverage.php');
for (const covered of [80, 81]) {
  test(`the coverage gate ${covered === 80 ? 'rejects exactly' : 'accepts more than'} 80 percent`, async () => {
    const dir = await mkdtemp(join(tmpdir(), 'cts-coverage-gate-'));
    try {
      const php = join(dir, 'php.xml'), js = join(dir, 'ui.json');
      await writeFile(php, `<coverage><project><metrics statements="100" coveredstatements="${covered}"/></project></coverage>`);
      await writeFile(js, JSON.stringify({ 'production.js': { s: Object.fromEntries(Array.from({ length: 100 }, (_, i) => [String(i), i < covered ? 1 : 0])) } }));
      const result = spawnSync('php', [check, php, js], { encoding: 'utf8' });
      assert.equal(result.status, covered === 80 ? 1 : 0, result.stdout + result.stderr);
    } finally { await rm(dir, { recursive: true, force: true }); }
  });
}
test('missing coverage data cannot pass', () => {
  const result = spawnSync('php', [check, '/nonexistent/php.xml', '/nonexistent/ui.json'], { encoding: 'utf8' });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /missing measurements do not pass/);
});
