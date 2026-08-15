# CYWater Forum

The forum is a member-authored publication, not a thread board. Members write
articles under an editorial category and free-form topics; readers browse by
author, category, or topic; discussion hangs off an article through core
WordPress comments. Authorship is gated by arXiv-style endorsement.

The module is enabled on staging for acceptance. It remains prohibited on
production until the association approves its editorial scope, moderation
owner, authorship rules, and mail identity. Disabling it removes the Forum UI
without deleting WordPress articles, comments, taxonomies, or revisions.

## Model

| Thing | Where it lives |
| --- | --- |
| Article | `cyw_forum_post` custom post type, `/forum/` |
| Editorial category | `cyw_forum_category` taxonomy, `/forum/category/<slug>/` |
| Topic | `cyw_forum_topic` taxonomy, `/forum/topic/<slug>/` |
| Author page | Core author archive, opened per account (see below) |
| Discussion | Core comments, scoped to `cyw_forum_post` |
| Images | Core media library and featured images |

The four starting categories — Research notes, Perspectives, Career, Community —
are seeded once during `wp cywater setup` and are editable afterwards. Reruns
never overwrite an edited term.

## Authorship

Two separate ideas, deliberately kept apart:

- The **`cyw_forum_author` role** is durable. It is granted when endorsement
  completes and is removed only by an administrator. Losing it would orphan an
  author's drafts.
- **Permission to publish** is evaluated per request, because membership lapses
  and email addresses change. A lapsed member keeps the role, keeps their drafts,
  and keeps their published articles, but cannot publish anything new until they
  are current again.

That split is why the gate lives in `map_meta_cap` rather than in the role.
Publishing requires all of:

1. an endorsement (or an administrator grant),
2. an active PMPro membership, while `membership_required` is on,
3. a verified email address, reusing `CYWater_Membership_Account_Security`
   rather than inventing a second notion of a trusted address.

An author who fails any of these can still write. WordPress shows them
**Submit for Review** instead of **Publish**, which is the intended arXiv-like
behaviour.

## Endorsement

1. A candidate opens `/forum-endorsement/` and enters the email address of an
   existing author.
2. If that address belongs to a qualified endorser, a single-use link is mailed
   to them. The response is identical either way, so the form cannot be used to
   discover who holds an account.
3. The endorser follows the link **while signed in as themselves**. A link
   opened by anyone else is refused and not spent.
4. When the required number of endorsements is recorded, the role is granted and
   the candidate is notified.

A qualified endorser is an endorsed author with at least
`endorsement_articles_required` published articles, or any staff member. Token
mechanics mirror the account-security module exactly: an HMAC of the token is
stored and never the token, links expire, the token is spent before it is acted
on so a replay cannot be processed twice, and requests are rate limited per day.

Endorsement is recomputed against current policy rather than frozen when it was
granted. Raising `endorsements_required` therefore applies to existing authors
too. That is the intended reading of a policy parameter, but it means raising
the threshold silently stops some current authors from publishing — change it
deliberately. Administrator grants are exempt.

## Discussion

Core comments are used rather than a custom table, because core brings
threading, the moderation queue, spam hooks, notification mail, and automatic
participation in WordPress personal-data export and erasure. `cywater-membership`
already relies on that machinery; a private comment table would sit outside it
and quietly break a privacy guarantee that has already been tested on staging.

- Comments exist only on `cyw_forum_post`. News, Events, Awards, and Board roles
  stay closed, enforced by filter rather than by convention.
- Both the global `comments_enabled` switch and the article's own WordPress
  discussion checkbox must be open.
- Replies require sign-in, and an active membership while
  `comments_require_membership` is on. The comment form is hidden from everyone
  else, and the endpoint refuses them independently — the form is not the
  enforcement.
- Default moderation holds a member's first reply and auto-approves afterwards.
  Turning `comments_hold_first` off restores full pre-moderation.

## Author archives and privacy

`cywater-environment` returns 404 for author archives so that numeric account
discovery cannot enumerate members. Reading by author is a forum requirement, so
the environment plugin now exposes a `cywater_public_author_archive_allowed`
filter and the forum opts in **one account at a time**: an account that has
published at least one article gets an archive, and every other author query
still 404s. The enumeration protection is narrowed, not removed.

Only the byline and the member's own biography appear there. Affiliation, ORCID,
and the rest of the professional profile stay behind the member directory's
opt-in, because publishing an article is consent to a byline, not consent to a
public profile.

## Policy parameters

Defaults are versioned in `wp-content/plugins/cywater-forum/includes/defaults.php`
and overridden from **Settings > CYWater Forum** by a `manage_options`
administrator. Defaults in Git means policy is reviewable and diffable; the
settings screen means changing a threshold does not need a deployment.

Current approved values:

| Parameter | Value |
| --- | --- |
| `endorsement_articles_required` | 1 |
| `endorsements_required` | 1 |
| `admin_override` | true |
| `membership_required` | true |

Defining `CYWATER_FORUM_LOCK_SETTINGS` as true in the runtime makes the screen
read-only and the versioned defaults authoritative.

No secret appears in this file or on this screen. API keys and environment gates
belong to `cywater-environment`, which reads host-injected constants.

## AI: declared, not implemented

`class-cywater-forum-ai.php` contains the contract and no implementation. No API
is called, no key is read, no content is generated, and no data leaves the site.
`npm run validate` fails if an outbound call appears in that file, so the seam
cannot quietly become live.

The design the implementation must honour:

1. **The reaction never renders server-side.** Cached article HTML is identical
   for every viewer, so the page cache stays fully effective; per-viewer
   variation lives entirely in the uncached REST response at
   `/wp-json/cywater/v1/forum-reaction/<id>`. This is what makes live
   per-viewer generation compatible with caching at all.
2. **Absence is the default.** Unconfigured, over budget, rate limited, slow, or
   broken all answer 204, and the page shows nothing — no empty container, no
   heading, no spinner, no error. A reader must not be able to tell the feature
   exists.
3. **Never styled as a comment.** It appears for some viewers and not others,
   next to real replies from named scientists. It must be visually and textually
   distinct and explicitly labelled as machine-generated.
4. **The daily token budget is a hard ceiling**, and crossing it switches the
   feature off by the same silent path as any other failure.

To implement: hook `cywater_forum_ai_reaction`, returning a string or `''`, and
enqueue a script that fetches the route and injects at the
`cywater_forum_after_article` mount point in `single-cyw_forum_post.php`.

A second dormant seam, `cywater_forum_ai_comment_recommendation`, lets a later
pass look at a reply still held after `ai_comment_review_delay_hours` and record
a recommendation for the moderator. It may only advise: a machine must not be
the thing that publishes a named member's words.

## Verification so far

`npm run test:forum` runs `scripts/test-forum-playground.mjs` against real
WordPress on Playground. 25 checks pass:

- content type, both taxonomies, the author role, the endorsement page, and the
  four seeded categories install through `wp cywater setup`;
- the approved policy defaults load;
- the publishing gate reports each blocker independently, and an unendorsed
  member is denied `publish_cyw_forum_posts` while an administrator is not;
- endorser qualification requires both an endorsement and a published article;
- the endorsement round trip works end to end, with the token taken from the
  real outgoing mail body: an unknown address sends no mail and returns the same
  response, a link opened by the wrong account is refused without spending the
  token, and a replay is rejected;
- a lapsed membership blocks new publishing while leaving the role, drafts, and
  already published articles untouched;
- discussion is scoped to forum articles with News closed, moderation holds
  replies until one is approved and then auto-approves, and non-members and
  signed-out visitors are refused as recoverable errors;
- the author archive opens per account while a member who has published nothing
  still 404s;
- every forum route renders without a PHP error, and the AI endpoint answers 204
  both enabled and disabled with no trace on the page.

`npm run forum:preview` starts the same environment with seeded articles and two
test accounts for manual review.

Hostinger staging acceptance on 2026-08-16 additionally passed:

- real PMPro membership state and the publish gate on MySQL;
- first-reply moderation, later-reply auto-approval, and nonmember refusal;
- Forum mail-template generation with delivery safely intercepted for the
  disposable address;
- public archive/article HTTP 200, the full-card canonical link, four seeded
  categories, and automatic cleanup of temporary users, membership, article,
  and replies;
- local desktop and 375px browser review with no broken image or horizontal
  overflow; live anonymous HTTP/DOM acceptance passed after deployment.
- Forum archive cards, article bylines, filters, and discussion surfaces reuse
  the accepted CYWater paper, white, line, radius, type, spacing, focus, and
  hover tokens; the 0.6.12 browser review confirmed the compact mobile card
  flow without introducing a Forum-only visual system.

Still required before production:

1. Real Postmark delivery of one endorsement or authorisation message to an
   association-controlled inbox.
2. The moderation queue with a named Editor account and an approved operating
   owner.
3. Authenticated review in a second browser engine. The in-app live screenshot
   connection timed out, although local desktop/mobile rendering and live HTTP
   structure passed.
4. Removal of the `[Staging Preview]` article before production editorial
   content is opened.

Note for reviewers: `npm run prepare` deletes the theme's mirrored `assets/css`
and re-copies the static site's stylesheets. WordPress-only CSS must live in
`wordpress.css`, which the sync never touches. `npm run validate` now enforces
that the mirrored `pages.css` stays byte-identical to the static site's, because
rules written into it were being silently destroyed on the next prepare.

## Open before production

1. Board approval of the forum's scope, moderation policy, and who moderates.
2. A named Editor account to drain the moderation queue.
3. A decision on whether a dedicated `forum@cywater.org` mailbox is wanted;
   endorsement mail currently uses the verified `web@cywater.org` identity and
   can be redirected through the `cywater_forum_mail_headers` filter.
4. Staging acceptance: endorsement request/redeem/replay, publishing gate under
   each blocker, comment scoping, moderation transitions, author-archive 404 for
   a non-author, and desktop/375px review.
5. Approval of the AI reaction feature itself before any provider is attached.
