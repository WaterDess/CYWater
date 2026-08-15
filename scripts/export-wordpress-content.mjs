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
  home: await readPage("index.html", ".hero"),
  about: await readPage("about/index.html", ".about-intro-grid .stack"),
  board: await readPage("about/board.html", ".board-status"),
  bylaws: await readPage("about/bylaws.html", ".bylaws-layout .prose"),
  membership: await readPage("membership/index.html", ".page-hero"),
  contact: await readPage("contact/index.html", ".contact-note"),
};

// WordPress treats the December 2020 virtual session as part of the Best Paper
// Award record, not as an Annual Gathering. Keep the public static prototype
// untouched while normalizing the editable WordPress content model.
const articles = structuredClone(content.ARTICLES);
const events = structuredClone(content.EVENTS);
const awards = structuredClone(content.AWARDS);
const newsOrder = structuredClone(content.NEWS_LIST || []);
const eventOrder = structuredClone(content.EVENT_LIST || []).filter(
  ({ id }) => id !== "annual-gathering-2020"
);

delete events["annual-gathering-2020"];

const award2020 = awards.find(({ year }) => String(year) === "2020");
if (award2020) {
  award2020.ceremony = {
    title: "CYWater Best Paper Award Ceremony — Online 2020",
    date: "December 18, 2020",
    location: "Online",
    image: "gatherings/2020-cover.jpg",
    imageAlt: "Participants in the online 2020 CYWater Best Paper Award Ceremony",
    lead: "The 2020 Best Paper Award Ceremony was held online, with the recognized authors presenting their work.",
  };
}

const award2020News = newsOrder.find(({ id }) => id === "bpa-2020-result");
if (award2020News?.alt) {
  award2020News.alt = "Participants in the online 2020 CYWater Best Paper Award Ceremony";
}

const board = [
  { role: "President", personName: "Qiuhong Tang" },
  { role: "President-Elect", personName: "Lifeng Luo" },
  { role: "Treasurer", personName: "Zhenxing Zhang" },
  { role: "Directors-at-Large", personName: "Ming Pan, Chaopeng Shen" },
  { role: "Executive Director", personName: "Vacant (N/A)" },
];

const payload = {
  schemaVersion: 1,
  seedRevision: 6,
  generatedFrom: "assets/js/content.js",
  articles,
  events,
  awards,
  newsOrder,
  eventOrder,
  board,
  pages,
};

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(payload, null, 2)}\n`, "utf8");
console.log(`Exported WordPress seed data to ${path.relative(root, outputPath)}`);
