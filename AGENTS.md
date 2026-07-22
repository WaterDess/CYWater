# CYWater Project Knowledgebase

Keep `AGENT.md`, `AGENTS.md`, and `claude.md` identical whenever project
direction changes.

## Remote SSH Context

- "联想笔记本", "Lenovo laptop", or "本机" in contrast to the Tsinghua server
  means the Windows laptop reached through SSH alias `lenovo-laptop`.
- Compatible aliases are `laptop-fadk2rhp` and `laptop-tailscale`.
- The Tailscale endpoint is `Lenovo@100.97.75.3:22`; expected host name is
  `LAPTOP-FADK2RHP`.
- Expected ED25519 host key fingerprint:
  `SHA256:X8AVCx8SYtOh8g5aeZvtUO99ZGaGeOFKiXvE0jxizQg`.
- Non-interactive ED25519 authentication is configured. From the Tsinghua
  server, use `ssh lenovo-laptop <command>` for unattended commands.
- For transfers back to the Lenovo laptop, use `scp`, `sftp`, or an SSH data
  stream. If no destination is given, discover the real Desktop with
  `[Environment]::GetFolderPath('Desktop')` and create a clearly named folder.
- Read-only check source and destination before transfer. Afterwards verify file
  count and total bytes; compare SHA-256 for important deliverables. Do not
  overwrite same-name files without explicit permission.
- Never put passwords, private keys, tokens, or other secrets in commands,
  logs, documentation, or project files.

## Project Identity

CYWater is a static website preview for the International Association of
Contemporary Young Scholars in Water Sciences. It began as a bilingual visual
prototype and is now an English-only, source-backed public preview.

- Live site: https://waterdess.github.io/CYWater/
- Repository: https://github.com/WaterDess/CYWater
- `main` is the working branch.
- GitHub Pages serves `gh-pages`.
- The original prototype is preserved at `v0.1-prototype` (`e4b5a01`). Never
  move, replace, or publish over this tag.

Publish after local verification:

```powershell
git push origin main
git push origin main:gh-pages
```

Never force-push unless the user explicitly requests it.

## Technical Shape

- Plain static HTML, CSS, and JavaScript
- No bundler, framework, or npm dependency
- Shared header/footer in `assets/js/layout.js`
- Shared interaction and reveal behavior in `assets/js/main.js`
- Structured articles, events, and awards in `assets/js/content.js`
- GitHub Pages hosting

Local preview:

```powershell
python -m http.server 8000
```

Open `http://127.0.0.1:8000/`.

## Local Planning Library

`local/` is an ignored, workstation-only research and planning archive. Never
publish it or store credentials in it. Hosting and WordPress migration planning
starts at `local/hosting/README.md`; that index links to the following notes:

- `01-decision-and-cost.md` - hosting choice, capability gates, budget, and open
  decisions
- `02-wordpress-architecture.md` - theme/plugin boundaries, content models, and
  migration approach
- `03-membership-payments.md` - PMPro, Stripe, membership terms, refunds, and
  transactional email
- `04-environments-deployment.md` - development/staging/production, Cloudflare,
  secrets, deployment, and rollback
- `05-launch-operations-handover.md` - launch QA, backup restore, operations,
  account ownership, and handover
- `official-sources.md` - official links whose pricing and capabilities must be
  reverified before purchase or implementation

These documents are planning records, not the current implementation. Before
work involving hosting, WordPress, membership, payment, DNS, SMTP, deployment,
or handover, read the index and relevant note first. Keep the current static
site as the source of truth until a WordPress replacement passes acceptance.

## Current Site Structure

- `index.html` - home
- `about/index.html` - association purpose and history
- `about/board.html` - governance roles; names remain unconfirmed
- `about/bylaws.html` - full nine-article Bylaws and document download
- `membership/index.html` - eligibility, dues, partnerships, and conference fees
- `membership/dashboard.html` - explicitly non-functional member mockup
- `events/index.html` - Annual Meetings and Annual Gathering
- `events/detail.html` - event renderer using `?id=...`
- `awards/index.html` - Young Scientist Best Paper Award yearbook
- `news/index.html` - Opportunities and Spotlights
- `news/article.html` - article renderer using `?id=...`
- `contact/index.html` - verified mailing address; email pending confirmation
- `assets/img/events/` - supplied Annual Meeting group photographs
- `assets/img/gatherings/` - selected Annual Gathering and virtual award photos
- `assets/docs/CYWater-Bylaws.docx` - supplied Bylaws source document
- `local/` - ignored research archive; never publish unless explicitly requested

The former Journal page and all translation dictionaries, Chinese UI copy,
language controls, and language-state logic have been removed from the current
site. Do not reintroduce them unless the user changes direction.

## Source Authority

Current content is governed by the supplied `CYWaterWebsite0716.docx` and
`CYWater Bylaws.docx`, supplemented by verified material in `local/` and
official CYWater records.

Key requirements:

- Mission and governance language should follow the supplied Bylaws.
- Board names are not confirmed; publish roles and status only.
- The 2026 Annual Meeting is in Nanjing, China, October 16-18; registration is
  expected to open in August.
- Events are separated into Annual Meetings and the Annual Gathering.
- Annual Gathering records currently include 2013, 2017, 2020, 2022, and 2024.
- Awards replace Journal in navigation and cover records from 2012 onward.
- News is separated into Opportunities and Spotlights.
- Membership uses calendar-year terms and the supplied dues/fee schedules.
- Mailing address: `202 E. Green St. Suite 2, Champaign, IL 61820, USA`.
- Contact email is pending confirmation; never invent an address.

Annual Gathering photo sources supplied by a CYWater teacher:

- 2024, Washington, DC, December 10:
  `https://photos.app.goo.gl/gnubxgdkfTdNHvGK7`
- 2022, Chicago, December 14:
  `https://photos.app.goo.gl/MNr5KDLTTJk17d8H8`
- 2020 virtual Best Paper Award gathering, December 18:
  `https://photos.app.goo.gl/ypBGHyJY6tbPfUYz8`
- The 2017 New Orleans dinner photograph is stored as
  `assets/img/gatherings/2017-dinner.png`.

Homepage photography should favor real CYWater images with clear subjects and
reliable landscape crops. Wider documentary sets may be used in detail-page
galleries even when they are not suitable as homepage cards.

People shown in active site photography must come from verified CYWater source
material and must correspond to the event or story where they appear. When no
matching photo exists, use the title-based `news-visual` treatment instead of a
stock photograph of unidentified people. The visual should not label itself as
a placeholder. Title-based visuals follow the Annual Meeting year-tile language:
use the warm paper surface, centered teal Fraunces text, and only the concise
story label plus year. Do not introduce dark pseudo-covers, decorative frames,
tech-style linework, or redundant CYWater branding inside the tile.

The News index currently uses verified CYWater photography for Annual Meetings
2020-2025, the 2011 founding story, and the 2020 virtual Best Paper Award. Other
award/COP27 entries use title-based visuals until matching source photos are
available. Events use the supplied year-matched Annual Meeting and Annual
Gathering archives.

Membership dues are presented with the four-card visual structure adapted from
the original prototype. Preserve the current confirmed categories and prices:
Professional `$70/year`, Student `$20/year`, Lifetime `$700`, and Partner
`$1,000/year`. Display them in ascending-price order, with Professional marked
as the Standard option in the second position. Each card has its own direct
Join action; there is no separate selection-summary step. No card is selected
on initial load. Activating a Join action moves the teal selection accent to
that card and shows the preview limitation without implying that an application
or payment was processed. Professional uses the teal accent action; the other
plans use restrained outline actions that turn teal on hover or activation.
Do not revert to the prototype's old categories or amounts.

Conference fees use a four-column matrix with separate Abstract, Early, and
Standard fee columns. Build it from the established `.table-wrap` and `.table`
components and keep one value per cell. Its column header uses the established
Fraunces display face on a light teal surface; the registration-type row labels
use the same display face, while all fee values remain in the Inter body face.
Do not collapse Early and Standard prices into one unstructured cell.

## Visual Consistency Contract

Visual consistency is a release requirement, not a discretionary polish pass.
New or revised UI must look native to the surrounding page and to the site as a
whole.

- Reuse existing layout and component classes before adding page-specific CSS.
  A new visual treatment is justified only when the current system cannot
  express the content or interaction correctly.
- Use `var(--font-display)` only for established display roles such as page and
  section headings and approved data-table column or row headers. Body copy,
  navigation, buttons, data values, and supporting text use `var(--font-body)`
  through existing component rules.
- Use the shared `--fs-*`, `--sp-*`, line-height, color, border, radius, shadow,
  and container tokens. Do not introduce arbitrary font sizes, line spacing,
  padding, widths, or one-off colors to make one section look independently
  designed.
- Match adjacent sections' container width, section padding, heading spacing,
  text measure, and alignment unless the content has a documented functional
  reason to differ.
- Data tables use `.table-wrap` and `.table`, semantic row/column headers, and
  one value per cell. Data values always use the body typography. The site's
  display family may be used for a complete column-header row and its associated
  row-header labels when that hierarchy is applied consistently. Do not apply
  marketing-card styling or compressed inline labels inside data grids.
- Before publishing, compare the changed section with the sections immediately
  before and after it at desktop and mobile widths. Check typeface, type scale,
  line height, wrapping, spacing rhythm, borders, alignment, and overflow.
- When deployed HTML depends on changed CSS or JavaScript, bump the affected
  page's asset-version query in the same commit. Verify the live HTML and live
  asset both contain the new version; a successful Git push alone is not visual
  QA.
- If real-browser visual QA is unavailable, stay within established components
  and tokens, perform structural and live-asset checks, and explicitly report
  the missing screenshot-level verification. Do not compensate by inventing a
  new visual language.

## Product Boundaries

The site should feel like a serious international scientific association:
quiet, restrained, readable, historically grounded, and easy to maintain.
Preserve the ink/teal/paper design system, full-width header, centered primary
navigation, constrained page content, local real photography, deliberate type
scale, reveal-on-scroll behavior, and responsive mobile drawer.

GitHub Pages is public. `noindex, nofollow` and `robots.txt` reduce crawler
discovery but do not provide authentication. Never describe this preview as
private or access-controlled.

Payments, membership accounts, sign-in, registrations, receipts, and dashboard
data are mock-only. Keep that status explicit and do not suggest submissions or
transactions are processed.

## Editing Guidance

- Keep content IDs and query-string links in sync with `assets/js/content.js`.
- Put global tokens in `assets/css/base.css`, reusable components in
  `assets/css/components.css`, and page/responsive rules in
  `assets/css/pages.css`.
- Use local deployment-safe images under `assets/img/`; do not add remote image
  dependencies or restore active placeholders.
- Avoid broad rewrites unless the user asks. Preserve the existing visual and
  interaction language.
- Do not publish ignored `local/` archives.

## QA Checklist

- Run `node --check` on every changed JavaScript file.
- Check all local links, scripts, stylesheets, images, and query IDs.
- Confirm there are no i18n hooks, Chinese UI strings, Journal links, or active
  placeholder images.
- Inspect desktop and mobile layouts, horizontal overflow, image rendering,
  navigation state, and scroll reveals in a real browser when available.
- Run `git diff --check` and confirm the prototype tag still resolves to
  `e4b5a01ff40abc7938fde11bfe8e6084f60844a3` before publishing.

## Do Not Do Without Approval

- Migrate to React, Vue, Next, or another framework
- Add a backend or build tooling
- Replace the visual system wholesale
- Reintroduce bilingual support
- Publish Board names or an unverified contact email
- Remove `.nojekyll`
- Delete user-created research/source files
- Move the `v0.1-prototype` tag
- Force-push
