import { createHash } from "node:crypto";
import { mkdir, readFile, readdir, rm, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import AdmZip from "adm-zip";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const packageJson = JSON.parse(await readFile(path.join(root, "package.json"), "utf8"));
const release = packageJson.version;
const distRoot = path.join(root, "dist");
const dist = path.join(distRoot, `wordpress-release-${release}`);
const wpContent = path.join(root, "wordpress", "wp-content");
const packages = [
  {
    type: "theme",
    slug: "cywater",
    source: path.join(wpContent, "themes", "cywater"),
    header: "style.css",
  },
  {
    type: "plugin",
    slug: "cywater-core",
    source: path.join(wpContent, "plugins", "cywater-core"),
    header: "cywater-core.php",
  },
  {
    type: "plugin",
    slug: "cywater-membership",
    source: path.join(wpContent, "plugins", "cywater-membership"),
    header: "cywater-membership.php",
  },
  {
    type: "plugin",
    slug: "cywater-environment",
    source: path.join(wpContent, "plugins", "cywater-environment"),
    header: "cywater-environment.php",
  },
  {
    type: "plugin",
    slug: "cywater-forum",
    source: path.join(wpContent, "plugins", "cywater-forum"),
    header: "cywater-forum.php",
  },
];

async function readPackageVersion(item) {
  const header = await readFile(path.join(item.source, item.header), "utf8");
  const match = header.match(/^\s*(?:\/\*\*?\s*)?(?:\*\s*)?Version:\s*([^\s*]+)\s*$/im);
  if (!match) {
    throw new Error(`Missing Version header in ${path.join(item.source, item.header)}`);
  }
  return match[1];
}

await rm(distRoot, { recursive: true, force: true });
await mkdir(dist, { recursive: true });

const manifest = {
  release,
  generatedAt: new Date().toISOString(),
  source: "wordpress-integration",
  packages: [],
};

for (const item of packages) {
  await stat(item.source);
  item.version = await readPackageVersion(item);
  const zip = new AdmZip();
  zip.addLocalFolder(item.source, item.slug);
  const filename = `${item.slug}-${item.version}.zip`;
  const target = path.join(dist, filename);
  zip.writeZip(target);
  const entries = zip.getEntries().map((entry) => entry.entryName);
  const requiredEntry = `${item.slug}/${item.header}`;
  if (!entries.includes(requiredEntry)) {
    throw new Error(`${filename} is missing ${requiredEntry}`);
  }
  if (entries.some((entry) => entry.startsWith(`${item.slug}/placeholders/`) || entry.endsWith(".zip"))) {
    throw new Error(`${filename} contains obsolete placeholders or nested release archives`);
  }
  const bytes = await readFile(target);
  manifest.packages.push({
    type: item.type,
    slug: item.slug,
    version: item.version,
    file: filename,
    bytes: bytes.length,
    sha256: createHash("sha256").update(bytes).digest("hex"),
  });
}

const readme = `# CYWater WordPress release ${release}

This folder is generated from the complete source directories. Each archive has exactly one
installable top-level folder and contains no prior release archive or runtime data.

Install or replace the packages in this order:

${packages
  .map(
    (item, index) =>
      `${index + 1}. \`${item.slug}-${item.version}.zip\`${item.type === "theme" ? " (theme)" : ""}`
  )
  .join("\n")}
${packages.length + 1}. Activate all ${packages.filter((item) => item.type === "plugin").length} CYWater plugins and Paid Memberships Pro.
${packages.length + 2}. Open Tools > CYWater setup and run the normal setup once. Do not use force import.
${packages.length + 3}. Purge LiteSpeed and browser caches before visual acceptance.

The normal setup is revision-aware: it upgrades seeded records but does not delete administrator-
created Events, News, Awards, or later editorial changes at the current seed revision.
`;

await writeFile(path.join(dist, "README.md"), readme, "utf8");
await writeFile(path.join(dist, "manifest.json"), `${JSON.stringify(manifest, null, 2)}\n`, "utf8");

const bundle = new AdmZip();
bundle.addLocalFolder(dist, `cywater-wordpress-release-${release}`);
const bundlePath = path.join(distRoot, `cywater-wordpress-release-${release}.zip`);
bundle.writeZip(bundlePath);

const bundleBytes = await readFile(bundlePath);
const outputNames = (await readdir(dist)).sort();
const expectedNames = [...packages.map((item) => `${item.slug}-${item.version}.zip`), "README.md", "manifest.json"].sort();
if (JSON.stringify(outputNames) !== JSON.stringify(expectedNames)) {
  throw new Error(`Release directory contains unexpected files: ${outputNames.join(", ")}`);
}
console.log(`Built ${manifest.packages.length} clean packages in ${path.relative(root, dist)}.`);
console.log(`Bundle: ${path.relative(root, bundlePath)} (${bundleBytes.length} bytes)`);
console.log(`SHA256: ${createHash("sha256").update(bundleBytes).digest("hex")}`);
