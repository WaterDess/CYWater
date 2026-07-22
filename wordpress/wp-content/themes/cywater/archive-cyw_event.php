<?php
/**
 * Events archive.
 *
 * @package CYWater
 */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => 'Scientific exchange', 'title' => 'Meetings and gatherings.', 'lead' => 'CYWater Annual Meetings and community gatherings during the AGU Fall Meeting.' ) );
?>
<section class="section">
	<div class="container container-narrow">
		<div class="event-list">
			<?php
			while ( have_posts() ) :
				the_post();
				$image    = cywater_featured_image_url( get_the_ID(), 'cywater-card' );
				$start    = get_post_meta( get_the_ID(), '_cyw_start_date', true );
				$date     = get_post_meta( get_the_ID(), '_cyw_date_label', true ) ?: $start;
				$location = get_post_meta( get_the_ID(), '_cyw_location', true );
				$status   = get_post_meta( get_the_ID(), '_cyw_status', true );
				?>
				<article class="event-row" data-reveal>
					<a class="event-row-media" href="<?php the_permalink(); ?>"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"><?php else : ?><span class="event-year"><?php echo esc_html( $start ? substr( $start, 0, 4 ) : get_the_date( 'Y' ) ); ?></span><?php endif; ?></a>
					<div class="event-row-copy"><span class="badge <?php echo 'upcoming' === $status ? 'badge-teal' : ''; ?>"><?php echo esc_html( 'upcoming' === $status ? 'Upcoming' : 'Archive' ); ?></span><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p class="event-meta"><?php echo esc_html( trim( $date . ( $location ? ' · ' . $location : '' ) ) ); ?></p><p><?php echo esc_html( get_the_excerpt() ); ?></p></div>
					<a class="link event-row-link" href="<?php the_permalink(); ?>">View details</a>
				</article>
				<?php
			endwhile;
			?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php get_footer(); ?>
