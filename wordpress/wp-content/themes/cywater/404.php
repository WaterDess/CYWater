<?php
/**
 * Not found page.
 *
 * @package CYWater
 */
get_header();
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => __( '404', 'cywater' ),
		'title'    => __( 'This page could not be found.', 'cywater' ),
		'lead'     => __( 'The address may have changed during the WordPress migration.', 'cywater' ),
	)
);
?>
<section class="section">
	<div class="container container-narrow"><a class="btn btn-accent" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'cywater' ); ?></a></div>
</section>
<?php get_footer(); ?>
