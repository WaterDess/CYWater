<?php
/**
 * News index.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => 'News',
		'title'    => 'Opportunities and spotlights.',
		'lead'     => 'Verified opportunities for the water-science community, together with research, event, and award spotlights from CYWater.',
	)
);

$spotlights = new WP_Query(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);
?>
<section class="section">
	<div class="container container-narrow">
		<section id="opportunities" aria-labelledby="opportunities-title">
			<div class="section-head" data-reveal="section">
				<span class="eyebrow">Open calls and positions</span>
				<h2 id="opportunities-title">Opportunities</h2>
			</div>
			<div class="opportunities-empty" data-reveal>
				<strong>No verified opportunities are currently published.</strong>
				<p>Historical listings are being reviewed before republication so that closed or outdated positions are not presented as active.</p>
			</div>
		</section>

		<section id="spotlights" class="news-section-block" aria-labelledby="spotlights-title">
			<div class="section-head" data-reveal="section">
				<span class="eyebrow">Research and community</span>
				<h2 id="spotlights-title">Spotlights</h2>
			</div>
			<?php if ( $spotlights->have_posts() ) : ?>
				<div>
					<?php
					$index = 0;
					while ( $spotlights->have_posts() ) :
						$spotlights->the_post();
						if ( 1 === $index ) {
							echo '<div class="news-list">';
						}
						get_template_part( 'template-parts/news-row', null, array( 'featured' => 0 === $index ) );
						++$index;
					endwhile;
					if ( $index > 1 ) {
						echo '</div>';
					}
					wp_reset_postdata();
					?>
				</div>
			<?php endif; ?>
		</section>
	</div>
</section>
</main>
<?php get_footer(); ?>
