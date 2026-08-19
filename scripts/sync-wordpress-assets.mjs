import { cp, mkdir, rm } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const source = path.join(root, "assets");
const destination = path.join(
  root,
  "wordpress",
  "wp-content",
  "themes",
  "cywater",
  "assets"
);

if (!destination.startsWith(path.join(root, "wordpress") + path.sep)) {
  throw new Error("Refusing to sync outside the WordPress workspace.");
}

await mkdir(destination, { recursive: true });
await mkdir(path.join(destination, "css"), { recursive: true });
// pages.css and main.js intentionally contain WordPress-only accessibility and
// PMPro compatibility adjustments. Sync only the shared assets so a routine
// prepare cannot silently replace those accepted WordPress variants.
for (const stylesheet of ["base.css", "components.css"]) {
  await cp(
    path.join(source, "css", stylesheet),
    path.join(destination, "css", stylesheet),
    { force: true }
  );
}
await rm(path.join(destination, "img"), { recursive: true, force: true });
await cp(path.join(source, "img"), path.join(destination, "img"), {
  recursive: true,
});
// The static archive retains historical, unused mock assets for reference. They
// are not part of the accepted WordPress release and must not ship in patches.
await rm(path.join(destination, "img", "placeholders"), {
  recursive: true,
  force: true,
});
await rm(path.join(destination, "docs"), { recursive: true, force: true });
await cp(path.join(source, "docs"), path.join(destination, "docs"), {
  recursive: true,
});

console.log(`Synced static assets to ${path.relative(root, destination)}`);
