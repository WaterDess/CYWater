<?php
/**
 * Partner application guide.
 *
 * @package CYWater
 */
get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => 'Institutional partnerships',
			'title'    => get_the_title(),
			'lead'     => 'Submit an expression of interest for Board and MOU review. Partnership is separate from individual membership, and no payment is requested before approval.',
		)
	);
	?>
	<section><div class="entry-content"><?php the_content(); ?></div></section>
	<?php
endwhile;
?>
</main>
<?php get_footer(); ?>
