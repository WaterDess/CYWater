# CYWater Website

CYWater is the static public-site preview for the International Association of
Contemporary Young Scholars in Water Sciences. The current site is English-only
and uses real association history, meeting records, award records, and local
image assets where those materials have been verified.

Live preview: https://waterdess.github.io/CYWater/

## Stack

- Plain HTML, CSS, and JavaScript
- No build step or package dependency
- GitHub Pages deployment from `gh-pages`

Start a local preview with:

```powershell
python -m http.server 8000
```

Then open `http://127.0.0.1:8000/`.

## Site Structure

- `index.html` - home
- `about/` - association, Board status, and Bylaws
- `membership/` - membership requirements and mock dashboard
- `events/` - Annual Meetings and Annual Gathering archive
- `awards/` - Young Scientist Best Paper Award yearbook
- `news/` - opportunities and association spotlights
- `contact/` - verified mailing address and contact status
- `assets/js/content.js` - structured article, event, and award records
- `assets/js/layout.js` - shared navigation and footer
- `assets/js/main.js` - shared interface behavior
- `assets/css/` - design tokens, components, and responsive page styles
- `assets/img/events/` - supplied Annual Meeting group photographs
- `assets/img/gatherings/` - selected Annual Gathering and virtual award photos
- `assets/docs/CYWater-Bylaws.docx` - supplied Bylaws document

## Content Rules

- The current public preview is English-only. Do not reintroduce translation
  dictionaries or language-switching logic unless the project direction changes.
- Board names remain unconfirmed and must not be published speculatively.
- Contact email remains to be confirmed; do not invent one.
- Membership payments, accounts, registration, receipts, and dashboard data are
  prototype-only and must remain clearly identified as non-functional.
- Prefer supplied records and source-backed history over placeholder content.

## Preview Visibility

Every page carries `noindex, nofollow`, and `robots.txt` requests that crawlers
not index the preview. GitHub Pages is still publicly reachable by URL; these
directives are not authentication or access control.

## Publishing

Work on `main`, verify locally, then publish the same commit to GitHub Pages:

```powershell
git push origin main
git push origin main:gh-pages
```

The original bilingual prototype remains preserved at the immutable
`v0.1-prototype` tag. Do not move or overwrite that tag.

## WordPress Integration

The production candidate is developed separately on the
`wordpress-integration` branch. It contains a custom WordPress theme, modular
content/membership/environment plugins, Paid Memberships Pro integration,
Stripe sandbox safeguards, Mailpit support, CI, and deployment documentation.
It does not replace this GitHub Pages preview until staging acceptance.

Start with `wordpress/docs/README.md` when working on hosting, member accounts,
payments, email, DNS, backups, or production deployment.

## QA

Before publishing, check JavaScript syntax, all local links and images, desktop
and mobile layouts, horizontal overflow, navigation state, and mock-feature
labels. Use real browser inspection when available.
