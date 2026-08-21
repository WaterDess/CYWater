# CYWater staging HTTP 429 major incident

Status: **Closed — traced to the Lenovo workstation's Magic Ring VPN route**
Environment: `https://staging.cywater.org/` only
Incident date: 2026-08-18 to 2026-08-19 UTC

## Resolution

The failures stopped when the Magic Ring VPN route on the Lenovo workstation
was disabled. A direct staging media REST probe then returned HTTP 201 JSON and
the temporary attachment, account, and application password were removed. The
incident is retained as historical evidence, but it is no longer attributed to
Hostinger or WordPress and is not a production release blocker. Reopen provider
investigation only if the failure reproduces on a direct network with the VPN
disabled.

## Impact

Ordinary interactive browsing intermittently receives a whole-page `HTTP 429`
from the Hostinger/LiteSpeed layer. The failure has affected both the public
home page and authenticated WordPress administration routes. It is not limited
to one WordPress screen or one CYWater plugin. Production cutover and the
remaining visual acceptance work were initially paused while the source was
investigated.

## Confirmed observations

- An ordinary-use Chrome failure was captured at 2026-08-18 23:54:21 UTC and
  recovered about two minutes later.
- Further Chrome failures were captured at 2026-08-19 00:28:11 UTC and continued
  through approximately 00:32 UTC. A single normal navigation to
  `/wp-admin/edit.php` failed, and separate tabs for `/` and `/wp-admin/` were
  simultaneously left on the browser's `HTTP ERROR 429` page.
- At 00:28:53, 00:30:23, and 00:32:09 UTC, HTTP/1.1 requests from the same Lenovo
  network returned the expected WordPress response (`302` for the logged-out
  admin route) in about 0.37-0.41 seconds.
- At 00:29:07, 00:30:24, and 00:32:10 UTC, same-server HTTP/2 requests returned
  the expected WordPress response in about 0.10-0.11 seconds.
- The staging PHP error log remained 300 bytes with its last entry at
  2026-08-18 16:52:20 UTC. It contains no `429` or PHP error for any incident
  window. No access/WAF log is exposed inside the hosting account's SSH view.
- Successful responses identify `Server: LiteSpeed`, advertise HTTP/3 through
  `alt-svc`, and reach WordPress normally. CYWater code and the active
  `.htaccess` contain no whole-page 429 response rule.
- At 2026-08-19 00:38 UTC, a cookie-free .NET probe on a QUIC-capable client
  could not establish an exact HTTP/3 connection to either `/` or
  `/wp-admin/edit.php`. Exact HTTP/2 requests made seconds later negotiated
  HTTP/2 and returned `200` and the expected `302`, respectively. This confirms
  an unusable advertised HTTP/3 path from the incident network but does not by
  itself prove which server component produced Chrome's 429 response.
- At 2026-08-19 00:46 UTC, a temporary static file under staging tested whether
  account-level `.htaccess` could send `Alt-Svc: clear`. LiteSpeed replaced or
  followed that directive with `alt-svc: h3=\":443\"; ma=2592000,
  h3-29=\":443\"; ma=2592000`. The temporary directory was then removed after
  its resolved absolute path and two expected files were verified. This proves
  the HTTP/3 advertisement cannot be durably corrected by CYWater's WordPress
  code or account-level `.htaccess`; Hostinger must change or repair it at the
  virtual-host, listener or server layer.
- A later side-by-side check used the same staging Administrator in a normal
  Chrome window and an Incognito window. The cached normal session rendered the
  enhanced grouped CYWater menu; the fresh Incognito session rendered only the
  server-ordered menu and also showed a failed avatar resource. Direct checks
  of the Operations CSS, navigation JavaScript, and local avatar subsequently
  returned `200`, confirming intermittent fresh-resource delivery rather than a
  role or permission difference. Operations `0.1.11` now inlines the critical
  group-heading/work-area styles and grouping/collapse interaction into required
  WordPress core admin assets so no separate navigation request is needed. This is a scoped
  UI resilience measure only; it cannot prevent a whole-page 429 and does not
  clear the incident or production release gate.
- The completely unstyled `/wp-admin/` capture was isolated from the CYWater
  menu implementation: the authenticated HTML still contained the grouped
  headings, while WordPress' single 554,001-byte `load-styles.php` core bundle
  had not taken effect. A reversible staging-only containment now defines
  `CONCATENATE_SCRIPTS=false`, replacing the single core loader with individual
  static admin assets. Authenticated Dashboard and editor HTML then contained
  zero `load-styles.php` and zero `load-scripts.php` references. Three external
  rounds against five essential admin styles returned 15/15 HTTP 200 responses;
  the 396-check Operations suite and 29-check editor REST/meta suite passed and
  left zero temporary users, posts or comments. The prior `wp-config.php` is
  retained under
  `/home/u111638297/cywater-release-backups/admin-assets-config-20260819T020500Z`.
  A separate authenticated REST probe from the Lenovo network then created a
  temporary draft with HTTP 201 JSON and updated it with HTTP 200 JSON. The
  exact post and temporary application-password user were removed, and
  independent queries returned zero matching residue.
  This is containment for the admin's all-or-nothing asset failure, not proof
  that Hostinger's upstream 429/connection incident is fixed.

These observations establish that the rejection happens before WordPress. The
protocol-specific cause is not yet proven, but the split between failing Chrome
connections and successful HTTP/1.1 plus HTTP/2 probes makes Hostinger's
HTTP/3/QUIC and fallback behavior, per-client connection/rate controls,
WAF/ModSecurity and account resource limits the required investigation scope.

## Provider investigation checklist (not currently active)

In hPanel, inspect **Websites -> Dashboard -> Resource Usage** for the incident
windows above. Record whether CPU, memory, PHP workers, entry processes or I/O
showed a limit hit. Do not treat a normal graph as a resolution; it instead
rules out account-level resource exhaustion.

If the failure reproduces on a direct network with the VPN disabled, Hostinger
support should inspect server/vhost records for the exact UTC windows and
provide:

1. the component that generated each 429;
2. the exact rule, counter or resource limit that was hit;
3. whether the affected Chrome requests used HTTP/3/QUIC;
4. any per-source-network, connection, WAF/ModSecurity or LiteSpeed throttle;
5. the provider-side remediation, including a vhost/server adjustment or host
   move if the shared server cannot provide stable ordinary visitor traffic.
6. removal or correction of the HTTP/3 advertisement while the advertised QUIC
   route cannot be established; account-level `.htaccess` cannot override the
   LiteSpeed-injected header on this host.

Suggested support message:

> Regular authenticated and public browser traffic to staging.cywater.org is
> intermittently receiving whole-page HTTP 429 responses before WordPress.
> Incidents occurred at 2026-08-18 23:54:21 UTC and 2026-08-19 00:28:11-00:32
> UTC. During the latter window, the public home page and `/wp-admin/` failed in
> Chrome, while HTTP/1.1 requests from the same client network and same-server
> HTTP/2 requests returned the expected WordPress responses within seconds.
> WordPress/PHP logs contain no 429. Please inspect the vhost/server logs for
> HTTP/3/QUIC, per-client connection or rate limits, WAF/ModSecurity and account
> resource faults, and tell us the exact rule/limit plus the durable remediation.

## Exit criteria

For a future direct-network recurrence, do not close the incident after only a
reload, temporary recovery, another browser, cache clearing, or a short all-200
curl run. Require:

- the producing Hostinger component and rule/limit are identified;
- a staging-only, reversible provider or configuration correction is applied;
- public pages and authenticated WordPress administration work in normal Chrome
  navigation without a workaround;
- sustained HTTP/1.1, HTTP/2 and ordinary external-browser checks pass from at
  least two networks; and
- no temporary diagnostic users, content, comments, orders or membership rows
  remain.

No credentials, session data, source IP address or payment secret is recorded
in this incident document.
