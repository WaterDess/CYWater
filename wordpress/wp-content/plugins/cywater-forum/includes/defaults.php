<?php
/**
 * Forum policy defaults.
 *
 * This is the versioned parameter file. Every value here is editable by a
 * `manage_options` administrator under Settings > CYWater Forum, and the stored
 * option overrides the value below. Keeping the defaults in Git means a policy
 * change is reviewable and diffable, while the settings screen means changing
 * one does not require a deployment.
 *
 * Secrets never appear here. API keys and environment gates belong to
 * cywater-environment, which reads them from host-injected constants.
 *
 * To make policy deploy-only, define CYWATER_FORUM_LOCK_SETTINGS as true in
 * the runtime configuration. The settings screen then renders read-only and
 * these defaults become authoritative.
 *
 * @return array<string, mixed>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	/*
	 * Authorship.
	 *
	 * endorsement_articles_required - published forum articles an existing
	 *   author needs before they may endorse somebody else.
	 * endorsements_required - endorsements a new author must collect before
	 *   they may publish.
	 * admin_override - whether an administrator may grant or revoke authorship
	 *   directly, bypassing the endorsement chain.
	 * membership_required - whether an active PMPro membership is a
	 *   prerequisite for publishing.
	 */
	/*
	 * endorsement_required - the master switch for the whole endorsement chain.
	 *
	 * Off for launch, deliberately. Endorsement has no origin on a fresh site:
	 * a qualified endorser must already be endorsed AND have published, so with
	 * nobody endorsed the chain can never start and every author would depend on
	 * an administrator granting authorship by hand. The association chose to
	 * open publishing to paying members instead, to encourage participation.
	 *
	 * With this off, publishing requires an active membership and a verified
	 * email address and nothing else. The machinery below stays in place and is
	 * re-enabled by turning this back on, at which point the members who have
	 * published in the meantime are the pool of qualified endorsers, so the
	 * bootstrap problem does not recur.
	 *
	 * Board members are exempt from dues rather than from membership: an
	 * administrator assigns them a complimentary PMPro level with no order and
	 * no fabricated payment record. See wordpress/docs/forum.md.
	 */
	'endorsement_required'          => false,
	'endorsement_articles_required' => 1,
	'endorsements_required'         => 1,
	'admin_override'                => true,
	'membership_required'           => true,

	/*
	 * Endorsement request limits. A candidate may send this many endorsement
	 * requests within the window before being asked to wait.
	 */
	'endorsement_requests_per_day'  => 5,
	'endorsement_token_ttl_hours'   => 72,

	/*
	 * Discussion.
	 *
	 * comments_enabled - global switch. Individual articles also carry the
	 *   WordPress per-post discussion checkbox, and both must be open.
	 * comments_require_membership - restrict replies to active members.
	 * comments_hold_first - hold a member's first reply for moderation and
	 *   auto-approve afterwards, rather than pre-moderating everything.
	 */
	'comments_enabled'              => true,
	'comments_require_membership'   => true,
	'comments_hold_first'           => true,

	/*
	 * AI. Not implemented. These keys exist so the seam has a configuration
	 * contract from the first release and later work does not have to migrate
	 * the option shape. See class-cywater-forum-ai.php.
	 *
	 * ai_reaction_enabled - master switch for the per-viewer reaction panel.
	 * ai_daily_token_budget - 0 means unlimited; any other value is the daily
	 *   ceiling after which the panel silently stops appearing.
	 * ai_reaction_cache_minutes - how long one viewer's reaction is reused.
	 * ai_comment_review_enabled - allow a later AI pass to look at a comment
	 *   still held in moderation after ai_comment_review_delay_hours.
	 */
	'ai_reaction_enabled'           => false,
	'ai_daily_token_budget'         => 0,
	'ai_reaction_cache_minutes'     => 60,
	'ai_comment_review_enabled'     => false,
	'ai_comment_review_delay_hours' => 24,
);
