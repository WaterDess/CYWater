<?php
/**
 * Non-destructive creation of editable launch-policy drafts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Policy_Drafts {
	const META_KEY = '_cywater_board_review_policy';
	const VERSION_META_KEY = '_cywater_board_review_policy_version';
	const APPROVED_FROM_META_KEY = '_cywater_policy_approved_from';
	const APPROVED_AT_META_KEY = '_cywater_policy_approved_at';
	const CONTENT_VERSION  = '2026-08-18-nonrefundable';

	/** Keep staging review drafts out of search-engine indexes. */
	public static function register() {
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
	}

	public static function filter_robots( $robots ) {
		if ( is_singular( 'page' ) && '1' === (string) get_post_meta( get_queried_object_id(), self::META_KEY, true ) ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	/**
	 * Create missing drafts without overwriting later editorial work.
	 *
	 * @return array<string,int>
	 */
	public static function ensure() {
		$ids = array();
		foreach ( self::definitions() as $slug => $definition ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $existing instanceof WP_Post ) {
				$approved = get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => array( 'publish', 'draft', 'private' ),
						'posts_per_page' => 1,
						'meta_key'       => self::APPROVED_FROM_META_KEY,
						'meta_value'     => $slug,
					)
				);
				$existing = $approved ? $approved[0] : null;
			}
			if ( $existing instanceof WP_Post ) {
				$ids[ $slug ] = (int) $existing->ID;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $definition['title'],
					'post_name'    => $slug,
					'post_content' => self::notice() . $definition['content'],
					'meta_input'   => array(
						self::META_KEY         => '1',
						self::VERSION_META_KEY => self::CONTENT_VERSION,
					),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				throw new RuntimeException( $post_id->get_error_message() );
			}
			$ids[ $slug ] = (int) $post_id;
		}
		return $ids;
	}

	/**
	 * Return the canonical managed review content for a known policy slug.
	 *
	 * This is intentionally read-only. A deployment migration may use it after
	 * independently proving that the target is still a CYWater Board-review
	 * page; normal setup never overwrites later editorial work.
	 */
	public static function content_for( $slug ) {
		$definitions = self::definitions();
		return isset( $definitions[ $slug ] ) ? self::notice() . $definitions[ $slug ]['content'] : '';
	}

	private static function notice() {
		return '<div class="cywater-policy-draft-notice"><strong>Draft for Board Review — Not approved or in effect.</strong><br>This working draft is for staging review only. CYWater should obtain legal and tax review before publication.</div>';
	}

	/**
	 * @return array<string,array{title:string,content:string}>
	 */
	private static function definitions() {
		return array(
			'privacy-notice-draft' => array(
				'title'   => 'Privacy Notice (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Scope and organization</h2>
<p>CYWater is the International Association of Contemporary Young Scholars in Water Sciences. This notice applies to personal information handled through cywater.org, CYWater accounts, individual membership, events, association programs, partnership enquiries and related communications.</p>
<h2>Information CYWater collects</h2>
<ul><li><strong>Account and contact information:</strong> username, name, email address, email-verification state, account creation, sign-in and security records.</li><li><strong>Professional and membership information:</strong> institution, region, role, career stage, membership level and dates, eligibility records, profile information and directory privacy choices.</li><li><strong>Transaction information:</strong> orders, prices, currency, payment status, refunds, renewal status and limited processor references. Stripe processes payment credentials; CYWater does not receive or store full payment-card numbers.</li><li><strong>Participation information:</strong> event registrations and attendance, forum posts and replies, association-program submissions and votes, and partnership enquiries and review status.</li><li><strong>Communications and technical information:</strong> messages sent to CYWater, transactional-email status, browser and device information, and limited network or server logs used for security and reliability.</li></ul>
<h2>How CYWater uses information</h2>
<p>CYWater uses personal information to provide and secure accounts; verify identity and eligibility; administer membership, payments, refunds, events and programs; provide member benefits; moderate community content; respond to requests; maintain records; prevent fraud and abuse; and comply with accounting, tax and legal obligations. Where applicable, processing is based on a transaction or requested service, consent, CYWater's legitimate operational needs, or a legal obligation.</p>
<h2>Directory and public content</h2>
<p>The member directory is private by default. A profile is public only when an eligible member opts in, and only fields that member separately selects are displayed. Email addresses are never directory fields. Forum posts, replies, shortlisted program entries and other content intentionally submitted for publication may be visible under the applicable program rules.</p>
<h2>Service providers and disclosure</h2>
<p>CYWater uses service providers including Hostinger and WordPress for hosting, Paid Memberships Pro for membership records, Stripe for payment processing, Postmark for transactional email and Google Workspace for association communications. CYWater may disclose only the information reasonably needed by these providers, authorized association personnel, professional advisers, or public authorities when required by law. CYWater does not sell personal information.</p>
<h2>Retention, security and international access</h2>
<p>CYWater retains information only for the periods described in its Data Retention and Account Closure Policy or as required for accounting, dispute, fraud-prevention, governance or legal purposes. CYWater uses reasonable administrative and technical safeguards, but no internet service can guarantee absolute security. Because CYWater serves an international community and uses service providers in multiple locations, information may be processed outside the user's country subject to applicable safeguards.</p>
<h2>Your choices and requests</h2>
<p>Users can correct account information, change directory choices, request an export of supported WordPress data and request account closure. A closure request does not automatically erase transaction, refund, event, governance, moderation or security records that CYWater must retain. Requests should be sent from the account email to membership@cywater.org. CYWater may take reasonable steps to verify the requester before acting.</p>
<h2>Children</h2>
<p>CYWater services are intended for researchers, students and professionals who can lawfully create an account and accept these terms. A person who cannot legally provide the required consent must not create an account without authorization from a parent or legal guardian where permitted.</p>
<h2>Contact and changes</h2>
<p>Privacy and membership questions: membership@cywater.org. General correspondence: contact@cywater.org. Material changes will be posted with a revised effective date and, when appropriate, communicated to affected account holders.</p>
<h2>Board review before publication</h2>
<p><strong>Confirm:</strong> effective date, cookie and analytics inventory, service-provider agreements, international-transfer disclosures, minimum-age treatment, applicable state or international privacy notices, and the final identity-verification and response procedure.</p>
HTML,
			),
			'terms-of-use-draft' => array(
				'title'   => 'Terms of Use (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Acceptance and scope</h2>
<p>These Terms govern use of cywater.org and CYWater's account, membership, event, forum and association-program services. By creating an account, submitting content, registering for an event or purchasing a membership, a user agrees to these Terms and the policies identified at the relevant form or checkout. If a user cannot lawfully accept them, the user must not use the service.</p>
<h2>Accounts and security</h2>
<p>Users must provide accurate current information, maintain one account per person unless CYWater authorizes otherwise, protect their credentials and promptly report suspected unauthorized use. Accounts and membership benefits are personal and may not be sold, shared or transferred except under a published CYWater rule.</p>
<h2>Membership, events and partnerships</h2>
<p>An account is not itself a paid membership. Membership begins only when the applicable payment or authorized administrative action is recorded. The level, price, term and benefits are those displayed at checkout and in the member account. Event registration is a separate purchase governed by event-specific terms. Institutional partnership is a separate Board and MOU process and is not individual membership.</p>
<p>Membership and event payments are governed by the Billing, Cancellation and Refund Policy. All membership sales are final and non-refundable. Cancelling future renewal does not refund the current membership term. Event and program payments remain separate and are governed by the terms displayed for that event or program.</p>
<h2>Acceptable use and community conduct</h2>
<p>CYWater services may be used only for lawful scientific, educational, professional and association purposes. Users must not impersonate another person; harass or threaten others; publish unlawful, deceptive, infringing or malicious material; scrape or automate access without permission; interfere with security or availability; attempt unauthorized access; or misuse personal information obtained through CYWater.</p>
<p>CYWater may review, moderate, restrict or remove content and may suspend an account when reasonably necessary to enforce these Terms, protect users or services, comply with law, or investigate abuse. Serious or repeated violations may result in termination without refund to the extent permitted by law.</p>
<h2>User content and intellectual property</h2>
<p>Users retain ownership of content they submit unless a specific program agreement states otherwise. By submitting content for publication, a user grants CYWater a non-exclusive, worldwide, royalty-free license to host, reproduce, format and display that content only as reasonably needed to operate, promote and archive the relevant CYWater service or program. The user represents that the content is lawful and that the user has the rights needed to grant this license.</p>
<p>Logo Call entrants retain rights in non-selected work. The submission license is limited to administration, review and voting. Selection does not transfer ownership: permanent use, modification or trademark registration requires a separate written assignment or license approved by the Board.</p>
<h2>CYWater content and third-party services</h2>
<p>CYWater's name, site design, publications and other association materials may not be copied or used to imply endorsement without permission. Links and services supplied by Stripe, Hostinger, WordPress, Postmark or other providers are also governed by their own terms. CYWater is not responsible for third-party services outside its reasonable control.</p>
<h2>Availability and disclaimers</h2>
<p>CYWater may change, suspend or discontinue a service for maintenance, security, legal or organizational reasons. Services and informational content are provided on a reasonable-efforts and "as available" basis. They are not legal, medical, financial or engineering advice. To the maximum extent permitted by law, CYWater disclaims implied warranties and is not liable for indirect, incidental, special or consequential loss. Nothing in these Terms excludes liability or consumer rights that cannot lawfully be excluded.</p>
<h2>Changes, governing law and contact</h2>
<p>Material changes will be posted with a revised effective date and, when appropriate, communicated to affected users. Continued use after the effective date constitutes acceptance where permitted by law. Questions may be sent to contact@cywater.org.</p>
<h2>Board review before publication</h2>
<p><strong>Confirm:</strong> effective date, minimum-age rule, Illinois governing law and forum, moderation appeal path, liability cap, copyright-notice procedure, and notice requirements for material changes.</p>
HTML,
			),
			'billing-cancellation-refund-policy-draft' => array(
				'title'   => 'Billing, Cancellation and Refund Policy (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Clear checkout terms</h2>
<p>Before payment, checkout must show the item or membership, price, currency, term, benefits or registration covered, and whether payment is one-time or automatically recurring. CYWater must not enable automatic renewal unless the checkout clearly discloses the amount, frequency, cancellation method and renewal timing and records the payer's affirmative consent.</p>
<h2>Membership sales are final and non-refundable</h2>
<p><strong>All membership sales are final and non-refundable.</strong> Each successful Student or Professional payment purchases one full year beginning on the payment date; Lifetime membership does not expire. Membership is not prorated. CYWater does not refund or credit membership dues because of nonuse, an incorrect level selection, changed circumstances, cancellation during a term, or unused time.</p>
<p>Cancelling an automatic renewal, when renewal is offered, stops future charges but does not refund or shorten the current membership term. At initial launch, automatic renewal must remain disabled unless the Board separately approves recurring-billing terms and checkout records the member's affirmative consent.</p>
<h2>Billing corrections and reversed membership payments</h2>
<p>CYWater may correct a duplicate charge, technical processing error or unauthorized payment, and will comply with a payment-processor reversal, chargeback decision or right that cannot lawfully be waived. These are billing corrections or legally required remedies, not a general membership-refund entitlement.</p>
<p>If a membership payment is reversed or corrected in full, CYWater cancels only the membership level funded by that payment and stops its matching future renewal, if any. A separately purchased event registration, submission, partnership or other service remains a separate transaction governed by its own terms. If that separate purchase depended on active membership or a member-only rate, CYWater may apply the eligibility and price-adjustment rule disclosed for that purchase.</p>
<h2>Event and program payments</h2>
<p>Event registration fees, abstract or submission fees and other program payments are separate from membership dues. Before a paid event opens, it must publish its own cancellation deadline, refund schedule, processing fee, transfer or substitution rule, capacity rule and treatment of cancellation, postponement or format changes. If no event-specific refund terms are displayed before payment, paid registration must not open.</p>
<p>An approved event refund cancels only the matching registration, seat or program entitlement. It does not cancel or refund an individual membership. Non-refundable processing or submission fees must be clearly identified before payment. If CYWater cancels an event, CYWater will follow the cancellation remedy stated in that event's published terms.</p>
<h2>How to request an event remedy, billing correction or renewal cancellation</h2>
<p>Requests must be sent to billing@cywater.org from the account email and identify the relevant order or registration without including card numbers or other payment credentials. CYWater may request information needed to verify the payer and transaction. Any approved event refund or billing correction returns to the original payment method where possible. Stripe and the payer's financial institution control the time needed for funds to appear.</p>
<h2>Disputes and required remedies</h2>
<p>Duplicate charges, technical errors, suspected fraud, unauthorized payments, processor reversals and chargebacks are reviewed against the matching order and entitlement. Users should contact CYWater promptly so that the underlying record can be reviewed consistently. Partnership payments are governed by the approved MOU and invoice terms. Nothing in this policy limits rights that cannot lawfully be waived.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> the final-sale disclosure at checkout, the event refund schedule template, automatic-renewal status, renewal reminders, billing-correction and chargeback procedure, member-rate consequences for separately purchased events, and the treatment of processor fees.</p>
HTML,
			),
			'data-retention-account-closure-policy-draft' => array(
				'title'   => 'Data Retention and Account Closure Policy (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Account closure</h2>
<p>An account-closure request starts a seven-day cooling-off period measured from the request, not from the user's last sign-in. The user may withdraw the request during that period. Afterward an authorized administrator reviews the request. The system does not automatically erase an active membership, event registration, order, refund, moderation record, governance record or record CYWater must retain.</p>
<h2>Closure, cancellation and deletion are different</h2>
<p>Signing out ends a browser session. Cancelling a membership ends future renewal but does not refund the current membership term, and preserves the account and transaction history. An event refund or membership billing correction follows the Billing, Cancellation and Refund Policy. Account closure removes or anonymizes eligible profile and account data only after review; it does not reverse a transaction or destroy another person's or the association's required records.</p>
<h2>Proposed retention schedule</h2>
<ul><li><strong>Account and public profile:</strong> remove or anonymize after approved closure when no overriding operational or legal need remains.</li><li><strong>Payments, orders, refunds and accounting evidence:</strong> seven years after the relevant transaction or fiscal year, subject to accountant and legal confirmation.</li><li><strong>Membership history and governance decisions:</strong> retain the minimum record needed to document eligibility, benefits, votes and association actions; remove unnecessary profile detail when no longer needed.</li><li><strong>Event registrations:</strong> three years after the event unless a longer accounting, safety, grant, dispute or legal need applies.</li><li><strong>Forum and moderation records:</strong> retain published content while it remains useful to the community; retain restricted moderation and abuse evidence only as long as reasonably needed for safety, appeals or legal claims.</li><li><strong>Partnership and MOU records:</strong> seven years after the relationship ends, subject to the signed agreement and accounting requirements.</li><li><strong>Logo Call:</strong> delete non-selected files twelve months after final results unless a dispute or legal hold applies; retain selected work, approvals and signed rights records while CYWater uses or protects the design.</li><li><strong>Security and delivery logs:</strong> normally up to twelve months unless needed to investigate abuse, fraud or delivery failures.</li><li><strong>Backups:</strong> expire under the hosting backup rotation and are not edited solely to remove one live record. Deleted live data may remain in protected backups until that rotation expires.</li></ul>
<h2>Deletion safeguards</h2>
<p>CYWater verifies the requester, scopes deletion to that person, checks for active services and retention obligations, and records the decision without keeping unnecessary personal detail. Deletion must not damage another person's records. Where deletion is not permitted, access should be restricted and the retained data minimized. A legal hold, active dispute, suspected fraud or security investigation pauses deletion of relevant records.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> all retention periods with the accountant and counsel, inactive unpaid-account handling, forum-content treatment, legal holds, backup rotation, responsible role, and the final identity-verification and appeal process.</p>
HTML,
			),
		);
	}
}
