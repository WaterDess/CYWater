/* Anonymous, read-only HTTP/browser acceptance. No authentication or form submission. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.CYWATER_NODE_MODULES + '/playwright');

const base = new URL(process.env.CYWATER_PUBLIC_BASE_URL || 'https://cywater.org/');
assert.ok(['cywater.org', 'staging.cywater.org'].includes(base.hostname), 'Unexpected target host');
assert.equal(base.protocol, 'https:');
assert.ok(!base.username && !base.password, 'Never place credentials in the target URL');
const previewPath = '/best-paper-2026-preview/';
const previewUrl = new URL(previewPath, base).href;
const officialUrl = new URL('/awards/best-paper-award-2026/', base).href;
const output = path.resolve('artifacts');
fs.mkdirSync(output, { recursive: true });
const results = [];

function checkHeaders(response) {
  assert.ok(response, 'Missing preview response');
  assert.equal(response.status(), 200, 'Address-only preview is not anonymously accessible');
  const headers = response.headers();
  assert.match(headers['cache-control'] || '', /(?:no-store|no-cache)/i, 'Preview is cacheable');
  assert.doesNotMatch(headers['x-litespeed-cache'] || '', /\bhit\b/i, 'Preview served from shared cache');
  assert.match(headers['x-robots-tag'] || '', /\bnoindex\b/i, 'Preview must carry an HTTP noindex directive');
  return { cacheControl: headers['cache-control'], robots: headers['x-robots-tag'] };
}

async function noPreviewLinks(page, label) {
  const links = await page.locator('a[href]').evaluateAll(nodes => nodes.map(node => node.href));
  assert.ok(!links.some(href => new URL(href).pathname.replace(/\/$/, '') === previewPath.replace(/\/$/, '')),
    `${label} links to the unlisted preview`);
}

async function fitsViewport(page, label) {
  const size = await page.evaluate(() => ({ width: innerWidth, document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth }));
  assert.ok(size.document <= size.width + 1 && size.body <= size.width + 1,
    `${label}: horizontal overflow ${JSON.stringify(size)}`);
}

async function inspectPublicDiscovery(browser) {
  const context = await browser.newContext({ javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    for (const route of ['/', '/awards/', '/?s=best-paper-2026-preview', '/?s=Best+Paper+2026+application+preview']) {
      const response = await page.goto(new URL(route, base).href, { waitUntil: 'load', timeout: 45000 });
      assert.ok(response && response.status() === 200, `Public discovery route failed: ${route}`);
      await noPreviewLinks(page, route);
    }
    const index = await context.request.get(new URL('/wp-sitemap.xml', base).href, { timeout: 30000 });
    assert.ok([200, 404].includes(index.status()), `Unexpected sitemap status ${index.status()}`);
    let sitemapCount = 0;
    if (index.status() === 200) {
      const xml = await index.text();
      assert.ok(!xml.includes(previewPath), 'Preview is in sitemap index');
      const locations = [...xml.matchAll(/<loc>\s*([^<]+)\s*<\/loc>/g)].map(match => match[1].replace(/&amp;/g, '&'));
      const pageMaps = locations.map(value => new URL(value)).filter(url =>
        url.origin === base.origin && /^\/wp-sitemap-posts-page-\d+\.xml$/.test(url.pathname));
      assert.ok(pageMaps.length > 0, 'No WordPress page sitemap found; page exclusion was not verified');
      assert.ok(pageMaps.length <= 10, 'Unexpectedly many page sitemaps; inspect manually');
      for (const url of pageMaps) {
        const response = await context.request.get(url.href, { timeout: 30000 });
        assert.equal(response.status(), 200, `Page sitemap failed: ${url.pathname}`);
        assert.ok(!(await response.text()).includes(previewPath), 'Preview is discoverable in a page sitemap');
        ++sitemapCount;
      }
    }
    return { homeAwardsSearchUnlisted: true, sitemapStatus: index.status(), pageSitemapsChecked: sitemapCount };
  } finally { await context.close(); }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const discovery = await inspectPublicDiscovery(browser);
    for (const width of [1440, 390]) for (const javaScriptEnabled of [true, false]) {
      const context = await browser.newContext({ viewport: { width, height: 1000 }, javaScriptEnabled,
        reducedMotion: 'reduce', locale: 'en-US' });
      const blockedWrites = [];
      // Defense in depth: even a regression cannot send an upload or state-changing request.
      await context.route('**/*', route => {
        const request = route.request();
        if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
          blockedWrites.push({ method: request.method(), path: new URL(request.url()).pathname });
          return route.abort('blockedbyclient');
        }
        return route.continue();
      });
      const page = await context.newPage();
      page.setDefaultTimeout(15000);
      try {
        const response = await page.goto(previewUrl, { waitUntil: 'load', timeout: 45000 });
        const headers = checkHeaders(response);
        assert.equal(new URL(page.url()).pathname, previewPath, 'Preview redirected away from its address');
        const module = page.locator('section.cywater-best-paper');
        const preview = page.locator('[data-cywater-best-paper-preview]');
        assert.equal(await module.count(), 1);
        assert.equal(await preview.count(), 1);
        for (const asset of [
          { selector: '#cywater-best-paper-css', attribute: 'href', type: 'css', mime: /text\/css/i },
          { selector: '#cywater-best-paper-js', attribute: 'src', type: 'js', mime: /(?:java|ecma)script/i },
        ]) {
          const assetTag = page.locator(asset.selector);
          assert.equal(await assetTag.count(), 1, `Best Paper ${asset.type} tag missing or duplicated`);
          const assetUrl = new URL(await assetTag.getAttribute(asset.attribute), previewUrl);
          assert.equal(assetUrl.origin, base.origin, `Best Paper ${asset.type} must load from the site`);
          const assetResponse = await context.request.get(assetUrl.href, { timeout: 30000 });
          assert.equal(assetResponse.status(), 200,
            `Best Paper ${asset.type} failed to load (including possible cached404)`);
          assert.match(assetResponse.headers()['content-type'] || '', asset.mime,
            `Best Paper ${asset.type} response is not the expected asset type`);
        }
        const styles = await module.evaluate(element => {
          const block = getComputedStyle(element);
          const fieldsets = [...element.querySelectorAll('fieldset')].map(fieldset => {
            const style = getComputedStyle(fieldset);
            return { minWidth: style.minWidth, padding: style.padding };
          });
          const fileInputs = [...element.querySelectorAll('input[type=file]')].map(input => {
            const rect = input.getBoundingClientRect();
            return { width: rect.width, height: rect.height };
          });
          return { padding: parseFloat(block.paddingTop), border: parseFloat(block.borderTopWidth), fieldsets, fileInputs };
        });
        assert.ok(styles.padding > 0 && styles.border > 0,
          'Best Paper card stylesheet did not apply (unstyled desktop can otherwise look structurally valid)');
        assert.equal(styles.fieldsets.length, 4);
        for (const fieldset of styles.fieldsets) {
          assert.equal(parseFloat(fieldset.minWidth), 0, 'Fieldset retains browser min-content width');
          assert.equal(parseFloat(fieldset.padding), 0, 'Fieldset retains browser-default padding');
        }
        assert.equal(styles.fileInputs.length, 2);
        for (const input of styles.fileInputs) {
          assert.ok(Math.abs(input.width - 1) < 0.1 && Math.abs(input.height - 1) < 0.1,
            'Native file input was not visually reduced to the styled Choose File control');
        }
        assert.match(await module.innerText(), /no application will be submitted/i);
        assert.equal(await module.locator('form').count(), 0, 'Preview contains an actual form');
        assert.equal(await module.locator('[action],[formaction],input[type=hidden]').count(), 0,
          'Preview contains an action, nonce, or hidden submission controls');
        assert.equal(await module.locator('button[type=submit]').count(), 0);
        const submit = preview.getByRole('button', { name: 'Submit application', exact: true });
        assert.equal(await submit.getAttribute('type'), 'button');
        assert.equal(await submit.isDisabled(), true);
        for (const name of ['first_name', 'last_name', 'email', 'institution', 'dob']) {
          const input = preview.locator(`[name="${name}"]`);
          assert.equal(await input.inputValue(), '', `Guest ${name} leaked a stored identity`);
          assert.equal(await input.isEnabled(), true, `${name} remains disabled in interactive preview`);
        }
        const files = preview.locator('input[type=file]');
        assert.equal(await files.count(), 2, 'Expected paper and CV file pickers');
        for (let index = 0; index < 2; ++index) assert.equal(await files.nth(index).isEnabled(), true);
        assert.equal(await module.locator('a[href*="application_id="]').count(), 0,
          'Anonymous preview contains protected application links');
        await noPreviewLinks(page, 'Preview navigation');
        await fitsViewport(page, `Preview ${width}/${javaScriptEnabled}`);
        await page.screenshot({ path: path.join(output, `best-paper-preview-live-${width}-${javaScriptEnabled ? 'js' : 'nojs'}.png`), fullPage: true });

        const attemptsBefore = blockedWrites.length;
        const applicationRequests = [];
        const trackApplicationRequest = request => {
          const url = new URL(request.url());
          if (url.origin === base.origin && (request.isNavigationRequest() || ['xhr', 'fetch'].includes(request.resourceType()))) {
            applicationRequests.push({ method: request.method(), path: url.pathname });
          }
        };
        page.on('request', trackApplicationRequest);
        await files.first().setInputFiles({ name: 'preview-local-only.pdf', mimeType: 'application/pdf',
          buffer: Buffer.from('%PDF-1.4\n% Synthetic local preview only\n%%EOF') });
        assert.equal(await files.first().evaluate(input => input.files[0]?.name), 'preview-local-only.pdf');
        await preview.locator('[name="first_name"]').fill('Preview');
        await preview.locator('[name="first_name"]').press('Enter');
        // Short observation window is intentional: detect unwanted automatic/Enter submission.
        await page.waitForTimeout(600);
        page.off('request', trackApplicationRequest);
        assert.equal(page.url(), previewUrl, 'Enter navigated away from preview');
        assert.equal(blockedWrites.length, attemptsBefore, 'Preview attempted an upload/write (blocked by test)');
        assert.deepEqual(applicationRequests, [], 'Choosing a file or pressing Enter triggered an application request');
        await files.first().setInputFiles([]);
        await fitsViewport(page, `Interactive preview ${width}/${javaScriptEnabled}`);
        checkHeaders(await page.reload({ waitUntil: 'load', timeout: 45000 }));
        assert.equal(await page.locator('[data-cywater-best-paper-preview] [name="first_name"]').inputValue(), '',
          'Preview values persisted after reload');

        const official = await page.goto(officialUrl, { waitUntil: 'load', timeout: 45000 });
        assert.equal(official.status(), 200);
        assert.match(await page.locator('section.cywater-best-paper').innerText(), /Applications are not open yet/);
        assert.equal(await page.locator('section.cywater-best-paper form').count(), 0,
          'Preview deployment accidentally opened official intake');
        assert.equal(await page.locator('[data-cywater-best-paper-preview]').count(), 0,
          'Preview mode leaked onto official Award page');
        await noPreviewLinks(page, 'Official Award page');
        results.push({ width, javaScriptEnabled, anonymousAccess: true, blankGuestIdentity: true,
          localFilePickers: 2, hasForm: false, submitDisabled: true, interactionRequests: 0,
          assetsHttp200: true, styledCard: true, fieldsetMinWidthZero: true,
          noOverflow: true, officialIntakeClosed: true, ...headers });
        console.log(JSON.stringify(results.at(-1)));
      } finally { await context.close(); }
    }
    fs.writeFileSync(path.join(output, 'best-paper-preview-live-smoke.json'), JSON.stringify({
      verifiedAt: new Date().toISOString(), previewUrl, discovery, results,
    }, null, 2) + '\n');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
