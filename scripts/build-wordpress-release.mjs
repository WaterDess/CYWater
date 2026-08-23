import { createHash } from "node:crypto";
import { mkdir, readFile, readdir, rm, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import AdmZip from "adm-zip";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const packageJson = JSON.parse(await readFile(path.join(root, "package.json"), "utf8"));
const release = packageJson.version;
const productionClean = process.argv.includes("--production-clean");
const profile = productionClean ? "production-clean" : "complete-integration";
const distRoot = path.join(root, "dist");
const distName = productionClean ? `wordpress-production-clean-${release}` : `wordpress-release-${release}`;
const dist = path.join(distRoot, distName);
const wpContent = path.join(root, "wordpress", "wp-content");
const allPackages = [
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
    slug: "cywater-partnerships",
    source: path.join(wpContent, "plugins", "cywater-partnerships"),
    header: "cywater-partnerships.php",
  },
  {
    type: "plugin",
    slug: "cywater-logo-call",
    source: path.join(wpContent, "plugins", "cywater-logo-call"),
    header: "cywater-logo-call.php",
  },
  {
    type: "plugin",
    slug: "cywater-forum",
    source: path.join(wpContent, "plugins", "cywater-forum"),
    header: "cywater-forum.php",
  },
  {
    type: "plugin",
    slug: "cywater-operations",
    source: path.join(wpContent, "plugins", "cywater-operations"),
    header: "cywater-operations.php",
  },
  {
    type: "plugin",
    slug: "cywater-environment",
    source: path.join(wpContent, "plugins", "cywater-environment"),
    header: "cywater-environment.php",
  },
];
const excludedPackages = [];
const excludedSlugs = new Set(excludedPackages.map(({ slug }) => slug));
const packages = allPackages.filter(({ slug }) => !excludedSlugs.has(slug));

async function readPackageVersion(item) {
  const header = await readFile(path.join(item.source, item.header), "utf8");
  const match = header.match(/^\s*(?:\/\*\*?\s*)?(?:\*\s*)?Version:\s*([^\s*]+)\s*$/im);
  if (!match) {
    throw new Error(`Missing Version header in ${path.join(item.source, item.header)}`);
  }
  return match[1];
}

const stagingSourceMinimums = new Map([
  ["cywater-logo-call", "0.2.3"],
  ["cywater-environment", "0.5.7"],
]);

function compareVersions(left, right) {
  const a = left.split(".").map(Number);
  const b = right.split(".").map(Number);
  for (let index = 0; index < Math.max(a.length, b.length); index += 1) {
    const delta = (a[index] || 0) - (b[index] || 0);
    if (delta) return delta;
  }
  return 0;
}

// Never generate a production bundle from source older than the verified
// staging baseline. Reconcile the deployed tree first; an old local package
// must not silently overwrite a newer, accepted staging implementation.
for (const item of packages) {
  await stat(item.source);
  item.version = await readPackageVersion(item);
  const minimum = stagingSourceMinimums.get(item.slug);
  if (minimum && compareVersions(item.version, minimum) < 0) {
    throw new Error(
      `${item.slug} source ${item.version} is older than verified staging ${minimum}; recover and reconcile the deployed source before building a release.`
    );
  }
}

await rm(distRoot, { recursive: true, force: true });
await mkdir(dist, { recursive: true });

const manifest = {
  release,
  profile,
  generatedAt: new Date().toISOString(),
  source: "wordpress-integration",
  excludedPackages,
  packages: [],
};

for (const item of packages) {
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

const readme = `# CYWater WordPress release ${release} (${profile})

This folder is generated from the complete source directories. Each archive has exactly one
installable top-level folder and contains no prior release archive or runtime data.

${productionClean ? "This production-clean profile includes every accepted CYWater module, including Logo Call. Production data must still be clean: migrate the reviewed Logo Event configuration without staging submissions, votes, identities, or protected files.\n" : "This complete-integration profile includes every CYWater module in the repository.\n"}

Install or replace the packages in this order:

${packages.map((item, index) => `${index + 1}. \`${item.slug}-${item.version}.zip\`${item.type === "theme" ? " (theme)" : ""}`).join("\n")}
${packages.length + 1}. Activate all ${packages.filter((item) => item.type === "plugin").length} CYWater plugins and Paid Memberships Pro.
${packages.length + 2}. Open Tools > CYWater setup and run the normal setup once. Do not use force import.
${productionClean ? `${packages.length + 3}. Run \`wp eval-file cywater-production-logo-event-setup.php\` to create or verify the accepted Logo Event without staging entries or votes.\n` : ""}${packages.length + (productionClean ? 4 : 3)}. Purge LiteSpeed and browser caches before visual acceptance.

The normal setup is revision-aware: it upgrades seeded records but does not delete administrator-
created Events, News, Awards, or later editorial changes at the current seed revision.
${productionClean ? "\nBefore DNS cutover, place `cywater-production-purity-audit.php` where WP-CLI can read it and run `wp eval-file cywater-production-purity-audit.php`. It is read-only and must pass after staging fixtures, Sandbox orders/levels, review-only policy copy, staging links, and Logo Call test entries/votes have been resolved.\n" : ""}
`;

const productionSupportFiles = [
  {
    file: "cywater-production-purity-audit.php",
    purpose: "Read-only pre-DNS production data and configuration purity gate.",
  },
  {
    file: "cywater-production-logo-event-setup.php",
    purpose: "Idempotent setup for the accepted production Logo Call Event without staging entries or votes.",
  },
  {
    file: "cywater-production-policy-approval.php",
    purpose: "One-time publication of the four Board-approved initial CYWater policies.",
  },
];
if (productionClean) {
  manifest.supportFiles = [];
  for (const support of productionSupportFiles) {
    const bytes = await readFile(path.join(root, "scripts", support.file));
    await writeFile(path.join(dist, support.file), bytes);
    manifest.supportFiles.push({
      ...support,
      bytes: bytes.length,
      sha256: createHash("sha256").update(bytes).digest("hex"),
    });
  }
}
await writeFile(path.join(dist, "README.md"), readme, "utf8");
await writeFile(path.join(dist, "manifest.json"), `${JSON.stringify(manifest, null, 2)}\n`, "utf8");

const bundle = new AdmZip();
bundle.addLocalFolder(dist, `cywater-${distName}`);
const bundlePath = path.join(distRoot, `cywater-${distName}.zip`);
bundle.writeZip(bundlePath);

const bundleBytes = await readFile(bundlePath);
const outputNames = (await readdir(dist)).sort();
const expectedNames = [
  ...packages.map((item) => `${item.slug}-${item.version}.zip`),
  "README.md",
  "manifest.json",
  ...(productionClean ? productionSupportFiles.map(({ file }) => file) : []),
].sort();
if (JSON.stringify(outputNames) !== JSON.stringify(expectedNames)) {
  throw new Error(`Release directory contains unexpected files: ${outputNames.join(", ")}`);
}
console.log(`Built ${manifest.packages.length} clean packages in ${path.relative(root, dist)}.`);
console.log(`Bundle: ${path.relative(root, bundlePath)} (${bundleBytes.length} bytes)`);
console.log(`SHA256: ${createHash("sha256").update(bundleBytes).digest("hex")}`);
