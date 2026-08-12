<?php
/**
 * Non-destructive creation of editable launch-policy drafts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Policy_Drafts {
	const META_KEY = '_cywater_board_review_policy';

	/**
	 * Create missing drafts without overwriting later editorial work.
	 *
	 * @return array<string,int>
	 */
	public static function ensure() {
		$ids = array();
		foreach ( self::definitions() as $slug => $definition ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
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
					'meta_input'   => array( self::META_KEY => '1' ),
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
<h2>Who we are</h2>
<p>CYWater is the International Association of Contemporary Young Scholars in Water Sciences. This notice describes how CYWater proposes to handle personal information collected through cywater.org and related association services.</p>
<h2>Information we collect</h2>
<ul><li>Account and contact information, including email verification and sign-in records.</li><li>Professional profile, membership level, membership dates, directory and field-level privacy choices.</li><li>Orders, refunds, renewal status and limited payment references. CYWater does not receive or store full payment-card numbers; payment processing is provided by Stripe.</li><li>Event registrations and attendance records; partnership expressions of interest and review status; Logo Call submissions and eligibility-limited votes.</li><li>Messages sent through contact forms and operational data such as IP-derived security signals, browser information and server logs.</li></ul>
<h2>How we use information</h2>
<p>We use information to operate accounts and membership, verify eligibility, process payments and refunds, administer events and association programs, respond to requests, prevent abuse, meet accounting and legal duties, and—with explicit member choices—publish an opt-in member directory.</p>
<h2>Service providers</h2>
<p>Proposed providers include Hostinger and WordPress for hosting, Paid Memberships Pro for membership records, Stripe for payments, Postmark for transactional delivery, and Google Workspace for authorized association communications. Each provider processes information under its own terms and security practices.</p>
<h2>Sharing and publication</h2>
<p>CYWater does not sell personal information. A directory profile is public only when an eligible member opts in, and only fields individually selected for publication are shown. We may disclose information to service providers, authorized association personnel, or when required by law.</p>
<h2>Choices and rights</h2>
<p>Members can edit profile and directory choices, request a WordPress data export, and submit an account-closure request. Some identity, payment, refund, event, governance, rights-assignment and security records may be retained when required for legitimate association, accounting, fraud-prevention or legal purposes.</p>
<h2>Contact</h2>
<p>Privacy and membership questions: membership@cywater.org. General correspondence: contact@cywater.org.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> effective date, legal basis and jurisdiction-specific disclosures, cookie/analytics inventory, service-provider agreements, international-transfer language, youth/minimum-age rule, and final request-response procedure.</p>
HTML,
			),
			'terms-of-use-draft' => array(
				'title'   => 'Terms of Use (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Use of CYWater services</h2>
<p>Users must provide accurate information, keep access credentials secure, and use CYWater services only for lawful association, scientific, educational and professional purposes. Automated abuse, impersonation, interference with the site, unauthorized access and infringement of third-party rights are prohibited.</p>
<h2>Accounts and membership</h2>
<p>An account is not itself a paid membership. Membership begins only after the applicable payment or authorized administrative action is recorded. Eligibility, dues, benefits and end dates are shown at checkout and in the member account. Institutional partnership is a separate Board/MOU process and is not individual membership.</p>
<h2>Member and event content</h2>
<p>Users retain rights in content they submit except for rights expressly granted for a specific program. Users warrant that submissions are lawful and that they have permission to provide them. CYWater may moderate, reject or remove content that violates published rules.</p>
<h2>Logo Call</h2>
<p>Logo Call entrants retain rights in non-selected work. Submission grants CYWater a limited, non-exclusive right to review and display the work for administration and voting. Selection does not itself transfer ownership: before permanent use, modification or trademark registration, the selected entrant and CYWater must sign a separate written rights assignment or license approved by the Board.</p>
<h2>Availability and third parties</h2>
<p>CYWater may update, suspend or discontinue services for maintenance, safety or organizational needs. Third-party payment, email and hosting services remain subject to their own terms. The site is provided on a reasonable-efforts basis to the extent permitted by law.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> governing law and venue, minimum age, acceptable-use enforcement and appeals, liability language, copyright-agent procedure, and notice/change process.</p>
HTML,
			),
			'billing-cancellation-refund-policy-draft' => array(
				'title'   => 'Billing, Cancellation and Refund Policy (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Clear checkout terms</h2>
<p>Before payment, checkout must show the item or membership, price, currency, term, benefits or registration covered, and whether payment is one-time or automatically recurring. CYWater must not enable automatic renewal unless the checkout clearly discloses the amount, frequency, cancellation method and renewal timing and records the payer's affirmative consent.</p>
<h2>Membership payments</h2>
<p>Individual membership dates and amounts are those displayed at checkout and recorded in the member account. A full refund of a membership payment cancels only the membership level funded by that payment and stops its matching renewal subscription, if any. A refund must not cancel an unrelated event registration or other service.</p>
<h2>Event and program payments</h2>
<p>Each paid event or program must publish its own cancellation deadline, refund schedule, transfer policy and capacity rules before registration opens. A refund cancels only the matching registration or entitlement. If CYWater cancels an event, the event-specific notice will state whether fees are refunded, credited or transferred.</p>
<h2>How to request cancellation or a refund</h2>
<p>Requests should be sent to billing@cywater.org from the account email and identify the relevant order or registration without including card details. Approved refunds return to the original payment method where possible. Processing time can depend on Stripe and the payer's financial institution.</p>
<h2>Disputes and exceptional cases</h2>
<p>Duplicate charges, technical errors, fraud claims and exceptional hardship may be reviewed individually. Partnership payments are governed by the approved MOU and invoice terms. Nothing in this draft limits non-waivable consumer rights.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> ordinary membership refund window, event refund schedule template, renewal reminder timing, grace periods, partial-refund authority, chargeback procedure, and who may approve exceptions.</p>
HTML,
			),
			'data-retention-account-closure-policy-draft' => array(
				'title'   => 'Data Retention and Account Closure Policy (Draft for Board Review)',
				'content' => <<<'HTML'
<h2>Account closure</h2>
<p>An account-closure request starts a seven-day cooling-off period measured from the request. The member may withdraw the request during that period. Afterward an authorized administrator reviews the request; the system does not automatically erase active memberships, event registrations, refunds, payment records or legally required records.</p>
<h2>Proposed retention schedule</h2>
<ul><li><strong>Account and public profile:</strong> remove or anonymize after approved closure when no overriding obligation remains.</li><li><strong>Payments, orders, refunds and accounting evidence:</strong> seven years after the relevant transaction or fiscal year, subject to accountant/legal confirmation.</li><li><strong>Membership history and governance decisions:</strong> retain as needed to document eligibility, benefits, votes and association actions; minimize profile details when no longer needed.</li><li><strong>Event registrations:</strong> three years after the event unless a longer accounting, safety, grant or legal need applies.</li><li><strong>Partnership/MOU records:</strong> seven years after the relationship ends, subject to the signed agreement.</li><li><strong>Logo Call:</strong> non-selected files proposed for deletion twelve months after final results; selected work, approvals and signed rights records retained for as long as CYWater uses or protects the design.</li><li><strong>Security and delivery logs:</strong> normally up to twelve months unless needed to investigate abuse or delivery failures.</li><li><strong>Backups:</strong> expire under the hosting backup rotation and are not edited solely to remove one live record.</li></ul>
<h2>Deletion safeguards</h2>
<p>Deletion must be scoped to the requesting person, reviewed by an authorized administrator, logged without retaining unnecessary personal details, and must not damage another person's records. Where deletion is not permitted, access should be restricted and data minimized.</p>
<h2>Board decisions before publication</h2>
<p><strong>Confirm:</strong> retention periods with the accountant and counsel, inactive-account handling, legal holds, backup rotation, responsible role, and the final identity-verification process for privacy requests.</p>
HTML,
			),
		);
	}
}
