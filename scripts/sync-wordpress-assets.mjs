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
// The static archive retains historical, unused mock assets for reference. They
// are not part of the accepted WordPress release and must not ship in patches.
await rm(path.join(destination, "img", "placeholders"), {
  recursive: true,
  force: true,
});
await cp(path.join(source, "docs"), path.join(destination, "docs"), {
  recursive: true,
});
// The theme's main.js is deliberately NOT synced.
//
// It has diverged for a structural reason: the static site marks FAQ questions
// up as divs and needs role/tabindex/keydown shims, while the theme emits real
// <button> elements and instead maintains aria-hidden on the answer, closes the
// mobile drawer on Escape, and returns focus to the toggle. Copying the static
// file over it silently reverts that accessibility work — which is exactly what
// happened once already. Theme-owned behaviour stays theme-owned, the same way
// wordpress.css owns WordPress-only styling.


console.log(`Synced static assets to ${path.relative(root, destination)}`);
