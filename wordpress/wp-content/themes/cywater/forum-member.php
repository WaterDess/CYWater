<?php
/**
 * Privacy-scoped Forum author page.
 *
 * The Forum plugin resolves an opaque public token to an author. This theme
 * renders only the byline and professional fields that member explicitly made
 * public through the existing profile-privacy controls.
 *
 * @package CYWater
 */

get_header();

$author  = class_exists( 'CYWater_Forum_Community' ) ? CYWater_Forum_Community::current_author() : null;
$profile = $author instanceof WP_User ? CYWater_Forum_Community::public_profile( $author->ID ) : array();
$count   = $author instanceof WP_User ? CYWater_Forum_Content::published_count( $author->ID ) : 0;
$paged   = max( 1, absint( get_query_var( 'paged' ) ) );
$posts   = $author instanceof WP_User
	? new WP_Query(
		array(
			'post_type'      => CYWater_Forum_Content::POST_TYPE,
			'post_status'    => 'publish',
			'author'         => $author->ID,
			'posts_per_page' => 12,
			'paged'          => $paged,
		)
	)
	: null;
?>
<main>
	<?php
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow'     => __( 'Forum author', 'cywater' ),
			'title'       => $author instanceof WP_User ? $author->display_name : __( 'Author unavailable', 'cywater' ),
			'lead'        => __( 'Published contributions to the CYWater community.', 'cywater' ),
			'breadcrumbs' => '<a href="' . esc_url( get_post_type_archive_link( 'cyw_forum_post' ) ) . '">' . esc_html__( 'Forum', 'cywater' ) . '</a>',
		)
	);
	?>
	<section class="section forum-member-page">
		<div class="container">
			<div class="forum-member-layout">
				<aside class="forum-member-profile card" aria-labelledby="forum-member-profile-heading">
					<?php if ( isset( $profile['cyw_profile_photo'] ) ) : ?><?php echo wp_kses_post( $profile['cyw_profile_photo']['value'] ); ?><?php endif; ?>
					<h2 id="forum-member-profile-heading"><?php echo esc_html( $author->display_name ); ?></h2>
					<p><?php echo esc_html( sprintf( _n( '%d published Forum post', '%d published Forum posts', $count, 'cywater' ), $count ) ); ?></p>
					<?php foreach ( $profile as $key => $item ) : ?>
						<?php if ( 'cyw_profile_photo' === $key ) { continue; } ?>
						<dl><dt><?php echo esc_html( $item['label'] ); ?></dt><dd>
						<?php if ( 'cyw_orcid' === $key && preg_match( '/^\d{4}-\d{4}-\d{4}-[\dX]{4}$/', (string) $item['value'] ) ) : ?>
							<a href="<?php echo esc_url( 'https://orcid.org/' . $item['value'] ); ?>" rel="noopener noreferrer"><?php echo esc_html( $item['value'] ); ?></a>
						<?php else : ?><?php echo esc_html( (string) $item['value'] ); ?><?php endif; ?>
						</dd></dl>
					<?php endforeach; ?>
				</aside>
				<div class="forum-member-posts">
					<div class="section-head"><span class="eyebrow"><?php esc_html_e( 'Writing', 'cywater' ); ?></span><h2><?php esc_html_e( 'Published Forum posts.', 'cywater' ); ?></h2></div>
					<?php if ( $posts instanceof WP_Query && $posts->have_posts() ) : ?>
						<div class="grid grid-2">
							<?php while ( $posts->have_posts() ) : $posts->the_post(); get_template_part( 'template-parts/forum-card' ); endwhile; ?>
						</div>
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'total'   => $posts->max_num_pages,
									'current' => $paged,
									'type'    => 'list',
								)
							)
						);
						?>
					<?php else : ?><p><?php esc_html_e( 'No public Forum posts are available.', 'cywater' ); ?></p><?php endif; ?>
				</div>
			</div>
		</div>
	</section>
</main>
<?php wp_reset_postdata(); get_footer(); ?>
