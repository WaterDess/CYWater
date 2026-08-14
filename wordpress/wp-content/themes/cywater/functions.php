<?php
/**
 * CYWater theme setup.
 *
 * Business data belongs to cywater-core and membership/payment behavior belongs
 * to their dedicated plugins. This theme only renders WordPress content.
 *
 * @package CYWater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_THEME_VERSION', '0.5.9' );

function cywater_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'cywater' ),
			'footer'  => __( 'Footer navigation', 'cywater' ),
		)
	);
	add_image_size( 'cywater-card', 960, 640, true );
	add_image_size( 'cywater-wide', 1600, 900, true );
}
add_action( 'after_setup_theme', 'cywater_theme_setup' );

/**
 * Preserve the verified static site's punctuation exactly.
 *
 * WordPress normally converts straight quotes and apostrophes to typographic
 * variants. The public preview intentionally keeps the source registry text
 * unchanged, so the WordPress renderer must do the same.
 */
function cywater_disable_texturize() {
	foreach ( array( 'the_title', 'the_content', 'the_excerpt', 'single_post_title', 'wp_title' ) as $filter ) {
		remove_filter( $filter, 'wptexturize' );
	}
}
add_action( 'after_setup_theme', 'cywater_disable_texturize', 20 );

function cywater_asset_version( $relative_path ) {
	$file = get_theme_file_path( $relative_path );
	return file_exists( $file ) ? (string) filemtime( $file ) : CYWATER_THEME_VERSION;
}

function cywater_enqueue_assets() {
	wp_enqueue_style(
		'cywater-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'cywater-base', get_theme_file_uri( 'assets/css/base.css' ), array( 'cywater-fonts' ), cywater_asset_version( 'assets/css/base.css' ) );
	wp_enqueue_style( 'cywater-components', get_theme_file_uri( 'assets/css/components.css' ), array( 'cywater-base' ), cywater_asset_version( 'assets/css/components.css' ) );
	wp_enqueue_style( 'cywater-pages', get_theme_file_uri( 'assets/css/pages.css' ), array( 'cywater-components' ), cywater_asset_version( 'assets/css/pages.css' ) );
	wp_enqueue_style( 'cywater-wordpress', get_theme_file_uri( 'wordpress.css' ), array( 'cywater-pages' ), cywater_asset_version( 'wordpress.css' ) );
	// Theme-owned, and deliberately outside assets/ so `npm run assets:sync`
	// cannot overwrite it with the static site's copy. See wordpress.css.
	wp_enqueue_script( 'cywater-main', get_theme_file_uri( 'main.js' ), array(), cywater_asset_version( 'main.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'cywater_enqueue_assets' );

function cywater_asset_uri( $relative_path ) {
	return get_theme_file_uri( 'assets/' . ltrim( $relative_path, '/' ) );
}

function cywater_current_section() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_post_type_archive( 'cyw_event' ) || is_singular( 'cyw_event' ) ) {
		return 'events';
	}
	if ( is_post_type_archive( 'cyw_award' ) || is_singular( 'cyw_award' ) ) {
		return 'awards';
	}
	if ( is_post_type_archive( 'cyw_forum_post' ) || is_singular( 'cyw_forum_post' ) || is_tax( array( 'cyw_forum_category', 'cyw_forum_topic' ) ) || is_author() || is_page( 'forum-endorsement' ) ) {
		return 'forum';
	}
	if ( is_home() || is_singular( 'post' ) || is_category() ) {
		return 'news';
	}
	if ( is_page( array( 'about', 'board', 'bylaws' ) ) ) {
		return 'about';
	}
	if ( is_page( array( 'membership', 'account', 'membership-account', 'membership-billing', 'membership-cancel', 'membership-checkout', 'membership-confirmation', 'membership-order', 'member-login', 'member-register', 'member-profile', 'members' ) ) ) {
		return 'membership';
	}
	if ( is_page( 'contact' ) ) {
		return 'contact';
	}
	return '';
}

function cywater_page_field( $key, $fallback = '' ) {
	$value = is_singular() ? get_post_meta( get_queried_object_id(), '_cyw_' . $key, true ) : '';
	return $value ? $value : $fallback;
}

function cywater_featured_image_url( $post_id = null, $size = 'cywater-card', $fallback = '' ) {
	$post_id = $post_id ?: get_the_ID();
	$url     = get_the_post_thumbnail_url( $post_id, $size );
	if ( $url ) {
		return $url;
	}
	$legacy = get_post_meta( $post_id, '_cyw_source_image', true );
	if ( $legacy ) {
		return cywater_asset_uri( 'img/' . ltrim( $legacy, '/' ) );
	}
	return $fallback ? cywater_asset_uri( 'img/' . ltrim( $fallback, '/' ) ) : '';
}

function cywater_source_permalink( $source_id, $post_type = 'post' ) {
	$post = cywater_source_post( $source_id, $post_type );
	return $post ? get_permalink( $post ) : '';
}

function cywater_source_post( $source_id, $post_type = 'any' ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_cyw_source_id',
			'meta_value'     => $source_id,
		)
	);
	return $posts ? get_post( $posts[0] ) : null;
}

function cywater_article_content( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$content = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
	return str_replace( 'gallery-grid', 'article-gallery', $content );
}

function cywater_body_classes( $classes ) {
	$classes[] = 'cywater-wordpress';
	$section   = cywater_current_section();
	if ( $section ) {
		$classes[] = 'section-' . sanitize_html_class( $section );
	}
	return $classes;
}
add_filter( 'body_class', 'cywater_body_classes' );

function cywater_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'cywater_excerpt_more' );

/**
 * Forum helpers.
 *
 * Presentation only. Authorship policy, endorsement state, and discussion rules
 * belong to cywater-forum; this theme asks it questions and renders answers.
 */

function cywater_forum_enabled() {
	return post_type_exists( 'cyw_forum_post' );
}

/**
 * Resolve the sign-in URL the same way the membership plugin does.
 *
 * The `/member-login/` page is created by PMPro setup, so it does not exist
 * wherever PMPro is inactive — including the Playground review environment.
 * Hardcoding that slug produces a 404 there. Ask PMPro when it is present and
 * fall back to the WordPress login otherwise.
 *
 * @param string $redirect Optional URL to return to after signing in.
 * @return string
 */
function cywater_login_url( $redirect = '' ) {
	$url = function_exists( 'pmpro_url' ) ? pmpro_url( 'login' ) : wp_login_url();
	return $redirect ? add_query_arg( 'redirect_to', $redirect, $url ) : $url;
}

/**
 * Call to action for the forum hero, matched to what this visitor can do.
 */
function cywater_forum_hero_actions() {
	if ( ! class_exists( 'CYWater_Forum_Roles' ) ) {
		return '';
	}
	if ( ! is_user_logged_in() ) {
		return '<a class="btn btn-accent" href="' . esc_url( cywater_login_url( get_post_type_archive_link( 'cyw_forum_post' ) ) ) . '">' . esc_html__( 'Sign in to take part', 'cywater' ) . '</a>';
	}
	if ( CYWater_Forum_Roles::can_publish( get_current_user_id() ) ) {
		return '<a class="btn btn-accent" href="' . esc_url( admin_url( 'post-new.php?post_type=cyw_forum_post' ) ) . '">' . esc_html__( 'Write an article', 'cywater' ) . '</a>';
	}
	$endorsement = class_exists( 'CYWater_Forum_Endorsement' ) ? CYWater_Forum_Endorsement::page_url() : home_url( '/forum-endorsement/' );
	return '<a class="btn btn-accent" href="' . esc_url( $endorsement ) . '">' . esc_html__( 'Become an author', 'cywater' ) . '</a>';
}

function cywater_forum_pagination() {
	$links = paginate_links(
		array(
			'type'      => 'array',
			'prev_text' => __( 'Previous', 'cywater' ),
			'next_text' => __( 'Next', 'cywater' ),
		)
	);
	if ( ! $links ) {
		return;
	}
	echo '<nav class="forum-pagination" aria-label="' . esc_attr__( 'Forum pages', 'cywater' ) . '">';
	foreach ( $links as $link ) {
		// aria-current marks the current page for assistive technology and must
		// survive the allowlist.
		echo wp_kses(
			$link,
			array(
				'a'    => array( 'href' => true, 'class' => true, 'aria-current' => true ),
				'span' => array( 'class' => true, 'aria-current' => true ),
				'br'   => array(),
			)
		);
	}
	echo '</nav>';
}

/**
 * Shared body for the forum category and topic archives.
 *
 * @param string $eyebrow Label above the term name.
 */
function cywater_forum_term_archive( $eyebrow ) {
	$term = get_queried_object();
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow'     => $eyebrow,
			'title'       => $term instanceof WP_Term ? $term->name : __( 'Forum', 'cywater' ),
			'lead'        => $term instanceof WP_Term && $term->description ? $term->description : '',
			'breadcrumbs' => '<a href="' . esc_url( get_post_type_archive_link( 'cyw_forum_post' ) ) . '">' . esc_html__( 'Forum', 'cywater' ) . '</a>',
		)
	);
	?>
	<section class="section">
		<div class="container">
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
				<p class="lead"><?php esc_html_e( 'Nothing has been published here yet.', 'cywater' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}
