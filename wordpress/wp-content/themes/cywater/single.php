<?php
/**
 * News article.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	$categories = get_the_category();
	$tag        = $categories ? $categories[0]->name : 'News';
	$source     = get_post_meta( get_the_ID(), '_cyw_source_url', true );
	?>
	<section class="article-hero">
		<div class="container container-narrow">
			<div class="crumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span>/</span><a href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/news/' ) ); ?>">News</a></div>
			<div class="meta"><span class="badge badge-teal"><?php echo esc_html( $tag ); ?></span><span><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span></div>
			<h1><?php the_title(); ?></h1>
			<p class="lead" style="margin-top:var(--sp-4)"><?php echo esc_html( get_the_excerpt() ); ?></p>
		</div>
	</section>
	<section class="section">
		<div class="container container-narrow">
			<div class="article-body">
				<div class="prose entry-content" data-reveal><?php echo cywater_article_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php if ( $source ) : ?><hr class="divider" style="margin:var(--sp-7) 0"><div style="text-align:center"><a class="btn btn-outline btn-lg" href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener">View source announcement</a></div><?php endif; ?>
				<div style="text-align:center; margin-top:var(--sp-6)"><a class="link" href="<?php echo esc_url( home_url( '/news/' ) ); ?>">Back to News</a></div>
				<?php edit_post_link( 'Edit this article', '<span class="admin-edit-link">', '</span>' ); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
