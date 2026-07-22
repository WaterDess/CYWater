<?php
/**
 * Award detail.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => 'Best Paper Award · ' . get_post_meta( get_the_ID(), '_cyw_year', true ), 'title' => get_the_title(), 'lead' => get_the_excerpt() ) );
	?>
	<article class="section"><div class="container container-narrow"><div class="prose entry-content" data-reveal><?php the_content(); ?></div><?php edit_post_link( 'Edit this award', '<span class="admin-edit-link">', '</span>' ); ?></div></article>
	<?php
endwhile;
get_footer();
