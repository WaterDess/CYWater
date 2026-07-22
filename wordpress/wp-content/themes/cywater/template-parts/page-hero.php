<?php
/**
 * Shared page hero.
 *
 * @package CYWater
 */
$eyebrow = $args['eyebrow'] ?? cywater_page_field( 'eyebrow', '' );
$title    = $args['title'] ?? get_the_title();
$lead     = $args['lead'] ?? cywater_page_field( 'lead', get_the_excerpt() );
?>
<section class="page-hero">
	<div class="container">
		<?php if ( ! empty( $args['breadcrumbs'] ) ) : ?>
			<div class="crumbs"><?php echo wp_kses_post( $args['breadcrumbs'] ); ?></div>
		<?php endif; ?>
		<?php if ( $eyebrow ) : ?>
			<span class="eyebrow" style="color:var(--teal-soft)"><?php echo esc_html( $eyebrow ); ?></span>
		<?php endif; ?>
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( $lead ) : ?>
			<p class="lead"><?php echo esc_html( $lead ); ?></p>
		<?php endif; ?>
	</div>
</section>
