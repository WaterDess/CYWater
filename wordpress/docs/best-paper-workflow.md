# Best Paper workflow — staging implementation

## Release boundary

`cywater-best-paper` is an independent, removable workflow plugin. It attaches
cycle configuration to the existing `cyw_award` record; it does not replace the
Awards archive, create News posts automatically, or maintain a second account
store. Application and review tables are private. Deactivating the plugin keeps
the records and protected files intact.

This first release is for staging review only. Keep the 2026 Award record a draft,
its workflow in Draft, and exact opening/closing dates blank until approved. Do
not assign real reviewers, send real applicant mail, publish results, or enable
production intake as part of this preview.

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

Verified on 2026-09-23: 68 runtime assertions and desktop/mobile Chrome checks
of four synthetic staging-rendered states with and without JavaScript. The
source PDFs were replaced only by synthetic QA files; no historical applications
were imported. The exact ZIP file bytes and per-application paths were checked.
Production remains unchanged. Full authenticated HTTP submit/download/reviewer
acceptance is still required before opening intake; staging's existing HTTP
Basic-auth gate has deliberately not been bypassed or changed.

Current staging preview: Award `1202`, draft, module enabled in Draft phase,
no opening/closing dates and no reviewer assignments. Open **Award records →
Best Paper workflow**, choose the 2026 record, or use the WordPress preview.
The pre-install active-plugin list is retained at
`/home/u111638297/cywater-release-backups/best-paper-staging-20260923/active-plugins-before.json`.
Rollback is plugin deactivation and leaving the draft unpublished; do not drop
application tables or private files. No existing theme or plugin tree was changed.
