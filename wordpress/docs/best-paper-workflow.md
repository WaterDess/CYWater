# Best Paper workflow

## Release boundary

`cywater-best-paper` is an independent, removable workflow plugin. It attaches
cycle configuration to the existing `cyw_award` record; it does not replace the
Awards archive, create News posts automatically, or maintain a second account
store. Application and review tables are private. Deactivating the plugin keeps
the records and protected files intact.

The user authorized production deployment on 2026-09-23. Version `0.1.3` and
theme `0.6.64` are installed on both sites. The 2026 information page is public;
its workflow remains in Draft with exact opening/closing dates blank until
approved. Do not infer that publishing this information opens intake, appoints
reviewers, authorizes invitations or publishes award results.

Public entry: **Awards → Current award cycle → View details and application**,
at `https://cywater.org/awards/best-paper-award-2026/`. It is an Award, not an
Event or an automatically created News post. The current cycle is separate
from the historical yearbook until its results are explicitly announced.
The archive contains only the current-cycle entry and historical yearbook,
with the yearbook explanation under its heading; eligibility belongs on the
individual cycle page, not in a separate archive section.

## Direct-link front-end preview

The requested interactive preview is at
`https://cywater.org/best-paper-2026-preview/`. Anyone with this address can
view it without signing in. This explicitly replaces the original private
administrator-only preview. It is a published WordPress Page (`253` production,
`1212` staging), not a new Award, Event or application cycle. It is excluded
from public navigation, site search and Core page sitemaps, and sends
no-cache/noindex headers. It is unlisted, not private or access-controlled.
Staging retains its separate environment-wide HTTP Basic-auth protection.

The page uses `[cywater_best_paper_preview award_id="251"]` in production
(Award `1202` on staging). It reuses the accepted applicant form and current
account defaults for signed-in visitors; anonymous identity fields are blank.
Inputs and local file choices are enabled for inspection,
but there is no HTML form, action or nonce, and the type-button Submit control
is disabled. No entered values or selected files are uploaded or saved; it
does not load existing applications or consume their one-time feedback.
These protections remain in place even when the real cycle later opens.

Maintainers can take only this preview Page offline by changing its status to
Draft, and restore it as Published while retaining its unlisted marker. Do not
change the official Award or disable the whole plugin just to hide the preview.
The setup helper converts the original private preview once, then preserves
subsequent intentional visibility changes, including Draft.
The previous plugin is retained under
`cywater-release-backups/best-paper-public-preview-{staging,production}-20260923`.

## Confirmed operating requirements

- Announce in October, close applications in November, announce results before
  the December AGU meeting. These are planning months, not fabricated deadlines.
- Hong Yang is the contact for selection questions and review-meeting coordination.
  This does not assign him as Chair, create an account, or authorize contacting him.
- Age is **35 or younger on first submission**, not the closing date. Editing a
  submission must preserve that eligibility snapshot.
- Use the first formal online publication date. The eligible window is the
  twelve-calendar-month period ending on the cycle application deadline.
- Previous Best Paper **or Outstanding Paper** recipients cannot apply again.
  Collect the declaration and check historical records during eligibility review;
  legacy names alone must not be treated as reliable account identity matches.
- One applicant submits one paper per cycle. Repeat submissions of the same paper
  count as one application; DOI normalization and database uniqueness enforce this.
- Select one Best Paper. The number of Outstanding Paper awards is configurable
  through explicit committee decisions, not a hard-coded quota.
- Reviewer scores and z-scores support discussion; they never automatically select
  winners or publish results.
- Use current CYWater identity for new communications. Preserve historical records
  and historical facts as they stand.

## Workflow and authority

1. An Administrator configures the cycle, its exact timezone-labelled dates,
   Chair, and reviewer accounts. Dates and reviewers can be changed without code.
2. Application intake opens only when the Award is published, the module enabled,
   the Applications phase selected, and the configured time window is open.
3. Signed-in applicants submit identity, paper metadata, declarations, paper PDF,
   and CV PDF. They can review the saved record and download their own files.
   First/Last name, email and institution default to the current account's
   existing WordPress and Membership fields (`cyw_institution_name`). A saved
   application takes precedence over later profile changes; retry values take
   precedence over both. Application-only edits never change the account.
   Birth date is not inferred or copied from unrelated private data.
4. Staff check eligibility, missing information, and previous-winner restrictions.
   Only eligible applications may be assigned for scoring.
5. Configured committee members can read the eligible papers in their cycle
   unless recused. Per-paper assignments determine who may enter scores,
   comments, or recuse for a conflict of interest. A reviewer has no general
   membership, payment, publishing, or user-management authority.
6. Staff review aggregate scores. Z-scores use each reviewer's submitted scores and
   **sample** standard deviation. A missing score, fewer than two scores, or zero
   variance yields no z-score, never an invented zero. Normalized results are a
   reference, not a ranked award decision.
7. During Decision, staff explicitly record the committee's Best/Outstanding
   decisions. Announced status requires exactly one Best Paper; changing phase
   does not publish a draft Award or create an announcement.
8. Staff can export a structured package (eligible applications only by default,
   or all applications) containing a CSV, manifest, and separate
   application folders with the original protected PDFs. Export does not turn
   confidential files into public Media Library attachments.

Applicants cannot read others' records. Reviewer reads are scoped to the configured
cycle and conflict rules; scoring additionally requires an assignment. Administrative actions and file downloads
require authorization as well as a nonce. Access is checked again at download
time, so removing reviewer access takes effect on existing links.

## Dates, unknowns, and future handoff

Exact call/deadline/result dates, Chair and committee, prize amount, meeting time,
and ceremony arrangements require cycle-specific confirmation before intake.
The accepted 2025 material is a reference, not authorization to copy its dates,
prize, contact details, or reviewer appointments into the new cycle.

Call announcements, personal invitations, meeting scheduling, winner information
confirmation, certificate production, ceremony slides, and AGU-system upload remain
human-controlled tasks. This first module supports applications and selection;
it does not pretend to automate those external handoffs or award payments.

## Verification

Run the staging-only regression with `wp eval-file
scripts/cywater-staging-best-paper-qa.php`. It uses uniquely marked test accounts
and Award records, suppresses email, and removes its own fixtures. Never run it
against production. Keep live applicant data out of browser fixtures and logs.

Verified on 2026-09-23: 107 runtime assertions and desktop/mobile Chrome checks
of five synthetic staging-rendered states with and without JavaScript. The
source PDFs were replaced only by synthetic QA files; no historical applications
were imported. The exact ZIP file bytes and per-application paths were checked.
Actual production public archive/detail pages also passed the 1440/390px,
JavaScript-on/off matrix: one current cycle, 14 historical Awards, one module,
closed anonymous intake, no overflow and explicit no-cache response headers.
The public direct-link preview also passed the same browser matrix, checking
blank guest identity, applied styling and CSS/JS HTTP 200, local file selection,
no Enter-triggered requests, disabled submission, no overflow, noindex/no-cache,
and absence from home/Awards/navigation/site search/Core page sitemap links.
Atomic deployment must set the public plugin root to mode 755 after extracting
into a mode-700 temporary directory; private data/backup directories stay 700.
Hashes of all pre-existing users, user metadata, Membership rows, orders and
historical Awards were unchanged on both sites. No test registrations or real
applicant mail were created in production. Full authenticated HTTP submit/download/reviewer
acceptance is still required before opening intake; staging's existing HTTP
Basic-auth gate has deliberately not been bypassed or changed.

Current records: production Award `251`, staging Award `1202`, both published
at `/awards/best-paper-award-2026/`; workflow Draft, no exact dates, no committee.
Administrators use **Award records → Best Paper workflow**, choose the 2026
record and follow **Preview application page** to see the disabled form, including
account defaults. Ordinary visitors to the official Award see closed-cycle
information; the separate direct-link preview is available without signing in.

Full database, previous theme and active-plugin-list backups are retained at
`/home/u111638297/cywater-release-backups/best-paper-release-production-20260923`
and `.../best-paper-release-staging-20260923`; staging also has its previous
Best Paper tree. Nine deployed code files were SHA-256 verified against the
release artifact. Production remains Stripe Live; staging remains Sandbox.

Safe rollback: first disable the affected cycle and draft only its current
information record, deactivate the new plugin on production (restore the prior
plugin tree on staging), and restore the three changed theme files from the
theme backup. Never drop private tables/files or bulk-restore a database over
new user activity. Database snapshots are disaster-recovery evidence, not an
automatic rollback instruction. `scripts/cywater-best-paper-publish-2026.php`
is a narrowly scoped, idempotent information-release helper, not a future-cycle
setup tool; it refuses progressed cycles and preserves published content.
