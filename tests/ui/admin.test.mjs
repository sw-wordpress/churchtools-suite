import { after, before, test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, rm, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { chromium } from 'playwright-core';
import v8ToIstanbul from 'v8-to-istanbul';

const root = resolve(import.meta.dirname, '../..');
let fixtureDir, browser, server, origin;
const errors = [];
const coverage = {};
const measureCoverage = process.env.CTS_UI_COVERAGE === '1';
const aliases = { data: 'data', overviews: 'data', addons: 'addons', disclaimer: 'disclaimer', settings: 'settings', advanced: 'advanced' };
before(async () => {
  if (process.env.CTS_UI_SCREENSHOTS) await mkdir(join(root, 'coverage'), { recursive: true });
  fixtureDir = await mkdtemp(join(tmpdir(), 'cts-browser-'));
  execFileSync('php', [join(root, 'tests/wp/export-admin.php'), fixtureDir], { cwd: root, stdio: 'pipe', timeout: 45000 });
  server = createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://localhost');
      let file;
      if ((url.pathname.startsWith('/plugin/assets/') || url.pathname.startsWith('/plugin/addons/'))) file = join(root, url.pathname.slice('/plugin/'.length));
      else if (url.pathname.startsWith('/wp-core/')) file = join(root, 'tests/wp/vendor/johnpbloch/wordpress-core', url.pathname.slice('/wp-core/'.length));
      else if (url.pathname === '/wp-admin/admin.php' || url.pathname === '/') {
        const page = url.searchParams.get('page') || 'churchtools-suite';
        const section = url.searchParams.get('tab') || aliases[page.replace('churchtools-suite-', '')] || 'dashboard';
        const sub = url.searchParams.get('subtab');
        file = join(fixtureDir, section + (sub ? '--' + sub : '') + '.html');
      } else { res.writeHead(404).end(); return; }
      // Only local fixtures and static test assets are served; there is no PHP execution endpoint.
      if (file.includes('..')) throw new Error('Invalid asset path');
      const contents = await readFile(file);
      res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : file.endsWith('.woff2') ? 'font/woff2' : 'text/html; charset=utf-8');
      res.end(contents);
    } catch { res.writeHead(404).end(); }
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch({ headless: true, ...(process.env.CHROME ? { executablePath: process.env.CHROME } : {}), args: ['--no-sandbox'] });
});
after(async () => {
  try {
    if (measureCoverage && browser) {
      for (const context of browser.contexts()) for (const page of context.pages()) await close(page);
      await reportCoverage();
    }
  } finally {
    await browser?.close();
    await new Promise(resolve => server ? server.close(resolve) : resolve());
    if (fixtureDir) await rm(fixtureDir, { recursive: true, force: true });
  }
});
async function open(section = 'dashboard', sub = '', width = 1440) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  page.setDefaultTimeout(5000);
  if (measureCoverage) await page.coverage.startJSCoverage({ resetOnNavigation: false });
  page.on('pageerror', error => errors.push(`${section}/${sub}: ${error.message}`));
  await page.route('**/*', route => {
    if (new URL(route.request().url()).origin === origin) return route.continue();
    return route.abort();
  });
  await page.goto(`${origin}/wp-admin/admin.php?page=churchtools-suite&tab=${section}&subtab=${sub}`);
  return page;
}

test('all rendered screens initialize both admin scripts without runtime errors or nested shells', async () => {
  for (const name of await readdir(fixtureDir)) {
    const [section, sub = ''] = name.replace('.html', '').split('--');
    const page = await open(section, sub);
    assert.equal(await page.locator('.cts-wrap').count(), 1, name);
    assert.doesNotMatch(await page.locator('.cts-wrap').innerText(), /Berichte/);
    assert.equal(await page.locator('h1').count(), 1, name);
    assert.equal(await page.locator('h1').evaluate(el => el.getBoundingClientRect().width <= 1), true, name);
    assert.equal(await page.locator('.cts-crumbs [aria-current=page]').count(), 1, name);
    assert.equal(await page.locator('#cts-sync-result').count(), 1, name);
    assert.equal(await page.locator('.cts-sub-tabs').count(), 0, name);
    assert.equal(await page.evaluate(() => typeof window.ctsAdmin), 'object', JSON.stringify({name,errors}));
    assert.equal(await page.evaluate(() => typeof window.ctsAdmin.syncNow), 'function');
    const missingLabels = await page.evaluate(() => [...document.querySelectorAll('form input:not([type=hidden]):not([type=submit]), form select, form textarea')].filter(el => !el.labels?.length && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby') && !el.title).map(el => el.id || el.name));
    assert.deepEqual(missingLabels, [], `${name}: fields without labels`);
    await page.setViewportSize({width:390,height:900});
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    assert.ok(overflow < 2, `${name} mobile overflow: ${overflow}px`);
    await close(page);
  }
  assert.deepEqual(errors, []);
});

for (const connected of [false, true]) {
test(`one sync request per click (connected=${connected}); concurrent calls share the pending sync`, async () => {
  const page = await open('dashboard', connected ? 'connected' : '');
  const requests = [];
  let dialogs = 0;
  page.on('dialog', async dialog => { dialogs++; await dialog.accept(); });
  let release;
  const pending = new Promise(resolve => { release = resolve; });
  await page.route('**/admin-ajax.php', async route => {
    requests.push(Object.fromEntries(new URLSearchParams(route.request().postData())));
    await pending;
    await route.fulfill({ json: { success: true, data: { message: '<b>Synced safely</b>' } } });
  });
  await page.locator('#cts-sync-now .dashicons').click();
  await page.waitForFunction(() => document.querySelector('#cts-sync-now').disabled);
  const samePromise = await page.evaluate(() => window.ctsAdmin.syncNow() === window.ctsAdmin.syncNow());
  assert.equal(samePromise, true);
  release();
  await page.waitForFunction(() => !document.querySelector('#cts-sync-now').disabled);
  assert.equal(dialogs, 0, 'the shell sync must have no obsolete inline handler');
  assert.deepEqual(requests, [{ action: 'cts_trigger_manual_sync', nonce: await page.evaluate(() => window.churchtoolsSuite.nonce) }]);
  assert.equal(await page.locator('#cts-sync-result').textContent(), '<b>Synced safely</b>');
  assert.equal(await page.locator('#cts-sync-result b').count(), 0);
  assert.equal(await page.locator('#cts-sync-result').getAttribute('data-state'), 'success');
  await close(page);
});
}

for (const kind of ['server', 'invalid-json', 'network']) {
  test(`sync ${kind} failure is visible and re-enables the action`, async () => {
    const page = await open();
    await page.route('**/admin-ajax.php', route => kind === 'network' ? route.abort() : kind === 'invalid-json' ? route.fulfill({ status: 502, body: 'invalid' }) : route.fulfill({ json: { success: false, data: { message: 'Keine Berechtigung.' } } }));
    await page.locator('#cts-sync-now').click();
    await page.waitForFunction(() => document.querySelector('#cts-sync-result').dataset.state === 'danger');
    assert.equal(await page.locator('#cts-sync-now').isEnabled(), true);
    assert.match(await page.locator('#cts-sync-result').textContent(), kind === 'server' ? /Keine Berechtigung/ : kind === 'invalid-json' ? /Ungültige Antwort/ : /fehlgeschlagen/);
    await close(page);
  });
}

test('mobile content starts below a compact rail; navigation opens, closes and supports Escape', async () => {
  const page = await open('dashboard', '', 390);
  const mainY = await page.locator('.cts-main').evaluate(el => el.getBoundingClientRect().top);
  assert.ok(mainY < 150, `main starts at ${mainY}px`);
  assert.equal(await page.locator('.cts-rail__nav').isVisible(), false);
  const button = page.locator('[data-cts-action="toggle-navigation"]');
  await button.click();
  assert.equal(await button.getAttribute('aria-expanded'), 'true');
  assert.equal(await page.locator('.cts-rail__nav').isVisible(), true);
  await page.keyboard.press('Escape');
  assert.equal(await button.getAttribute('aria-expanded'), 'false');
  assert.equal(await button.evaluate(el => el === document.activeElement), true);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
  if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,'coverage/ui-mobile.png'),fullPage:true,animations:'disabled'});
  assert.equal(overflow, false, JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).map(el => ({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width,right:el.getBoundingClientRect().right})).slice(0,12))));
  await close(page);
});

test('theme and rail preferences persist; card headings remain readable in light and dark', async () => {
  const page = await open();
  for (const theme of ['light', 'dark']) {
    await page.evaluate(theme => localStorage.setItem('ctsAdminTheme', theme), theme);
    await page.reload();
    const colors = await page.locator('.cts-card-header').first().evaluate(el => {
      const header = getComputedStyle(el), title = getComputedStyle(el.querySelector('h3'));
      return { actual: title.color, expected: header.getPropertyValue('--cts-text').trim(), background: header.backgroundColor };
    });
    const expected = await page.evaluate(color => { const el = document.createElement('span'); el.style.color = color; document.body.append(el); const resolved = getComputedStyle(el).color; el.remove(); return resolved; }, colors.expected);
    assert.equal(colors.actual, expected, JSON.stringify(colors));
    assert.equal(await page.locator('#cts-sync-result').count(), 1);
    const details = await page.evaluate(() => {
      const cron = document.querySelector('.cts-cronjob-card');
      const value = document.querySelector('.cts-system-table td:last-child');
      const sample = document.createElement('div');
      sample.style.color = getComputedStyle(value).getPropertyValue('--cts-text');
      sample.style.backgroundColor = getComputedStyle(cron).getPropertyValue('--cts-surface-2');
      cron.append(sample);
      const result = { value:getComputedStyle(value).color, expectedValue:getComputedStyle(sample).color, background:getComputedStyle(cron).backgroundColor, expectedBackground:getComputedStyle(sample).backgroundColor };
      sample.remove();
      return result;
    });
    assert.equal(details.value, details.expectedValue);
    assert.equal(details.background, details.expectedBackground);
    if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,`coverage/ui-${theme}.png`),fullPage:true,animations:'disabled'});
  }
  await page.locator('[data-cts-action="toggle-theme"]').click();
  assert.equal(await page.evaluate(() => localStorage.getItem('ctsAdminTheme')), 'light');
  await page.locator('[data-cts-action="toggle-rail"]').click();
  await page.reload();
  assert.equal(await page.locator('.cts-wrap').evaluate(el => el.classList.contains('cts-rail-collapsed')), true);
  await close(page);
});

test('API authentication choice and calendar selection keep their working controls', async () => {
  const page = await open('settings', 'api');
  await page.locator('#ct_auth_method_token').check();
  assert.equal(await page.locator('[name="ct_token"]').isVisible(), true);
  assert.equal(await page.locator('[name="ct_password"]').isVisible(), false);
  await page.locator('#ct_auth_method_password').check();
  assert.equal(await page.locator('[name="ct_password"]').isVisible(), true);
  await page.goto(`${origin}/wp-admin/admin.php?page=churchtools-suite&tab=settings&subtab=calendars`);
  assert.match(await page.locator('body').textContent(), /Review Calendar/);
  assert.equal(await page.locator('.cts-calendar-checkbox').isChecked(), true);
  await close(page);
});

async function close(page) {
  try {
  if (measureCoverage) {
    for (const entry of await page.coverage.stopJSCoverage()) {
      const url = new URL(entry.url || origin, origin);
      if (!url.pathname.startsWith('/plugin/') || !url.pathname.endsWith('.js')) continue;
      const path = join(root, url.pathname.slice('/plugin/'.length));
      const converter = v8ToIstanbul(path, 0, { source: entry.source });
      await converter.load();
      converter.applyCoverage(entry.functions);
      const result = converter.toIstanbul()[path];
      if (!coverage[path]) coverage[path] = result;
      else for (const key of Object.keys(result.s)) coverage[path].s[key] += result.s[key];
    }
  }
  } finally {
    await page.close();
  }
}

async function reportCoverage() {
  const files = [];
  async function walk(dir) {
    for (const item of await readdir(dir, { withFileTypes: true })) {
      const path = join(dir, item.name);
      if (item.isDirectory()) await walk(path);
      else if (path.endsWith('.js')) files.push(path);
    }
  }
  await walk(join(root, 'assets'));
  await walk(join(root, 'addons'));
  for (const path of files) {
    if (coverage[path]) continue;
    const source = await readFile(path, 'utf8');
    const converter = v8ToIstanbul(path, 0, { source });
    await converter.load();
    converter.applyCoverage([{ functionName: '', isBlockCoverage: true, ranges: [{ startOffset: 0, endOffset: source.length, count: 0 }] }]);
    Object.assign(coverage, converter.toIstanbul());
  }
  await mkdir(join(root, 'coverage'), { recursive: true });
  await writeFile(join(root, 'coverage/ui.json'), JSON.stringify(coverage));
  let covered = 0, total = 0;
  for (const [path, result] of Object.entries(coverage)) {
    const hits = Object.values(result.s);
    covered += hits.filter(count => count > 0).length;
    total += hits.length;
    if (path.endsWith('churchtools-suite-admin-ui.js')) console.log(`Shell JavaScript line coverage: ${(100 * hits.filter(count => count > 0).length / hits.length).toFixed(2)}%`);
  }
  console.log(`All production JavaScript line coverage: ${(100 * covered / total).toFixed(2)}% (${covered}/${total})`);
}

for (const [section, sub, selector, action] of [
  ['advanced', 'manuelle-trigger', '#cts-trigger-manual-sync', 'cts_sync_events'],
  ['advanced', 'manuelle-trigger', '#cts-trigger-keepalive', 'cts_keepalive_ping'],
  ['advanced', 'manuelle-trigger', '#cts-manual-update', 'cts_manual_update'],
  ['settings', 'calendars', '#cts-sync-calendars-btn', 'cts_sync_calendars'],
  ['data', 'posts', '#cts-posts-overview-run-sync', 'cts_posts_sync_run_now'],
]) {
  test(`${selector} uses one registered AJAX action`, async () => {
    const page = await open(section, sub);
    const actions = [];
    await page.route('**/admin-ajax.php', async route => {
      actions.push(new URLSearchParams(route.request().postData()).get('action'));
      await route.fulfill({ json: { success: false, data: { message: 'Test response' } } });
    });
    await page.locator(selector).click();
    await page.waitForFunction(() => document.body.textContent.includes('Test response'));
    assert.deepEqual(actions, [action]);
    assert.equal(await page.locator(selector).isEnabled(), true);
    await close(page);
  });
}

test('cancelled destructive actions send no request; accepting sends one request', async () => {
  const page = await open('advanced', 'reset-cleanup');
  const actions = [];
  await page.route('**/admin-ajax.php', async route => {
    actions.push(new URLSearchParams(route.request().postData()).get('action'));
    await route.fulfill({ json: { success: false, data: { message: 'Test reset denied' } } });
  });
  page.once('dialog', dialog => dialog.dismiss());
  await page.locator('#cts-clear-events').click();
  assert.deepEqual(actions, []);
  let confirms = 0;
  page.on('dialog', async dialog => {
    if (dialog.type() === 'confirm') { confirms++; await dialog.accept(); }
    else await dialog.dismiss();
  });
  await page.locator('#cts-clear-events').click();
  await page.waitForFunction(() => document.querySelector('#cts-clear-events').textContent === 'Erneut löschen');
  assert.equal(confirms, 1);
  assert.deepEqual(actions, ['cts_clear_events']);
  await close(page);
});

test('the public shell helpers report missing AJAX configuration and safe notices', async () => {
  const page = await open();
  const missing = await page.evaluate(async () => {
    delete window.ajaxurl;
    delete window.churchtoolsSuite;
    return window.ctsAdmin.syncNow();
  });
  assert.equal(missing, false);
  assert.match(await page.locator('.cts-notices').textContent(), /AJAX-URL fehlt/);
  await page.evaluate(() => window.ctsAdmin.notify('<script>unsafe</script>', 'success'));
  assert.equal(await page.locator('.cts-notices script').count(), 0);
  assert.equal(await page.locator('.cts-notices .is-success').textContent(), '<script>unsafe</script>');
  await close(page);
});

test('settings use the full workspace on the landing page and every subpage', async () => {
  for (const sub of ['', 'api', 'sync', 'advanced', 'templates', 'posts', 'calendars', 'services']) {
    const page = await open('settings', sub, 1920);
    const sizes = await page.evaluate(() => {
      const main = document.querySelector('.cts-main');
      const card = main.querySelector('.cts-card');
      const styles = getComputedStyle(main);
      return { available:main.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight), card:card?.getBoundingClientRect().width };
    });
    assert.ok(sizes.card >= sizes.available - 2, `${sub || 'landing'}: ${JSON.stringify(sizes)}`);
    if (process.env.CTS_UI_SCREENSHOTS && sub === '') { await page.screenshot({path:join(root,'coverage/settings-desktop.png'),fullPage:true,animations:'disabled'}); await page.setViewportSize({width:390,height:900}); await page.screenshot({path:join(root,'coverage/settings-mobile.png'),fullPage:true,animations:'disabled'}); }
    assert.equal(await page.locator('h1').evaluate(el => el.getBoundingClientRect().width <= 1), true);
    await close(page);
  }
});

for (const method of ['password', 'token']) {
  test(`connection testing sends the current ${method} form, with visible progress and a safe result`, async () => {
    const page = await open('settings');
    await page.locator('#ct_tenant').fill('unsaved-review');
    await page.locator(`#ct_auth_method_${method}`).check();
    if (method === 'password') {
      await page.locator('#ct_username').fill('unsaved@example.org');
      await page.locator('#ct_password').fill('new<password>');
    } else await page.locator('#ct_token').fill('new-token');
    const requests = [];
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    await page.route('**/admin-ajax.php', async route => {
      requests.push(Object.fromEntries(new URLSearchParams(route.request().postData())));
      await pending;
      await route.fulfill({json:{success:true,data:{message:'<b>Verbindung erfolgreich</b>'}}});
    });
    await page.locator('#cts-test-connection').click();
    await page.waitForFunction(() => document.querySelector('#cts-test-connection').getAttribute('aria-busy') === 'true');
    assert.equal(await page.locator('#cts-connection-result').isVisible(), true);
    release();
    await page.waitForFunction(() => !document.querySelector('#cts-test-connection').disabled);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].ct_tenant, 'unsaved-review');
    assert.equal(requests[0].ct_auth_method, method);
    assert.equal(requests[0].action, 'cts_test_connection');
    assert.equal(requests[0].nonce, await page.evaluate(() => window.churchtoolsSuite.nonce));
    assert.equal(requests[0].cts_save_api, undefined);
    if (method === 'password') assert.equal(requests[0].ct_password, 'new<password>');
    else { assert.equal(requests[0].ct_token, 'new-token'); assert.equal(requests[0].ct_password, undefined); }
    assert.equal(await page.locator('#cts-connection-result').textContent(), '<b>Verbindung erfolgreich</b>');
    assert.equal(await page.locator('#cts-connection-result b').count(), 0);
    await close(page);
  });
}

for (const failure of ['nonce', 'invalid-json', 'network', 'credentials']) {
  test(`connection test ${failure} failure stays visible and allows retry`, async () => {
    const page = await open('settings', 'api');
    await page.locator('#ct_tenant').fill('review');
    await page.locator('#ct_auth_method_token').check();
    await page.locator('#ct_token').fill('test-token');
    await page.route('**/admin-ajax.php', route => {
      if (failure === 'network') return route.abort();
      if (failure === 'nonce') return route.fulfill({status:403,body:'-1'});
      if (failure === 'invalid-json') return route.fulfill({status:500,body:'<html>PHP failed</html>'});
      return route.fulfill({json:{success:false,data:{message:'Zugangsdaten prüfen.'}}});
    });
    await page.locator('#cts-test-connection').click();
    await page.waitForFunction(() => document.querySelector('#cts-connection-result').classList.contains('is-danger'));
    assert.equal(await page.locator('#cts-test-connection').isEnabled(), true);
    assert.equal(await page.locator('#cts-connection-result').isVisible(), true);
    assert.equal(await page.locator('#cts-connection-result html').count(), 0);
    await close(page);
  });
}

test('invalid connection fields are marked and do not send requests', async () => {
  const page = await open('settings', 'api');
  let requests = 0;
  await page.route('**/admin-ajax.php', route => { requests++; return route.abort(); });
  await page.locator('#cts-test-connection').click();
  assert.equal(requests, 0);
  assert.equal(await page.locator('#ct_tenant').getAttribute('aria-invalid'), 'true');
  assert.equal(await page.locator('#cts-connection-result').isVisible(), true);
  assert.match(await page.locator('#cts-connection-result').textContent(), /Felder/);
  await page.locator('#ct_tenant').fill('review');
  assert.equal(await page.locator('#ct_tenant').getAttribute('aria-invalid'), null);
  await close(page);
});

test('a stalled connection test times out visibly and permits retry', async () => {
  const page = await open('settings', 'api');
  await page.clock.install();
  await page.locator('#ct_tenant').fill('review');
  await page.locator('#ct_auth_method_token').check();
  await page.locator('#ct_token').fill('test-token');
  await page.route('**/admin-ajax.php', () => {});
  await page.locator('#cts-test-connection').click();
  await page.clock.fastForward(40001);
  await page.waitForFunction(() => document.querySelector('#cts-connection-result').classList.contains('is-danger'));
  assert.match(await page.locator('#cts-connection-result').textContent(), /Zeitüberschreitung/);
  assert.equal(await page.locator('#cts-test-connection').isEnabled(), true);
  await close(page);
});

test('generated preset parameters have accessible names and fit a mobile form', async () => {
  const page = await open('data', 'presets');
  await page.route('**/admin-ajax.php', route => route.fulfill({json:{success:true,data:{calendars:[{id:'review',name:'Review Calendar',color:'#4f46e5'}]}}}));
  await page.locator('#cts-create-tab').click();
  await page.locator('#preset-shortcode-tag').selectOption('cts_list');
  await page.waitForFunction(() => document.querySelector('#preset-params-table input:not([type=hidden])'));
  const section = page.locator('#preset-params-table th[role=button]').nth(1);
  await section.focus();
  await page.keyboard.press('Enter');
  assert.equal(await section.getAttribute('aria-expanded'), 'true');
  await page.keyboard.press('Space');
  assert.equal(await section.getAttribute('aria-expanded'), 'false');
  assert.equal(await section.isVisible(), true);
  const missing = await page.locator('#preset-params-table').evaluate(table => [...table.querySelectorAll('input:not([type=hidden]), select, textarea')].filter(el => !el.labels?.length && !el.getAttribute('aria-label')).map(el => el.name));
  assert.deepEqual(missing, []);
  await page.setViewportSize({width:390,height:900});
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  await close(page);
});

test('connection testing reuses a saved password without putting it in the browser', async () => {
  const page = await open('settings', 'saved-api');
  assert.equal(await page.locator('#ct_password').inputValue(), '');
  assert.match(await page.locator('#cts-password-hint').textContent(), /gespeichert/);
  assert.equal((await page.content()).includes('private-fixture-password'), false);
  const requests = [];
  await page.route('**/admin-ajax.php', async route => {
    requests.push(Object.fromEntries(new URLSearchParams(route.request().postData())));
    await route.fulfill({json:{success:true,data:{message:'Gespeicherter Zugang geprüft.'}}});
  });
  await page.locator('#cts-test-connection').click();
  await page.waitForFunction(() => document.querySelector('#cts-connection-result').classList.contains('is-success'));
  assert.equal(requests.length, 1);
  assert.equal(requests[0].ct_password, '');
  assert.equal(requests[0].ct_username, 'review@example.org');
  await close(page);
});

test('overview filters align and the readable table scrolls within its panel', async () => {
  const page = await open('data', 'events', 1440);
  const inputs = await page.locator('.cts-filter-grid input, .cts-filter-grid select').evaluateAll(els => els.map(el => {
    const r = el.getBoundingClientRect(); return {top:r.top, height:r.height, width:r.width};
  }));
  assert.equal(inputs.length, 3);
  assert.ok(Math.max(...inputs.map(r => r.top)) - Math.min(...inputs.map(r => r.top)) < 2);
  assert.ok(inputs.every(r => r.height >= 40 && r.width >= 190));
  assert.equal(await page.locator('.cts-events-table th').count(), 7);
  assert.equal(await page.locator('.cts-event-date').evaluate(el => getComputedStyle(el).display), 'table-cell');
  assert.ok((await page.locator('.cts-type-badge').boundingBox()).width > 40);
  assert.match(await page.locator('.cts-event-title').textContent(), /useful event description/);
  assert.ok((await page.locator('.cts-event-title').boundingBox()).width >= 240);
  if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,'coverage/overview-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:900});
  assert.ok(await page.locator('.cts-table-wrapper').evaluate(el => el.scrollWidth > el.clientWidth));
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,'coverage/overview-mobile.png'),fullPage:true});
  await close(page);
});

test('sync button icons are centered and hints have comfortable padding', async () => {
  const page = await open('sync');
  for (const id of ['cts-sync-events-btn','cts-force-full-sync-btn']) {
    const metrics = await page.locator('#'+id).evaluate(button => {
      const b = button.getBoundingClientRect(), icon = button.querySelector('.dashicons').getBoundingClientRect();
      return { delta: Math.abs((b.top+b.height/2)-(icon.top+icon.height/2)), height:b.height, iconHeight:icon.height };
    });
    assert.ok(metrics.delta < 2, JSON.stringify(metrics));
    assert.ok(metrics.height >= 36 && metrics.iconHeight === 18);
  }
  if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,'coverage/sync-desktop.png'),fullPage:true});
  await close(page);
  const calendars = await open('settings','calendars');
  assert.equal(await calendars.getByText('Vorlagenbilder',{exact:true}).count(),0);
  assert.equal(await calendars.locator('.cts-calendar-template-select').count(),0);
  assert.ok(await calendars.locator('.cts-info').evaluate(el => parseFloat(getComputedStyle(el).paddingTop) >= 12));
  if (process.env.CTS_UI_SCREENSHOTS) await calendars.screenshot({path:join(root,'coverage/calendars-desktop.png'),fullPage:true});
  await close(calendars);
});

test('inactive Posts addon provides a useful settings page', async () => {
  const page = await open('settings','posts-inactive');
  assert.match(await page.locator('.cts-posts-unavailable').textContent(), /nicht aktiv/);
  assert.equal(await page.locator('button[name=cts_save_posts]').count(),0);
  assert.equal(await page.locator('.cts-posts-unavailable a[href$="plugins.php"]').count(),1);
  await close(page);
});

test('diagnostics exports the displayed local report as JSON', async () => {
  const page = await open('advanced');
  const displayed = JSON.parse(await page.locator('#cts-diagnostics-data').textContent());
  assert.equal(await page.locator('.cts-diagnostics-grid .cts-card').count(),5);
  const download = page.waitForEvent('download');
  await page.locator('#cts-diagnostics-export').click();
  const file = await download;
  assert.equal(file.suggestedFilename(),'churchtools-suite-diagnostics.json');
  assert.deepEqual(JSON.parse(await readFile(await file.path(),'utf8')),displayed);
  assert.equal('username' in displayed.connection,false);
  await page.evaluate(() => scrollTo(0,0));
  if (process.env.CTS_UI_SCREENSHOTS) await page.screenshot({path:join(root,'coverage/diagnostics-desktop.png'),fullPage:true});
  await close(page);
});


test('event filters navigate with all criteria and retain the shared table layout', async () => {
  const page = await open('data','events');
  const ajax = [];
  page.on('request', request => { if (request.url().includes('admin-ajax.php')) ajax.push(request.url()); });
  await page.locator('#cts-events-from').fill('2030-06-01');
  await page.locator('#cts-events-to').fill('2030-06-30');
  await page.locator('#cts-events-calendar').selectOption('review');
  await Promise.all([
    page.waitForURL(url => url.searchParams.get('from') === '2030-06-01'),
    page.locator('.cts-filter-actions button[type=submit]').click(),
  ]);
  const url = new URL(page.url());
  assert.equal(url.searchParams.get('to'),'2030-06-30');
  assert.equal(url.searchParams.get('calendar_id'),'review');
  assert.equal(url.searchParams.get('tab'),'data');
  assert.equal(await page.locator('.cts-events-table th').count(),7);
  assert.deepEqual(ajax,[]);
  await close(page);
});


test('diagnostic tables have headers and settings toggles expose one visible label', async () => {
  for (const [section, sub] of [['dashboard',''],['advanced',''],['settings','sync'],['settings','advanced']]) {
    const page = await open(section, sub);
    const unnamedToggles = await page.locator('.cts-toggle input').evaluateAll(inputs => inputs.filter(input => ![...input.labels].some(label => label.textContent.trim())).map(input => input.id));
    assert.deepEqual(unnamedToggles, [], `${section}/${sub}: unnamed toggle`);
    const tables = await page.locator('.cts-system-table, .cts-debug-table').evaluateAll(tables => tables.filter(table => !table.querySelector('th')).length);
    assert.equal(tables,0,`${section}/${sub}: table without headers`);
    await close(page);
  }
});
