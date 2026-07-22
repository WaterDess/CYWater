<?php
/**
 * Shared card for posts and custom content.
 *
 * @package CYWater
 */
$image = cywater_featured_image_url( get_the_ID(), 'cywater-card' );
$type  = get_post_type_object( get_post_type() );
$tag   = $args['tag'] ?? ( $type ? $type->labels->singular_name : __( 'Update', 'cywater' ) );
?>
<article class="card" data-reveal>
	<?php if ( $image ) : ?>
		<a href="<?php the_permalink(); ?>"><div class="card-media"><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"></div></a>
	<?php endif; ?>
	<div class="card-body">
		<div class="card-meta"><span class="card-tag"><?php echo esc_html( $tag ); ?></span><span>&middot; <?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span></div>
		<h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="card-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<a class="link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'cywater' ); ?></a>
	</div>
</article>
