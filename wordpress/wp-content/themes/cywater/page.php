<?php
/**
 * Standard editable page.
 *
 * @package CYWater
 */
get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero' );
	?>
	<section class="section">
		<div class="container container-narrow">
			<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
