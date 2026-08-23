#!/usr/bin/env node

const origin = "https://cywater.org";
const residuePatterns = new Map([
  ["staging domain", /staging\.cywater\.org/i],
  ["Sandbox/test payment copy", /Stripe Sandbox|Sandbox Payment Test|Test Sandbox payment|No real charge/i],
  ["private Live acceptance fixture", /Live Payment Acceptance Test/i],
  ["payment Test Mode", /Test Mode|test account with test data/i],
  ["temporary Hostinger domain", /hostingersite\.com|lightgoldenrodyellow-cobra/i],
  ["local development URL", /localhost|127\.0\.0\.1/i],
  ["QA identity", /example\.invalid|cyw_[a-z0-9_]*qa/i],
]);

function xmlLocations(xml) {
  return [...xml.matchAll(/<loc>(.*?)<\/loc>/gis)].map((match) =>
    match[1].replaceAll("&amp;", "&").trim()
  );
}

async function request(url) {
  const response = await fetch(url, {
    redirect: "follow",
    headers: { "cache-control": "no-cache", "user-agent": "CYWater production residue audit" },
  });
  return { url, response, body: await response.text() };
}

const sitemap = await request(`${origin}/wp-sitemap.xml`);
if (!sitemap.response.ok) {
  throw new Error(`Root sitemap returned HTTP ${sitemap.response.status}.`);
}

const childMaps = await Promise.all(xmlLocations(sitemap.body).map(request));
const urls = new Set([`${origin}/`]);
for (const map of childMaps) {
  if (!map.response.ok) {
    throw new Error(`${map.url} returned HTTP ${map.response.status}.`);
  }
  for (const url of xmlLocations(map.body)) urls.add(url);
}

for (const path of [
  "/membership/",
  "/member-login/",
  "/member-register/",
  "/member-profile/",
  "/account/",
  "/forum/",
  "/forum-workspace/",
  "/events/",
  "/board/",
  "/bylaws/",
  "/contact/",
]) {
  urls.add(`${origin}${path}`);
}

const queue = [...urls];
const hits = [];
const failures = [];
const worker = async () => {
  while (queue.length) {
    const url = queue.shift();
    try {
      const page = await request(url);
      if (!page.response.ok) {
        failures.push(`${url} -> HTTP ${page.response.status}`);
        continue;
      }
      for (const [label, pattern] of residuePatterns) {
        if (pattern.test(page.body)) hits.push(`${url} -> ${label}`);
      }
    } catch (error) {
      failures.push(`${url} -> ${error.message}`);
    }
  }
};

await Promise.all(Array.from({ length: 8 }, worker));

console.log(`Public URLs checked: ${urls.size}`);
console.log(`Residue hits: ${hits.length}`);
for (const hit of hits) console.log(`- ${hit}`);
console.log(`Fetch failures: ${failures.length}`);
for (const failure of failures) console.log(`- ${failure}`);

if (hits.length || failures.length) process.exitCode = 1;
