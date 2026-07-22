<?php
/**
 * Membership page with PMPro-backed direct checkout actions.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => cywater_page_field( 'eyebrow', 'Membership' ), 'title' => get_the_title(), 'lead' => cywater_page_field( 'lead', 'Join an international community advancing water sciences and supporting emerging scholars.' ) ) );
	?>
	<section class="section-tight">
		<div class="container container-narrow">
			<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
		</div>
	</section>

	<section class="section"><div class="container">
		<div class="section-head center" data-reveal><span class="eyebrow center">Benefits</span><h2>What membership offers.</h2></div>
		<div class="benefits-grid grid-3">
			<?php foreach ( array(
				'Annual meetings' => 'Priority notice and access to CYWater Annual Meetings and the Annual Gathering during the AGU Fall Meeting.',
				'Best Paper Award' => 'Eligibility for the Young Scientist Best Paper Award for qualifying early-career first authors.',
				'Global community' => 'A worldwide network connecting students, researchers, practitioners, and partner institutions.',
				'Newsletter archive' => 'Access to CYWater community news and historical updates.',
				'Career opportunities' => 'Job and position notices shared through the community channel.',
				'Recognition' => 'Member recognition in community activities and award ceremonies.',
			) as $heading => $copy ) : ?>
				<div class="feature" data-reveal><h3 style="font-size:var(--fs-md)"><?php echo esc_html( $heading ); ?></h3><p><?php echo esc_html( $copy ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div></section>

	<section class="section section-tint membership-dues">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center">Calendar-year membership</span><h2>Membership types and dues.</h2><p class="lead">Choose a level to continue directly to secure checkout.<?php if ( 'production' !== wp_get_environment_type() ) : ?> This environment uses test mode only.<?php endif; ?></p></div>
			<div class="tiers">
				<?php
				$levels = function_exists( 'pmpro_getAllLevels' ) ? pmpro_getAllLevels( true, true ) : array();
				if ( $levels ) {
					usort( $levels, static fn( $a, $b ) => (float) $a->initial_payment <=> (float) $b->initial_payment );
				}
				$fallbacks = array(
					(object) array( 'id' => 0, 'name' => 'Student', 'initial_payment' => 20, 'billing_amount' => 0, 'cycle_number' => 0, 'description' => 'For full-time students in water-related fields.' ),
					(object) array( 'id' => 0, 'name' => 'Professional', 'initial_payment' => 70, 'billing_amount' => 0, 'cycle_number' => 0, 'description' => 'For researchers and practitioners in water sciences.' ),
					(object) array( 'id' => 0, 'name' => 'Lifetime', 'initial_payment' => 700, 'billing_amount' => 0, 'cycle_number' => 0, 'description' => 'For long-term individual participation in CYWater.' ),
					(object) array( 'id' => 0, 'name' => 'Partner', 'initial_payment' => 1000, 'billing_amount' => 0, 'cycle_number' => 0, 'description' => 'For institutions supporting CYWater programs.' ),
				);
				foreach ( $levels ?: $fallbacks as $level ) :
					$is_standard = 'Professional' === $level->name;
					$is_partner  = 'Partner' === $level->name;
					$checkout    = $level->id && function_exists( 'pmpro_url' ) ? pmpro_url( 'checkout', '?level=' . absint( $level->id ) ) : '#membership-not-ready';
					?>
					<article class="tier <?php echo $is_partner ? 'tier-partner' : ''; ?>" data-reveal>
						<div class="tier-heading"><h3 class="tier-title"><?php echo esc_html( $level->name ); ?></h3><?php if ( $is_standard ) : ?><span class="badge badge-teal">Standard</span><?php elseif ( $is_partner ) : ?><span class="badge badge-gold">Organization</span><?php endif; ?></div>
						<p class="tier-tag"><?php echo wp_kses_post( wp_strip_all_tags( $level->description ) ); ?></p>
						<div class="tier-price">$<?php echo esc_html( number_format_i18n( (float) $level->initial_payment, 0 ) ); ?> <small><?php echo 'Lifetime' === $level->name ? 'one time' : '/ year'; ?></small></div>
						<p class="tier-cycle"><?php echo 'Lifetime' === $level->name ? 'Lifetime membership' : 'Calendar-year membership'; ?></p>
						<div class="tier-features"><span>CYWater community participation</span><span>Member communications and opportunities</span><span><?php echo 'Student' === $level->name ? 'Student eligibility verification' : 'Membership profile access'; ?></span></div>
						<a class="btn <?php echo $is_standard ? 'btn-accent' : 'btn-outline'; ?> btn-block tier-action" href="<?php echo esc_url( $checkout ); ?>">Join as <?php echo esc_html( $level->name ); ?></a>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $levels ) : ?><div id="membership-not-ready" class="prototype-notice"><strong>Membership checkout is temporarily unavailable.</strong><span><?php echo 'production' === wp_get_environment_type() ? 'Please check back later.' : 'Activate Paid Memberships Pro and run the CYWater setup command.'; ?></span></div><?php endif; ?>
		</div>
	</section>

	<section class="section" id="partnerships"><div class="container"><div class="about-intro-grid membership-partner-grid"><div data-reveal><span class="eyebrow">Sponsors and partners</span><h2 style="margin-top:var(--sp-3)">Support emerging water scholars.</h2></div><div class="stack" data-reveal><p>CYWater welcomes universities, research institutes, companies, professional societies, and foundations that share its mission.</p><p>Partnership arrangements remain subject to organizational review before production checkout is enabled.</p></div></div></div></section>

	<section class="section section-tint"><div class="container container-narrow">
		<div class="section-head" data-reveal><span class="eyebrow">Conference fees</span><h2>Abstract and registration fees.</h2><p class="lead">Fees support abstract review, program development, meeting logistics, publication and communication costs, and student and early-career activities.</p></div>
		<div class="table-wrap" data-reveal><table class="table fee-table"><caption class="visually-hidden">CYWater conference abstract and registration fees</caption><thead><tr><th scope="col">Registration type</th><th scope="col">Abstract fee</th><th scope="col">Early registration</th><th scope="col">Standard registration</th></tr></thead><tbody><tr><th scope="row">Professional Member</th><td>$25 per abstract</td><td>$500</td><td>$600</td></tr><tr><th scope="row">Student Member</th><td>$25 per abstract</td><td>$250</td><td>$300</td></tr><tr><th scope="row">Nonmember</th><td>$25 per abstract</td><td>$600</td><td>$700</td></tr></tbody></table></div>
	</div></section>

	<section class="section"><div class="container container-narrow"><div class="section-head center" data-reveal><span class="eyebrow center">Membership details</span><h2>Frequently asked questions.</h2></div><div class="prose" data-reveal>
		<h3>How do I apply?</h3><p>Choose a membership level above. Test environments use sandbox checkout only; production applications remain disabled until organizational approval and live account verification are complete.</p>
		<h3>Who can join?</h3><p>Membership is open worldwide to individuals who support CYWater's objectives and are engaged in or interested in water sciences, water resources, or related disciplines.</p>
		<h3>Who qualifies for student membership?</h3><p>Full-time undergraduate, graduate, and Ph.D. students are eligible for student membership.</p>
		<h3>What is the membership period?</h3><p>Annual membership follows the calendar year. The Board must approve the launch policy for mid-year applications before live checkout is enabled.</p>
	</div></div></section>
	<?php
endwhile;
get_footer();
