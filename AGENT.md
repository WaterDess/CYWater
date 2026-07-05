# CYWater Project Agent Guide

This file is the project-level handoff note for future coding agents on a new
host. Read it before making changes.

## Project Goal

CYWater is a static bilingual website prototype for an international water
science association. It is currently a front-end-only site intended to present
the association, membership, events, news, journal, governance, and contact
flows.

The live site is published at:

- https://waterdess.github.io/CYWater/

The GitHub repository is:

- https://github.com/WaterDess/CYWater

## Current Scope

This is a functional prototype, not a production membership/payment system.

Implemented:

- bilingual Chinese/English UI switching
- shared injected header, footer, and mobile navigation
- home, about, board, bylaws, membership, dashboard, events, event detail,
  news, article, journal, and contact pages
- mock join/register/contact/newsletter interactions
- local real photo assets under `assets/img/photos/`
- GitHub Pages deployment from the `gh-pages` branch

Not implemented:

- real payments
- real membership accounts
- real form submission
- backend database
- email delivery

Prototype form behavior lives in `assets/js/main.js` and related page scripts.

## Repository Structure

Key files and folders:

- `index.html` - home page
- `about/` - about, board, bylaws pages
- `membership/` - membership landing and mock dashboard
- `events/` - event list and event detail pages
- `news/` - news list and article page
- `journal/` - journal page
- `contact/` - contact page
- `assets/css/base.css` - design tokens, reset, base typography, layout helpers
- `assets/css/components.css` - header, footer, buttons, cards, forms, hero, etc.
- `assets/css/pages.css` - page-specific layout and responsive rules
- `assets/js/i18n.js` - bilingual dictionary and language controller
- `assets/js/layout.js` - shared header/footer/mobile nav injector
- `assets/js/main.js` - shared interactions
- `assets/js/register.js` - event registration prototype flow
- `assets/img/logo.png` - current logo
- `assets/img/photos/` - local real photos used by pages
- `_dl_photos.py` - helper to redownload verified photo assets
- `.nojekyll` - required for predictable GitHub Pages static serving

## Local Preview

No build step is required. This is plain HTML/CSS/JS.

Preferred local preview:

```powershell
python -m http.server 8000
```

Then open:

```text
http://127.0.0.1:8000/
```

Opening `index.html` directly can work, but local server preview is safer.

## Deployment

The public GitHub Pages site is served from the `gh-pages` branch.

After local changes are committed on `main`, deploy with:

```powershell
git push origin main
git push origin main:gh-pages
```

Current remote:

```text
origin https://github.com/WaterDess/CYWater.git
```

The GitHub CLI (`gh`) may be installed on the host, but deployment does not
require it.

## Design Direction

Keep the site quiet, academic, and association-like. The visual language should
feel restrained and polished, closer to AGU or an international science
association than to a marketing landing page.

Important current design decisions:

- Header is full-width: logo near the left edge, primary nav centered, language
  switch/sign in/join actions near the right edge.
- Page content remains constrained in `.container`.
- `About` keeps a dropdown for subpages (`Board`, `Bylaws`).
- `Events` is a direct nav link; do not add a dropdown with `News`/`Journal`
  because those are already top-level nav items.
- Home English hero title must stay exactly two lines:
  - `Advancing water sciences,`
  - `empowering young scholars.`
- Use real local photos where images are needed. Do not replace them with
  abstract placeholder SVGs unless explicitly asked.
- The color system is deliberately restrained: ink, teal, paper, line, and
  limited gold accents.

## Internationalization Rules

All user-facing translatable text should live in `assets/js/i18n.js`.

Use:

```html
data-i18n="group.key"
```

or:

```html
data-i18n-attr="placeholder: group.key"
```

Do not hard-code bilingual page text directly in HTML unless it is static,
non-translated metadata or there is a deliberate reason.

When changing shared nav/footer labels, update `assets/js/layout.js` and
`assets/js/i18n.js` together.

## Coding Principles

- Preserve the static-site architecture unless the user explicitly asks for a
  backend or framework migration.
- Prefer small scoped HTML/CSS/JS edits over broad rewrites.
- Keep shared behavior in `assets/js/layout.js`, `assets/js/main.js`, or
  existing page scripts.
- Keep design tokens in `assets/css/base.css`.
- Put reusable components in `assets/css/components.css`.
- Put page-specific layouts and responsive overrides in `assets/css/pages.css`.
- Avoid introducing build tooling, package managers, or framework dependencies
  unless necessary.
- Do not remove `.nojekyll`.
- Do not upload `.obsidian/`; it is ignored.
- Do not depend on remote Unsplash URLs at render time. Use local files in
  `assets/img/photos/`.
- Be careful with Chinese text encoding. Use UTF-8.

## QA Checklist Before Finalizing

For visual/layout changes:

- preview locally in English and Chinese
- check desktop and mobile widths
- verify no horizontal overflow
- verify header alignment
- verify the language toggle still works
- verify referenced images load

Useful checks:

```powershell
git status -sb
rg -n "assets/img/placeholders|localhost|127\.0\.0\.1" .
node --check assets/js/i18n.js
node --check assets/js/layout.js
node --check assets/js/main.js
```

If using Playwright/Chrome, verify the live DOM rather than only reading files.

## Current Maintenance Notes

- The `README.md` may display garbled in some terminals because of encoding
  rendering, but the site source files should be treated as UTF-8.
- `assets/img/placeholders/` still exists historically, but active page images
  should prefer `assets/img/photos/`.
- `Hydrology` is currently an empty file from the project folder; do not rely on
  it for site behavior.
- The site has no build output directory. The repository root is the site root.

