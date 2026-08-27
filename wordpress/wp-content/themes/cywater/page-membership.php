<?php
/**
 * Membership page.
 *
 * @package CYWater
 */
get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	$checkout_page_id = absint( get_option( 'pmpro_checkout_page_id' ) );
	$checkout_url     = $checkout_page_id ? get_permalink( $checkout_page_id ) : home_url( '/membership-checkout/' );
	$level_ids        = (array) get_option( 'cywater_membership_level_ids', array() );
	$partner_page_id  = absint( get_option( 'cywater_partner_guide_page_id' ) );
	$partner_url      = $partner_page_id ? get_permalink( $partner_page_id ) : home_url( '/become-a-partner/' );
	$payments_available = class_exists( 'CYWater_Config' )
		&& (
			( 'production' !== wp_get_environment_type() && 'test' === CYWater_Config::payment_mode() )
			|| CYWater_Config::live_payments_allowed()
		);
	$sandbox_test_enabled = 'staging' === wp_get_environment_type()
		&& 'sandbox' === get_option( 'pmpro_gateway_environment' )
		&& class_exists( 'CYWater_Config' )
		&& 'test' === CYWater_Config::payment_mode()
		&& ! empty( $level_ids['sandbox_test'] );
	$checkout_urls    = array();
	foreach ( array( 'student', 'professional', 'lifetime', 'sandbox_test' ) as $level_key ) {
		if ( ! empty( $level_ids[ $level_key ] ) ) {
			$checkout_urls[ $level_key ] = add_query_arg( 'level', absint( $level_ids[ $level_key ] ), $checkout_url );
		}
	}
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => 'Membership',
			'title'    => 'Membership and partnerships.',
			'lead'     => 'Individual membership is open worldwide to people engaged in or interested in water sciences. Institutions and organizations may apply separately to become CYWater partners.',
		)
	);
	?>
	<section class="section">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center">Benefits</span><h2>What membership offers.</h2></div>
			<div class="benefits-grid grid-3">
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg></div><h3 style="font-size:var(--fs-md)">Annual meetings</h3><p>Priority notice and access to CYWater Annual Meetings and the Annual Gathering during the AGU Fall Meeting.</p></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4Z"/><path d="M8 9h8M8 13h5"/></svg></div><h3 style="font-size:var(--fs-md)">Best Paper Award</h3><p>Eligibility for the Young Scientist Best Paper Award (under-35 first authors).</p></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><circle cx="17" cy="10" r="2.5"/></svg></div><h3 style="font-size:var(--fs-md)">Global community</h3><p>A worldwide network connecting students, researchers, and practitioners in water sciences.</p></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v11H9l-5 4Z"/><path d="M8 9h8M8 12h5"/></svg></div><h3 style="font-size:var(--fs-md)">Member Forum</h3><p>Verified active members can publish their own Forum articles directly and join discussions.</p></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg></div><h3 style="font-size:var(--fs-md)">Career opportunities</h3><p>Job and position notices shared through the community channel.</p></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></div><h3 style="font-size:var(--fs-md)">Recognition</h3><p>Member recognition in community activities and award ceremonies.</p></div>
			</div>
		</div>
	</section>

	<section class="section section-tint membership-dues">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center">Full-year membership</span><h2>Membership types and dues.</h2><p class="lead">Compare the options and choose the membership that fits how you participate in CYWater.</p></div>
			<div class="tiers<?php echo $sandbox_test_enabled ? ' has-sandbox-test' : ''; ?>">
				<article class="tier" data-plan-card data-reveal><div class="tier-heading"><h3 class="tier-title">Student</h3></div><p class="tier-tag">For full-time students in water-related fields.</p><div class="tier-price">$20 <small>/ year</small></div><p class="tier-cycle">One full year from payment</p><div class="tier-features"><span>Individual annual membership</span><span>Student member conference rates</span><span>Undergraduate, graduate, or Ph.D. eligibility</span></div><?php if ( $payments_available ) : ?><a class="btn btn-outline btn-block tier-action" href="<?php echo esc_url( $checkout_urls['student'] ?? $checkout_url ); ?>">Join as Student</a><?php else : ?><span class="btn btn-outline btn-block tier-action is-disabled" aria-disabled="true">Payments opening shortly</span><?php endif; ?></article>
				<article class="tier" data-plan-card data-reveal><div class="tier-heading"><h3 class="tier-title">Professional</h3><span class="badge badge-teal">Standard</span></div><p class="tier-tag">For researchers and practitioners in water sciences.</p><div class="tier-price">$50 <small>/ year</small></div><p class="tier-cycle">One full year from payment</p><div class="tier-features"><span>Individual annual membership</span><span>Professional member conference rates</span><span>CYWater community participation</span></div><?php if ( $payments_available ) : ?><a class="btn btn-accent btn-block tier-action" href="<?php echo esc_url( $checkout_urls['professional'] ?? $checkout_url ); ?>">Join as Professional</a><?php else : ?><span class="btn btn-accent btn-block tier-action is-disabled" aria-disabled="true">Payments opening shortly</span><?php endif; ?></article>
				<article class="tier" data-plan-card data-reveal><div class="tier-heading"><h3 class="tier-title">Lifetime</h3></div><p class="tier-tag">For long-term individual participation in CYWater.</p><div class="tier-price">$700 <small>one time</small></div><p class="tier-cycle">Lifetime membership</p><div class="tier-features"><span>Individual lifetime membership</span><span>No annual dues renewal</span><span>CYWater community participation</span></div><?php if ( $payments_available ) : ?><a class="btn btn-outline btn-block tier-action" href="<?php echo esc_url( $checkout_urls['lifetime'] ?? $checkout_url ); ?>">Join as Lifetime Member</a><?php else : ?><span class="btn btn-outline btn-block tier-action is-disabled" aria-disabled="true">Payments opening shortly</span><?php endif; ?></article>
				<?php if ( $sandbox_test_enabled ) : ?>
					<article class="tier tier--sandbox" data-plan-card data-reveal><div class="tier-heading"><h3 class="tier-title">Payment test</h3><span class="badge badge-gold">Staging only</span></div><p class="tier-tag">Run the real PMPro and Stripe Sandbox checkout path without moving real funds.</p><div class="tier-price">$0.50 <small>one time</small></div><p class="tier-cycle">Expires after one day</p><div class="tier-features"><span>No real charge</span><span>Does not grant member benefits</span><span>Safe alongside an existing membership</span></div><a class="btn btn-outline btn-block tier-action" href="<?php echo esc_url( $checkout_urls['sandbox_test'] ); ?>">Test Sandbox payment</a></article>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="section membership-partners" id="partnerships"><div class="container"><div class="section-head center membership-partner-head" data-reveal><span class="eyebrow center">Sponsors and partners</span><h2>Support emerging water scholars.</h2><p class="lead">Institutional partnership is separate from individual membership and begins with a Board-reviewed expression of interest.</p></div><div class="partner-overview" data-reveal><div class="membership-partner-copy"><p>CYWater welcomes universities, research institutes, industries, professional societies, and foundations that share our commitment to advancing water science education, research, conferences, and early-career development.</p><p>Approved partners may receive agreed recognition on the CYWater website and relevant association materials. Recognition scope, term, logo use, and links are documented in a memorandum of understanding and do not imply product endorsement.</p><ul class="partner-benefits"><li>Website recognition as an approved CYWater partner</li><li>Visibility alongside supported scientific and educational activities</li><li>Connection with CYWater's international water-science community</li></ul></div><aside class="partner-cta"><span class="badge badge-gold">Organization</span><h3>Annual partnership</h3><div class="tier-price">$1,000 <small>/ year</small></div><p>No payment is requested until Board approval and MOU completion.</p><a class="btn btn-accent btn-block" href="<?php echo esc_url( $partner_url ); ?>">Become Our Partner</a></aside></div></div></section>

	<section class="section"><div class="container container-narrow"><div class="section-head center" data-reveal><span class="eyebrow center">Membership details</span><h2>Frequently asked questions.</h2></div><div data-reveal>
		<div class="faq-item"><h3><button class="faq-q" id="membership-faq-q-1" type="button" aria-expanded="false" aria-controls="membership-faq-a-1">How do I apply?</button></h3><div class="faq-a" id="membership-faq-a-1" role="region" aria-labelledby="membership-faq-q-1" aria-hidden="true"><p>Choose a membership type, sign in or create an account, then complete the professional information and secure payment steps.</p></div></div>
		<div class="faq-item"><h3><button class="faq-q" id="membership-faq-q-2" type="button" aria-expanded="false" aria-controls="membership-faq-a-2">Who can join?</button></h3><div class="faq-a" id="membership-faq-a-2" role="region" aria-labelledby="membership-faq-q-2" aria-hidden="true"><p>Membership is open worldwide to individuals who support CYWater's objectives and are engaged in or interested in water sciences, water resources, or related disciplines.</p></div></div>
		<div class="faq-item"><h3><button class="faq-q" id="membership-faq-q-3" type="button" aria-expanded="false" aria-controls="membership-faq-a-3">Who qualifies for student membership?</button></h3><div class="faq-a" id="membership-faq-a-3" role="region" aria-labelledby="membership-faq-q-3" aria-hidden="true"><p>Full-time undergraduate, graduate, and Ph.D. students are eligible for student membership.</p></div></div>
		<div class="faq-item"><h3><button class="faq-q" id="membership-faq-q-4" type="button" aria-expanded="false" aria-controls="membership-faq-a-4">What is the membership period?</button></h3><div class="faq-a" id="membership-faq-a-4" role="region" aria-labelledby="membership-faq-q-4" aria-hidden="true"><p>Student and Professional membership lasts for one full year from the successful payment date. Lifetime membership does not expire.</p></div></div>
	</div></div></section>
	<?php
endwhile;
?>
</main>
<?php
get_footer();
