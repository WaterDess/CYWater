import { access, readFile, readdir } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const required = [
  ".wp-env.json",
  "wordpress/blueprint.json",
  "scripts/build-wp-env-config.mjs",
  "scripts/prepare-wordpress-vendor.mjs",
  "scripts/test-playground.mjs",
  "wordpress/wp-content/plugins/cywater-core/data/seed.json",
  "wordpress/wp-content/themes/cywater/style.css",
  "wordpress/wp-content/themes/cywater/functions.php",
  "wordpress/wp-content/themes/cywater/page-about.php",
  "wordpress/wp-content/themes/cywater/page-bylaws.php",
  "wordpress/wp-content/themes/cywater/page-membership.php",
  "wordpress/wp-content/themes/cywater/page-contact.php",
  "wordpress/wp-content/plugins/cywater-core/cywater-core.php",
  "wordpress/wp-content/plugins/cywater-membership/cywater-membership.php",
  "wordpress/wp-content/plugins/cywater-environment/cywater-environment.php",
];

for (const relativePath of required) {
  await access(path.join(root, relativePath));
}

const forbidden = [
  /(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{8,}/,
  /whsec_[A-Za-z0-9]{8,}/,
];
const textExtensions = new Set([".example", ".js", ".json", ".md", ".mjs", ".php", ".yaml", ".yml"]);

async function findTextFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...(await findTextFiles(absolute)));
    else if (textExtensions.has(path.extname(entry.name))) files.push(absolute);
  }
  return files;
}

const filesToScan = [
  path.join(root, ".env.example"),
  path.join(root, "package.json"),
  path.join(root, "wordpress", "blueprint.json"),
  path.join(root, "wordpress", "docker-compose.mailpit.yml"),
  ...(await findTextFiles(path.join(root, "scripts"))),
  ...(await findTextFiles(path.join(root, "wordpress", "docs"))),
  ...(await findTextFiles(path.join(root, "wordpress", "wp-content"))),
];
for (const file of filesToScan) {
  const contents = await readFile(file, "utf8");
  for (const pattern of forbidden) {
    if (pattern.test(contents)) {
      throw new Error(`Secret-like value found in ${path.relative(root, file)}.`);
    }
  }
}

console.log(`Validated ${required.length} required files and scanned ${filesToScan.length} text files for credential patterns.`);
