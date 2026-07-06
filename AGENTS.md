# CYWater Codex Handoff

This file gives Codex project context after a system reinstall or disk
migration. It intentionally mirrors `AGENT.md`; keep both files aligned when
the project direction changes.

## What This Project Is

CYWater is a static bilingual website prototype for an international association
of contemporary young scholars in water sciences. It is a polished front-end
prototype, not a production backend system.

Live site:

- https://waterdess.github.io/CYWater/

Repository:

- https://github.com/WaterDess/CYWater

Deployment:

- GitHub Pages serves the `gh-pages` branch.
- Normal workflow is to commit on `main`, push `main`, then push `main` to
  `gh-pages`.

```powershell
git push origin main
git push origin main:gh-pages
```

## Tech Stack

- Plain static HTML
- Plain CSS
- Plain JavaScript
- No bundler
- No npm dependency required
- GitHub Pages hosting

Local preview:

```powershell
python -m http.server 8000
```

Then open:

```text
http://127.0.0.1:8000/
```

## Important Files

- `index.html` - home page
- `assets/css/base.css` - tokens, reset, typography, helpers
- `assets/css/components.css` - header, footer, hero, cards, buttons, forms
- `assets/css/pages.css` - page layouts and responsive behavior
- `assets/js/i18n.js` - bilingual dictionary and language switching
- `assets/js/layout.js` - shared header/footer/mobile navigation injection
- `assets/js/main.js` - global interactions
- `assets/js/register.js` - event registration prototype flow
- `assets/img/photos/` - real local JPG assets used on the site
- `_dl_photos.py` - helper for restoring photo assets

## Product Intent

The website should feel like a serious international scientific association:
clean, restrained, bilingual, readable, and easy to maintain. Avoid flashy
marketing-style redesigns.

The current prototype includes:

- home page
- about page
- board page
- bylaws page
- membership page
- mock dashboard
- events list
- event detail and mock registration
- news list
- article page
- journal page
- contact page

Mock-only features:

- payments
- accounts
- joining
- registration
- contact/newsletter submission

Do not represent these as production features.

## Current UI Decisions To Preserve

- Header spans full width.
- Logo sits near the left edge.
- Language/sign-in/join actions sit near the right edge.
- Primary navigation is centered.
- Main page content still uses the constrained `.container`.
- `About` has a dropdown for `Board` and `Bylaws`.
- `Events` is a simple direct link; do not put `News` or `Journal` under it.
- English home hero title must stay two lines:

```text
Advancing water sciences,
empowering young scholars.
```

- Chinese hero should remain balanced and readable.
- Hero paragraph spacing should be comfortable in both languages.
- Use local real photos. Do not revert active content to placeholder SVGs.

## Internationalization

The bilingual source of truth is `assets/js/i18n.js`.

Use `data-i18n="group.key"` for translatable text.

Use `data-i18n-attr="placeholder: group.key"` for translated attributes.

When adding text:

1. Add the key to the correct group in `assets/js/i18n.js`.
2. Reference it from HTML with `data-i18n`.
3. Check English and Chinese layouts.

## CSS Guidance

- Global tokens: `assets/css/base.css`.
- Reusable components: `assets/css/components.css`.
- Page-specific and responsive rules: `assets/css/pages.css`.
- Keep responsive behavior explicit.
- Avoid broad refactors unless the user asks.
- Preserve the current quiet palette.
- Be careful with hero line-height, spacing, and mobile overflow.

## Image Guidance

Images should render reliably after deployment and in China-facing demos.

Use:

```text
assets/img/photos/
```

Avoid:

- remote Unsplash image URLs in page HTML
- broken image URLs
- abstract placeholder SVGs for active content

The historical placeholder SVGs under `assets/img/placeholders/` can stay, but
should not be used for active page imagery unless explicitly requested.

## Git / Publish Notes

The repository is already initialized.

Expected clean status:

```powershell
git status -sb
```

Remote:

```text
origin https://github.com/WaterDess/CYWater.git
```

Publishing to GitHub Pages:

```powershell
git add <changed-files>
git commit -m "<short message>"
git push origin main
git push origin main:gh-pages
```

Do not force-push unless explicitly requested.

## QA Checklist

Before finishing changes:

- check English and Chinese
- check desktop and mobile
- check no horizontal overflow
- check header alignment
- check language toggle
- check images load
- run JS syntax checks when JS changes

Useful commands:

```powershell
node --check assets/js/i18n.js
node --check assets/js/layout.js
node --check assets/js/main.js
rg -n "assets/img/placeholders|localhost|127\.0\.0\.1" .
```

If browser automation is available, use Chrome/Playwright to inspect actual
layout instead of guessing from CSS.

## Do Not Do Without User Approval

- migrate to React/Vue/Next/etc.
- add a backend
- add package/build tooling
- replace the visual system wholesale
- remove bilingual support
- remove `.nojekyll`
- rewrite page structure unnecessarily
- delete user-created documents or research files

