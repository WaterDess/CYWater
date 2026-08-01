<?php
/**
 * Award record detail.
 *
 * @package CYWater
 */

get_header();
while ( have_posts() ) :
	the_post();
	$year = get_post_meta( get_the_ID(), '_cyw_year', true );
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => 'Best Paper Award · ' . $year,
			'title'    => get_the_title(),
			'lead'     => get_the_excerpt(),
		)
	);
	?>
	<article class="section"><div class="container container-narrow"><div class="article-body"><div class="prose entry-content" data-reveal><?php echo cywater_article_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div style="text-align:center; margin-top:var(--sp-6)"><a class="link" href="<?php echo esc_url( get_post_type_archive_link( 'cyw_award' ) ); ?>#award-<?php echo esc_attr( $year ); ?>">Back to Awards</a></div><?php edit_post_link( 'Edit this award', '<span class="admin-edit-link">', '</span>' ); ?></div></div></article>
	<?php
endwhile;
get_footer();
