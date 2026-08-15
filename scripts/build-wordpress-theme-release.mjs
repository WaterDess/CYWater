import { createHash } from "node:crypto";
import { mkdir, readFile, readdir, rm, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import AdmZip from "adm-zip";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const source = path.join(root, "wordpress", "wp-content", "themes", "cywater");
const style = await readFile(path.join(source, "style.css"), "utf8");
const version = style.match(/^Version:\s*([^\r\n]+)$/m)?.[1]?.trim();

if (!version) {
  throw new Error("Unable to read the CYWater theme version from style.css.");
}

await stat(source);

const distRoot = path.join(root, "dist");
const dist = path.join(distRoot, `wordpress-theme-${version}`);
await rm(dist, { recursive: true, force: true });
await mkdir(dist, { recursive: true });

const filename = `cywater-${version}.zip`;
const target = path.join(dist, filename);
const zip = new AdmZip();
zip.addLocalFolder(source, "cywater");
zip.writeZip(target);

const archive = new AdmZip(target);
const entries = archive.getEntries().map((entry) => entry.entryName);
if (!entries.includes("cywater/style.css")) {
  throw new Error("Theme archive is not installable: cywater/style.css is missing.");
}
if (entries.some((entry) => entry.includes("/placeholders/") || entry.includes("wordpress-theme-"))) {
  throw new Error("Theme archive contains legacy or nested release files.");
}

const bytes = await readFile(target);
const sha256 = createHash("sha256").update(bytes).digest("hex");
const manifest = {
  release: version,
  generatedAt: new Date().toISOString(),
  source: "wordpress-integration",
  package: {
    type: "theme",
    slug: "cywater",
    file: filename,
    bytes: bytes.length,
    sha256,
    entries: entries.length,
  },
};

const readme = `# CYWater WordPress theme ${version}

This is a theme-only update. The CYWater plugins and Paid Memberships Pro do not need to be replaced.

1. In WordPress, open Appearance > Themes > Add New > Upload Theme.
2. Upload \`${filename}\` and confirm replacement of the installed CYWater theme.
3. Keep the CYWater theme active.
4. Purge LiteSpeed Cache and Hostinger cache, then hard-refresh the Bylaws and Membership pages.

Do not run a force content import. This update changes presentation only and does not alter Events, News, Awards, members, or payment settings.
`;

await writeFile(path.join(dist, "README.md"), readme, "utf8");
await writeFile(path.join(dist, "manifest.json"), `${JSON.stringify(manifest, null, 2)}\n`, "utf8");

const outputNames = (await readdir(dist)).sort();
const expectedNames = [filename, "README.md", "manifest.json"].sort();
if (JSON.stringify(outputNames) !== JSON.stringify(expectedNames)) {
  throw new Error(`Theme release directory contains unexpected files: ${outputNames.join(", ")}`);
}

console.log(`Built clean CYWater theme ${version} in ${path.relative(root, dist)}.`);
console.log(`${filename}: ${bytes.length} bytes`);
console.log(`SHA256: ${sha256}`);
