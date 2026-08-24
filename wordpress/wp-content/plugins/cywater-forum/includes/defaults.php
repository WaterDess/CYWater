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
	 *   they may participate.
	 * admin_override - whether an administrator may grant or revoke authorship
	 *   directly, bypassing the endorsement chain.
	 * membership_required - whether an active PMPro membership is a
	 *   prerequisite for publishing an article.
	 */
	'endorsements_enabled'          => false,
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
	 * comments_moderation_mode - auto publishes eligible members' replies;
	 *   first holds only their first Forum reply; all holds every reply.
	 */
	'comments_enabled'              => true,
	'comments_require_membership'   => true,
	'comments_moderation_mode'      => 'auto',

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
