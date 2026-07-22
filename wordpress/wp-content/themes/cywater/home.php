<?php
/**
 * News index.
 *
 * @package CYWater
 */
get_header();
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => __( 'News and updates', 'cywater' ),
		'title'    => __( 'Opportunities and spotlights.', 'cywater' ),
		'lead'     => __( 'Association announcements, meeting updates, awards, and community stories.', 'cywater' ),
	)
);
?>
<section class="section">
	<div class="container container-narrow">
		<div class="news-list">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/news-row' ); endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php get_footer(); ?>
