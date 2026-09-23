/* Read-only public acceptance after deployment; never submits a form or signs in. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.CYWATER_NODE_MODULES + '/playwright');

const base = new URL(process.env.CYWATER_PUBLIC_BASE_URL || 'https://cywater.org/');
assert.ok(['cywater.org', 'staging.cywater.org'].includes(base.hostname), 'Unexpected target host');
assert.equal(base.protocol, 'https:', 'Use the actual HTTPS site');
assert.ok(!base.username && !base.password, 'Do not place credentials in the target URL');
const archiveUrl = new URL('/awards/', base).href;
// Canonical rewrite source: CYWater_Content_Types, cyw_award rewrite slug = awards.
const expectedDetailPath = '/awards/best-paper-award-2026/';
const expectedHistory = Number(process.env.CYWATER_EXPECTED_HISTORICAL_AWARDS || 14);
const output = path.resolve('artifacts');
const results = [];
fs.mkdirSync(output, { recursive: true });

async function assertPageFits(page, label) {
  const dimensions = await page.evaluate(() => ({
    viewport: innerWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }));
  assert.ok(dimensions.document <= dimensions.viewport + 1 && dimensions.body <= dimensions.viewport + 1,
    `${label}: horizontal page overflow ${JSON.stringify(dimensions)}`);
}

function assertUncached(response, label) {
  assert.ok(response, `${label}: missing document response`);
  assert.equal(response.status(), 200, `${label}: HTTP ${response.status()}`);
  const headers = response.headers();
  assert.match(headers['cache-control'] || '', /(?:no-store|no-cache)/i,
    `${label}: dynamic application state is not explicitly uncached`);
  assert.doesNotMatch(headers['x-litespeed-cache'] || '', /\bhit\b/i,
    `${label}: anonymous application page came from LiteSpeed cache`);
  return { cacheControl: headers['cache-control'], liteSpeedCache: headers['x-litespeed-cache'] || null };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [1440, 390]) {
      for (const javaScriptEnabled of [true, false]) {
        const context = await browser.newContext({
          viewport: { width, height: 1000 }, javaScriptEnabled,
          reducedMotion: 'reduce', locale: 'en-US',
        });
        const page = await context.newPage();
        page.setDefaultTimeout(15000);
        try {
          const archiveResponse = await page.goto(archiveUrl, { waitUntil: 'load', timeout: 45000 });
          assert.equal(archiveResponse.status(), 200, 'Awards archive unavailable');
          const current = page.locator('section[aria-labelledby="current-award-cycles"]');
          assert.equal(await current.count(), 1, 'Missing or duplicate Current award cycle section');
          assert.equal(await current.locator('article.award-year').count(), 1, 'Expected one current cycle');
          assert.equal(await page.locator('#awards-yearbook > article.award-year').count(), expectedHistory,
            'Historical Award count changed');
          assert.equal(await page.locator('#awards-yearbook #award-2026').count(), 0,
            'Unannounced 2026 cycle is incorrectly shown as a historical result');
          const historyYears = await page.locator('#awards-yearbook .award-year-label').allTextContents();
          assert.deepEqual(historyYears.map(v => v.trim()),
            Array.from({ length: expectedHistory }, (_, index) => String(2025 - index)),
            'Historical Award years/order changed');
          const link = current.getByRole('link', { name: 'View details and application', exact: true });
          assert.equal(await link.count(), 1, 'Current cycle application link missing');
          const detailUrl = new URL(await link.getAttribute('href'), archiveUrl);
          assert.equal(detailUrl.origin, base.origin, 'Application link leaves the intended site');
          assert.equal(detailUrl.pathname, expectedDetailPath, 'Unexpected application permalink');
          assert.match(await current.innerText(), /Applications are not open yet\./);
          await assertPageFits(page, `Awards ${width}/${javaScriptEnabled}`);
          await page.screenshot({ path: path.join(output, `best-paper-live-awards-${width}-${javaScriptEnabled ? 'js' : 'nojs'}.png`) });

          const response = await page.goto(detailUrl.href, { waitUntil: 'load', timeout: 45000 });
          const cache = assertUncached(response, 'Award detail');
          const module = page.locator('section.cywater-best-paper');
          assert.equal(await module.count(), 1, 'Award module must render exactly once');
          assert.match(await module.innerText(), /Applications are not open yet/);
          assert.match(await module.innerText(), /Planned timeline/);
          assert.match(await module.innerText(), /35 years old or younger/);
          assert.equal(await module.locator('form').count(), 0, 'Closed anonymous cycle exposed an application form');
          assert.equal(await module.locator('input,button[type="submit"]').count(), 0,
            'Closed anonymous cycle exposed applicant controls');
          assert.equal(await page.locator('[name="first_name"],[name="dob"]').count(), 0,
            'Anonymous page contains private applicant fields');
          assert.doesNotMatch(await page.locator('main').innerText(), /There has been a critical error/i);
          const moduleStyle = await module.evaluate(element => ({
            display: getComputedStyle(element).display,
            visibility: getComputedStyle(element).visibility,
            opacity: getComputedStyle(element).opacity,
          }));
          assert.notEqual(moduleStyle.display, 'none');
          assert.notEqual(moduleStyle.visibility, 'hidden');
          assert.ok(Number(moduleStyle.opacity) > 0, 'Application information hidden by animation');
          await assertPageFits(page, `Detail ${width}/${javaScriptEnabled}`);
          await page.screenshot({ path: path.join(output, `best-paper-live-detail-${width}-${javaScriptEnabled ? 'js' : 'nojs'}.png`), fullPage: true });
          // A second canonical request detects a newly populated shared cache.
          assertUncached(await page.reload({ waitUntil: 'load', timeout: 45000 }), 'Reloaded Award detail');
          assert.equal(await page.locator('section.cywater-best-paper').count(), 1);
          results.push({ width, javaScriptEnabled, currentCycles: 1, historicalAwards: expectedHistory,
            moduleCount: 1, intakeOpen: false, noOverflow: true, ...cache });
          console.log(JSON.stringify(results.at(-1)));
        } finally {
          await context.close();
        }
      }
    }
    fs.writeFileSync(path.join(output, 'best-paper-live-smoke.json'), JSON.stringify({
      verifiedAt: new Date().toISOString(), archiveUrl, detailPath: expectedDetailPath, results,
    }, null, 2) + '\n');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
