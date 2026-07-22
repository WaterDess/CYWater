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

await rm(destination, { recursive: true, force: true });
await mkdir(destination, { recursive: true });
await cp(path.join(source, "css"), path.join(destination, "css"), {
  recursive: true,
});
await cp(path.join(source, "img"), path.join(destination, "img"), {
  recursive: true,
});
await cp(path.join(source, "docs"), path.join(destination, "docs"), {
  recursive: true,
});
await cp(
  path.join(source, "js", "main.js"),
  path.join(destination, "js", "main.js")
);

console.log(`Synced static assets to ${path.relative(root, destination)}`);
