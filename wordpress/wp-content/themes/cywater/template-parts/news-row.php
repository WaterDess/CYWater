<?php
/**
 * News/archive row.
 *
 * @package CYWater
 */
$image       = cywater_featured_image_url( get_the_ID(), 'cywater-card' );
$visual_name = get_post_meta( get_the_ID(), '_cyw_visual_title', true );
$visual_year = get_post_meta( get_the_ID(), '_cyw_visual_year', true );
$categories  = get_the_category();
$tag         = $categories ? $categories[0]->name : __( 'News', 'cywater' );
?>
<article class="news-row" data-reveal>
	<a class="news-row-media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
		<?php if ( $image ) : ?>
			<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<span class="news-visual"><strong><?php echo esc_html( $visual_name ?: $tag ); ?></strong><span><?php echo esc_html( $visual_year ?: get_the_date( 'Y' ) ); ?></span></span>
		<?php endif; ?>
	</a>
	<div class="news-row-copy">
		<div class="card-meta"><span class="card-tag"><?php echo esc_html( $tag ); ?></span><span><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span></div>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
	</div>
</article>
