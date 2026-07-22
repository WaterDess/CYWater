<?php
/**
 * News article.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	$categories = get_the_category();
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => $categories ? $categories[0]->name : __( 'News', 'cywater' ),
			'title'    => get_the_title(),
			'lead'     => get_the_excerpt(),
		)
	);
	?>
	<article class="section">
		<div class="container container-narrow">
			<?php if ( cywater_featured_image_url( get_the_ID(), 'cywater-wide' ) ) : ?>
				<div class="media-frame" data-reveal><img src="<?php echo esc_url( cywater_featured_image_url( get_the_ID(), 'cywater-wide' ) ); ?>" alt="" loading="eager"></div>
			<?php endif; ?>
			<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
			<?php edit_post_link( __( 'Edit this article', 'cywater' ), '<span class="admin-edit-link">', '</span>' ); ?>
		</div>
	</article>
	<?php
endwhile;
get_footer();
