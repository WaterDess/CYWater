<?php
/**
 * Not found page.
 *
 * @package CYWater
 */
get_header();
?>
<main>
<?php
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => __( '404', 'cywater' ),
		'title'    => __( 'This page could not be found.', 'cywater' ),
		'lead'     => __( 'The link may be outdated, the page may have moved, or the content may no longer be public.', 'cywater' ),
	)
);
?>
<section class="section">
	<div class="container container-narrow"><div class="hero-actions"><a class="btn btn-accent" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'cywater' ); ?></a><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact CYWater', 'cywater' ); ?></a></div></div>
</section>
</main>
<?php get_footer(); ?>
