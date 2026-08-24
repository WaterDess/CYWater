<?php
/**
 * Single forum article.
 *
 * @package CYWater
 */

get_header();

while ( have_posts() ) :
	the_post();

	$author_id  = (int) get_post_field( 'post_author', get_the_ID() );
	$categories = get_the_terms( get_the_ID(), 'cyw_forum_category' );
	$topics     = get_the_terms( get_the_ID(), 'cyw_forum_topic' );
	$hero       = cywater_featured_image_url( get_the_ID(), 'cywater-wide' );
	?>
	<main>
		<article class="forum-article">
			<section class="page-hero">
				<div class="container container-narrow" data-page-enter="hero">
					<div class="crumbs">
						<a href="<?php echo esc_url( get_post_type_archive_link( 'cyw_forum_post' ) ); ?>"><?php esc_html_e( 'Forum', 'cywater' ); ?></a>
						<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
							<span>&rsaquo;</span>
							<a href="<?php echo esc_url( get_term_link( $categories[0] ) ); ?>"><?php echo esc_html( $categories[0]->name ); ?></a>
						<?php endif; ?>
					</div>
					<h1><?php the_title(); ?></h1>
					<p class="lead forum-article-byline">
						<a href="<?php echo esc_url( CYWater_Forum_Community::author_url( $author_id ) ); ?>"><?php echo esc_html( get_the_author_meta( 'display_name', $author_id ) ); ?></a>
						<span>&middot; <?php echo esc_html( get_the_date() ); ?></span>
					</p>
				</div>
			</section>

			<?php if ( $hero ) : ?>
				<div class="container container-narrow">
					<div class="forum-article-media" data-reveal><img src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( get_post_meta( get_the_ID(), '_cyw_image_alt', true ) ); ?>"></div>
				</div>
			<?php endif; ?>

			<section class="section-tight">
				<div class="container container-narrow">
					<?php
					/*
					 * Not escaped on output, matching single.php and
					 * single-cyw_event.php. Forum authors do not hold
					 * unfiltered_html, so WordPress has already run kses over
					 * this content on save; escaping again here would break
					 * legitimate embeds and figures.
					 */
					?>
					<div class="prose entry-content" data-reveal><?php echo cywater_article_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

					<?php if ( $topics && ! is_wp_error( $topics ) ) : ?>
						<div class="tag-list forum-article-topics" data-reveal>
							<?php foreach ( $topics as $topic ) : ?>
								<span class="badge"><?php echo esc_html( $topic->name ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php
					/**
					 * Mount point for the per-viewer AI reaction.
					 *
					 * Nothing is attached. When a provider is connected, the
					 * script injects here after the page has rendered; if it
					 * has nothing to show, this stays exactly as it is now —
					 * no container, no heading, no trace.
					 */
					do_action( 'cywater_forum_after_article', get_post() );
					?>
				</div>
			</section>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<section class="section section-tint forum-discussion">
					<div class="container container-narrow">
						<?php comments_template(); ?>
					</div>
				</section>
			<?php endif; ?>
		</article>
	</main>
	<?php
endwhile;

get_footer();
