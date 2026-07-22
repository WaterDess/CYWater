<?php
/**
 * Event detail.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	$date     = get_post_meta( get_the_ID(), '_cyw_date_label', true ) ?: get_post_meta( get_the_ID(), '_cyw_start_date', true );
	$location = get_post_meta( get_the_ID(), '_cyw_location', true );
	get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => 'CYWater event', 'title' => get_the_title(), 'lead' => trim( $date . ( $location ? ' · ' . $location : '' ) ) ) );
	?>
	<article class="section"><div class="container container-narrow">
		<?php if ( cywater_featured_image_url( get_the_ID(), 'cywater-wide' ) ) : ?><div class="media-frame" data-reveal><img src="<?php echo esc_url( cywater_featured_image_url( get_the_ID(), 'cywater-wide' ) ); ?>" alt="" loading="eager"></div><?php endif; ?>
		<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
		<?php edit_post_link( 'Edit this event', '<span class="admin-edit-link">', '</span>' ); ?>
	</div></article>
	<?php
endwhile;
get_footer();
