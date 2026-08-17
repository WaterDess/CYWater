<?php
/**
 * Forum articles by one author.
 *
 * Only reachable for accounts that have published; cywater-environment still
 * 404s every other author query. The byline and whatever biography the member
 * wrote for themselves are shown. Affiliation, ORCID, and email stay behind the
 * member directory's opt-in and are deliberately not rendered here.
 *
 * @package CYWater
 */

get_header();

$author = get_queried_object();
$name   = $author instanceof WP_User ? $author->display_name : '';
$bio    = $author instanceof WP_User ? get_the_author_meta( 'description', $author->ID ) : '';
$count  = ( $author instanceof WP_User && class_exists( 'CYWater_Forum_Content' ) ) ? CYWater_Forum_Content::published_count( $author->ID ) : 0;
?>
<main>
<?php
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow'     => __( 'Author', 'cywater' ),
		'title'       => $name,
		'lead'        => $bio,
		'breadcrumbs' => '<a href="' . esc_url( get_post_type_archive_link( 'cyw_forum_post' ) ) . '">' . esc_html__( 'Forum', 'cywater' ) . '</a>',
	)
);
?>
<section class="section">
	<div class="container">
		<div class="section-head" data-reveal>
			<span class="eyebrow"><?php echo esc_html( sprintf( /* translators: %d: article count. */ _n( '%d article', '%d articles', $count, 'cywater' ), $count ) ); ?></span>
		</div>
		<?php if ( have_posts() ) : ?>
			<div class="grid grid-3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/forum-card' );
				endwhile;
				?>
			</div>
			<?php cywater_forum_pagination(); ?>
		<?php else : ?>
			<p class="lead"><?php esc_html_e( 'This author has not published anything yet.', 'cywater' ); ?></p>
		<?php endif; ?>
	</div>
</section>
</main>
<?php get_footer(); ?>
