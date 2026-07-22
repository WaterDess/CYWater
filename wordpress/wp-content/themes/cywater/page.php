<?php
/**
 * Standard editable page.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero' );
	?>
	<section class="section">
		<div class="container container-narrow">
			<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
			<?php edit_post_link( __( 'Edit this page', 'cywater' ), '<span class="admin-edit-link">', '</span>' ); ?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
