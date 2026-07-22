import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";
import * as cheerio from "cheerio";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const sourcePath = path.join(root, "assets", "js", "content.js");
const outputPath = path.join(
  root,
  "wordpress",
  "wp-content",
  "plugins",
  "cywater-core",
  "data",
  "seed.json"
);
const source = await readFile(sourcePath, "utf8");
const sandbox = { window: {} };

vm.runInNewContext(source, sandbox, {
  filename: sourcePath,
  timeout: 5_000,
});

const content = sandbox.window.CYWaterContent;
if (!content?.ARTICLES || !content?.EVENTS || !content?.AWARDS) {
  throw new Error("The static content registry did not expose the expected data.");
}

async function readPage(relativePath, contentSelector) {
  const html = await readFile(path.join(root, relativePath), "utf8");
  const $ = cheerio.load(html);
  return {
    title: $(".page-hero h1").first().text().trim(),
    eyebrow: $(".page-hero .eyebrow").first().text().trim(),
    lead: $(".page-hero .lead").first().text().trim(),
    content: $(contentSelector).first().html()?.trim() || "",
  };
}

const pages = {
  about: await readPage("about/index.html", ".about-intro-grid .stack"),
  bylaws: await readPage("about/bylaws.html", ".bylaws-layout .prose"),
  membership: await readPage("membership/index.html", ".page-hero"),
  contact: await readPage("contact/index.html", ".contact-note"),
};

const payload = {
  schemaVersion: 1,
  generatedFrom: "assets/js/content.js",
  articles: content.ARTICLES,
  events: content.EVENTS,
  awards: content.AWARDS,
  newsOrder: content.NEWS_LIST || [],
  eventOrder: content.EVENT_LIST || [],
  pages,
};

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(payload, null, 2)}\n`, "utf8");
console.log(`Exported WordPress seed data to ${path.relative(root, outputPath)}`);
