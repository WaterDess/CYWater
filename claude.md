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
a placeholder.

The News index currently uses verified CYWater photography for Annual Meetings
2020-2025, the 2011 founding story, and the 2020 virtual Best Paper Award. Other
award/COP27 entries use title-based visuals until matching source photos are
available. Events use the supplied year-matched Annual Meeting and Annual
Gathering archives.

Membership dues are presented with the four-card visual structure adapted from
the original prototype. Preserve the current confirmed categories and prices:
Professional `$70/year`, Student `$20/year`, Lifetime `$700`, and Partner
`$1,000/year`. Do not revert to the prototype's old categories or amounts.

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
