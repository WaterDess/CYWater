<?php
/**
 * Fallback template.
 *
 * @package CYWater
 */
get_header();
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => __( 'CYWater', 'cywater' ),
		'title'    => get_the_archive_title() ?: __( 'Latest updates', 'cywater' ),
		'lead'     => get_the_archive_description(),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="grid grid-3">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content-card' ); endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php get_footer(); ?>
