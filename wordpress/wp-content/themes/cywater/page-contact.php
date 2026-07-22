<?php
/**
 * Contact page.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	$contact_email  = cywater_page_field( 'contact_email', 'To be confirmed' );
	$mailing_address = cywater_page_field( 'mailing_address', "202 E. Green St. Suite 2\nChampaign, IL 61820, USA" );
	get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => cywater_page_field( 'eyebrow', 'Contact' ), 'title' => get_the_title(), 'lead' => cywater_page_field( 'lead', 'CYWater\'s official contact email is being confirmed. The mailing address below may be used for formal correspondence.' ) ) );
	?>
	<section class="section"><div class="container"><div class="contact-layout">
		<div data-reveal>
			<div class="contact-info-item"><div><div class="label">Email</div><div class="val"><?php echo esc_html( $contact_email ?: 'To be confirmed' ); ?></div></div></div>
			<div class="contact-info-item"><div><div class="label">Mailing address</div><div class="val"><?php echo wp_kses( nl2br( esc_html( $mailing_address ) ), array( 'br' => array() ) ); ?></div></div></div>
			<hr class="divider" style="margin:var(--sp-6) 0">
			<div class="media-frame"><img src="<?php echo esc_url( cywater_featured_image_url( get_the_ID(), 'cywater-wide', 'contact/cywater-community-group.jpg' ) ); ?>" alt="CYWater members gathered for a group photograph" width="1259" height="511" loading="lazy"></div>
		</div>
		<div class="contact-note prose entry-content" data-reveal><?php the_content(); ?></div>
	</div></div></section>
	<?php
endwhile;
get_footer();
