<?php
/**
 * Forum article card. Carries the author, which is the point of the section.
 *
 * @package CYWater
 */
$image      = cywater_featured_image_url( get_the_ID(), 'cywater-card' );
$categories = get_the_terms( get_the_ID(), 'cyw_forum_category' );
$category   = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0] : null;
$author_id  = (int) get_post_field( 'post_author', get_the_ID() );
?>
<article class="card forum-card" data-reveal>
	<?php if ( $image ) : ?>
		<a href="<?php the_permalink(); ?>"><div class="card-media"><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"></div></a>
	<?php endif; ?>
	<div class="card-body">
		<div class="card-meta">
			<?php if ( $category ) : ?>
				<a class="card-tag" href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
			<?php else : ?>
				<span class="card-tag"><?php esc_html_e( 'Forum', 'cywater' ); ?></span>
			<?php endif; ?>
			<span>&middot; <?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span>
		</div>
		<h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="card-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<div class="forum-byline">
			<a href="<?php echo esc_url( CYWater_Forum_Community::author_url( $author_id ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></a>
			<?php if ( comments_open() && get_comments_number() ) : ?>
				<span class="forum-replies"><?php echo esc_html( sprintf( /* translators: %d: reply count. */ _n( '%d reply', '%d replies', (int) get_comments_number(), 'cywater' ), (int) get_comments_number() ) ); ?></span>
			<?php endif; ?>
			<?php $like_count = CYWater_Forum_Community::like_count( get_the_ID() ); ?>
			<?php if ( $like_count ) : ?><span class="forum-likes"><?php echo esc_html( sprintf( _n( '%d like', '%d likes', $like_count, 'cywater' ), $like_count ) ); ?></span><?php endif; ?>
		</div>
	</div>
	<a class="forum-card-link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: forum article title. */ __( 'Read %s', 'cywater' ), get_the_title() ) ); ?>"></a>
</article>
