<?php
/**
 * Contact page.
 *
 * @package CYWater
 */
get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	$contact_email   = cywater_page_field( 'contact_email', 'contact@cywater.org' );
	$mailing_address = cywater_page_field( 'mailing_address', "202 E. Green St. Suite 2\nChampaign, IL 61820, USA" );
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => 'Contact',
			'title'    => 'Get in touch.',
			'lead'     => 'Contact CYWater for general correspondence, events, partnerships, and media inquiries.',
		)
	);
	?>
	<section class="section">
		<div class="container">
			<div class="contact-layout">
				<div data-reveal>
					<div class="contact-info-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
						<div><div class="label">Email</div><div class="val"><a href="mailto:<?php echo esc_attr( $contact_email ?: 'contact@cywater.org' ); ?>"><?php echo esc_html( $contact_email ?: 'contact@cywater.org' ); ?></a></div></div>
					</div>
					<div class="contact-info-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
						<div><div class="label">Mailing address</div><div class="val"><?php echo wp_kses( nl2br( esc_html( $mailing_address ) ), array( 'br' => array() ) ); ?></div></div>
					</div>
					<div class="contact-info-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
						<div><div class="label">Response</div><div class="val">Messages are reviewed by the CYWater team</div></div>
					</div>
					<hr class="divider" style="margin:var(--sp-6) 0">
					<div class="media-frame"><img src="<?php echo esc_url( cywater_featured_image_url( get_the_ID(), 'full', 'contact/cywater-community-group.jpg' ) ); ?>" alt="CYWater members gathered for a group photograph" width="1259" height="511" loading="lazy" style="display:block"></div>
				</div>
				<div class="contact-note" data-reveal>
					<span class="eyebrow">Official contact</span>
					<h2 style="margin-top:var(--sp-3)">How can we help?</h2>
					<p class="lead" style="margin-top:var(--sp-4)">Use <a href="mailto:contact@cywater.org">contact@cywater.org</a> for general correspondence, events, partnerships, and media inquiries.</p>
					<p style="margin-top:var(--sp-4)">For membership support, write to <a href="mailto:membership@cywater.org">membership@cywater.org</a>. For billing, renewals, and invoices, write to <a href="mailto:billing@cywater.org">billing@cywater.org</a>.</p>
				</div>
			</div>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
