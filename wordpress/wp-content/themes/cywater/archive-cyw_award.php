<?php
/**
 * Awards archive.
 *
 * @package CYWater
 */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => 'Recognition', 'title' => 'Young Scientist Best Paper Award.', 'lead' => 'A year-by-year record of outstanding contributions to water sciences.' ) );
?>
<section class="section"><div class="container"><div class="award-grid grid grid-3">
	<?php
	while ( have_posts() ) :
		the_post();
		$year      = get_post_meta( get_the_ID(), '_cyw_year', true );
		$recipient = get_post_meta( get_the_ID(), '_cyw_recipient', true );
		?>
		<article class="card" data-reveal><div class="card-body"><div class="stat-num"><?php echo esc_html( $year ); ?></div><h2 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php if ( $recipient ) : ?><p><?php echo esc_html( $recipient ); ?></p><?php endif; ?><p class="card-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p><a class="link" href="<?php the_permalink(); ?>">View award</a></div></article>
	<?php endwhile; ?>
</div><?php the_posts_pagination(); ?></div></section>
<?php get_footer(); ?>
