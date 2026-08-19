import AdmZip from "adm-zip";
import { access, cp, mkdir, readFile, rename, rm, writeFile } from "node:fs/promises";
import path from "node:path";

const version = "3.8.2";
const root = process.cwd();
const runtime = path.join(root, "wordpress", "runtime", "vendor");
const target = path.join(runtime, "paid-memberships-pro");
const entry = path.join(target, "paid-memberships-pro.php");
const archive = path.join(runtime, `paid-memberships-pro-${version}.zip`);
const localSource = path.join(root, "local", "vendor", "wordpress", `paid-memberships-pro-${version}`, `paid-memberships-pro-${version}`);

try {
  await access(entry);
  console.log(`Paid Memberships Pro ${version} is ready in the ignored runtime directory.`);
  process.exit(0);
} catch {}

await mkdir(runtime, { recursive: true });

try {
  await access(path.join(localSource, "paid-memberships-pro.php"));
  await cp(localSource, target, { recursive: true });
  console.log(`Prepared Paid Memberships Pro ${version} from the local source cache.`);
  process.exit(0);
} catch {}

let bytes;
try {
  bytes = await readFile(archive);
} catch {
  const url = `https://github.com/strangerstudios/paid-memberships-pro/archive/refs/tags/${version}.zip`;
  let lastError;
  for (let attempt = 1; attempt <= 3; attempt += 1) {
    try {
      const response = await fetch(url, { redirect: "follow" });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      bytes = Buffer.from(await response.arrayBuffer());
      await writeFile(archive, bytes, { mode: 0o600 });
      break;
    } catch (error) {
      lastError = error;
      if (attempt < 3) await new Promise((resolve) => setTimeout(resolve, attempt * 1500));
    }
  }
  if (!bytes) throw new Error(`Unable to download Paid Memberships Pro ${version}: ${lastError?.message}`);
}

const zip = new AdmZip(bytes);
const unsafe = zip.getEntries().find((item) => item.entryName.split("/").includes(".."));
if (unsafe) throw new Error(`Unsafe path in PMPro archive: ${unsafe.entryName}`);

const extraction = path.join(runtime, `.pmpro-${version}-extracting`);
await rm(extraction, { recursive: true, force: true });
await mkdir(extraction, { recursive: true });
zip.extractAllTo(extraction, true);

const extractedRoot = path.join(extraction, `paid-memberships-pro-${version}`);
await access(path.join(extractedRoot, "paid-memberships-pro.php"));
await rm(target, { recursive: true, force: true });
await rename(extractedRoot, target);
await rm(extraction, { recursive: true, force: true });
console.log(`Downloaded and prepared Paid Memberships Pro ${version}.`);
