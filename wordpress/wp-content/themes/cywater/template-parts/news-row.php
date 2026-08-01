<?php
/**
 * News index entry.
 *
 * @package CYWater
 */

$featured    = ! empty( $args['featured'] );
$image       = cywater_featured_image_url( get_the_ID(), 'cywater-card' );
$visual_name = get_post_meta( get_the_ID(), '_cyw_visual_title', true );
$visual_year = get_post_meta( get_the_ID(), '_cyw_visual_year', true );
$categories  = get_the_category();
$tag         = $categories ? $categories[0]->name : 'News';
?>
<article class="<?php echo esc_attr( $featured ? 'news-feature' : 'news-item' ); ?>" data-reveal>
	<a href="<?php the_permalink(); ?>" class="card-media" aria-label="<?php the_title_attribute(); ?>">
		<?php if ( $image ) : ?>
			<img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
		<?php else : ?>
			<span class="news-visual" role="img" aria-label="<?php the_title_attribute(); ?>">
				<strong><?php echo esc_html( $visual_name ?: $tag ); ?></strong>
				<span class="news-visual-year"><?php echo esc_html( $visual_year ?: get_the_date( 'Y' ) ); ?></span>
			</span>
		<?php endif; ?>
	</a>
	<div<?php echo $featured ? ' class="card-body"' : ''; ?>>
		<div class="card-meta"><span class="card-tag"><?php echo esc_html( $tag ); ?></span><span><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span></div>
		<h3<?php echo $featured ? ' class="card-title"' : ''; ?>><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p<?php echo $featured ? ' class="card-excerpt"' : ''; ?>><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php if ( $featured ) : ?><a class="link" href="<?php the_permalink(); ?>">Read spotlight</a><?php endif; ?>
	</div>
</article>
