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

define( 'CYWATER_THEME_VERSION', '0.6.52' );

function cywater_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 600,
			'width'       => 493,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_editor_style( 'assets/css/editor.css' );
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
	add_image_size( 'cywater-site-icon', 128, 128, false );
}
add_action( 'after_setup_theme', 'cywater_theme_setup' );

/**
 * CYWater owns one brand source rather than an unrelated Logo and Site Icon.
 * The theme emits its own icon links from the active custom Logo, so suppress
 * WordPress' independent Site Icon output if one was configured previously.
 */
function cywater_remove_independent_site_icon() {
	remove_action( 'wp_head', 'wp_site_icon', 99 );
	remove_action( 'login_head', 'wp_site_icon', 99 );
	remove_action( 'admin_head', 'wp_site_icon', 10 );
	remove_action( 'admin_head', 'wp_site_icon', 99 );
}
add_action( 'init', 'cywater_remove_independent_site_icon' );

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
	wp_enqueue_script( 'cywater-main', get_theme_file_uri( 'assets/js/main.js' ), array(), cywater_asset_version( 'assets/js/main.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'cywater_enqueue_assets' );

function cywater_asset_uri( $relative_path ) {
	return get_theme_file_uri( 'assets/' . ltrim( $relative_path, '/' ) );
}

/**
 * Return the one editable Logo attachment used by every CYWater surface.
 */
function cywater_brand_logo_id() {
	$logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
	return $logo_id && wp_attachment_is_image( $logo_id ) ? $logo_id : 0;
}

/**
 * Return the active brand Logo URL, falling back to the bundled mark.
 */
function cywater_brand_logo_url() {
	$logo_id = cywater_brand_logo_id();
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return cywater_asset_uri( 'img/logo.png' );
}

/**
 * Render the active brand Logo with consistent loading and intrinsic sizing.
 *
 * @param string $context Header or footer.
 * @return string
 */
function cywater_brand_logo_markup( $context = 'header' ) {
	$logo_id = cywater_brand_logo_id();
	$class   = 'brand-logo' . ( 'footer' === $context ? ' brand-logo--footer' : '' );
	$attrs   = array(
		'class'    => $class,
		'alt'      => 'footer' === $context ? get_bloginfo( 'name' ) : '',
		'loading'  => 'eager',
		'decoding' => 'async',
	);
	if ( 'header' === $context ) {
		$attrs['fetchpriority'] = 'high';
	}
	if ( $logo_id ) {
		$markup = wp_get_attachment_image( $logo_id, 'full', false, $attrs );
		if ( $markup ) {
			return $markup;
		}
	}

	$attributes = '';
	foreach ( $attrs as $name => $value ) {
		$attributes .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}
	return sprintf(
		'<img%s src="%s" width="493" height="600">',
		$attributes,
		esc_url( cywater_brand_logo_url() )
	);
}

/**
 * Use a small derivative of the same custom Logo as the browser icon.
 * Future Logo uploads receive the cywater-site-icon image size automatically.
 */
function cywater_brand_icon_url() {
	$logo_id = cywater_brand_logo_id();
	if ( $logo_id ) {
		$metadata = wp_get_attachment_metadata( $logo_id );
		$size     = isset( $metadata['sizes']['cywater-site-icon'] ) ? 'cywater-site-icon' : 'medium';
		$url      = wp_get_attachment_image_url( $logo_id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return cywater_asset_uri( 'img/favicon.svg' );
}

/**
 * Emit the single-source Logo as browser icon links.
 */
function cywater_brand_icon_links() {
	$logo_id      = cywater_brand_logo_id();
	$icon_url     = cywater_brand_icon_url();
	$icon_version = $logo_id ? get_post_modified_time( 'U', true, $logo_id ) : cywater_asset_version( 'assets/img/favicon.svg' );
	$icon_url     = add_query_arg( 'cyw-brand', $icon_version ?: CYWATER_THEME_VERSION, $icon_url );
	$icon_type    = $logo_id ? get_post_mime_type( $logo_id ) : 'image/svg+xml';
	?>
	<link rel="icon" href="<?php echo esc_url( $icon_url ); ?>" type="<?php echo esc_attr( $icon_type ?: 'image/png' ); ?>" sizes="any">
	<link rel="shortcut icon" href="<?php echo esc_url( $icon_url ); ?>" type="<?php echo esc_attr( $icon_type ?: 'image/png' ); ?>">
	<?php
}

/**
 * Emit icon discovery before wp_head and preload the visible header mark.
 */
function cywater_brand_head_assets() {
	cywater_brand_icon_links();
	?>
	<link rel="preload" as="image" href="<?php echo esc_url( cywater_brand_logo_url() ); ?>" fetchpriority="high">
	<?php
}

/**
 * WordPress login does not render the theme header. Remove any Site Icon hook
 * added after init immediately before it could render, then output the Logo.
 */
function cywater_brand_login_head() {
	remove_action( 'login_head', 'wp_site_icon', 10 );
	remove_action( 'login_head', 'wp_site_icon', 99 );
	cywater_brand_icon_links();
}
add_action( 'login_head', 'cywater_brand_login_head', 0 );

/**
 * WordPress administration likewise needs the same replace-once browser icon.
 */
function cywater_brand_admin_head() {
	remove_action( 'admin_head', 'wp_site_icon', 10 );
	remove_action( 'admin_head', 'wp_site_icon', 99 );
	cywater_brand_icon_links();
}
add_action( 'admin_head', 'cywater_brand_admin_head', 0 );

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
	if ( is_post_type_archive( 'cyw_forum_post' ) || is_singular( 'cyw_forum_post' ) || is_tax( array( 'cyw_forum_category', 'cyw_forum_topic' ) ) || get_query_var( 'cywater_forum_member' ) || get_query_var( 'cywater_forum_activity' ) || is_page( array( 'forum-endorsement', 'forum-workspace' ) ) ) {
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
	if ( 'cyw_forum_post' === get_post_type( $post_id ) && class_exists( 'CYWater_Forum_Covers' ) ) {
		$protected_cover = CYWater_Forum_Covers::url( $post_id );
		if ( $protected_cover ) {
			return $protected_cover;
		}
	}
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

/**
 * Build an event summary from stored editorial content only.
 *
 * Event-scoped plugins may append interactive modules through `the_content`.
 * Those modules must never leak into archive cards or the detail-page lead.
 */
function cywater_event_summary( $post_id, $words = 44 ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$source = $post->post_excerpt ?: $post->post_content;
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $source ) ), $words, '…' );
}

/**
 * Determine whether an attachment is already present in the stored body.
 *
 * Modern blocks preserve an attachment ID, while imported legacy HTML may
 * contain only the original or a generated-size filename. Supporting both
 * keeps the detail template from rendering the same cover twice.
 */
function cywater_content_contains_attachment( $post_id, $attachment_id ) {
	$post_id       = absint( $post_id );
	$attachment_id = absint( $attachment_id );
	$content       = (string) get_post_field( 'post_content', $post_id, 'raw' );
	if ( ! $post_id || ! $attachment_id || '' === $content ) {
		return false;
	}

	if ( preg_match( '/\bwp-image-' . $attachment_id . '\b/', $content ) ) {
		return true;
	}

	$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	$filename      = pathinfo( wp_basename( $attached_file ), PATHINFO_FILENAME );
	$extension     = pathinfo( wp_basename( $attached_file ), PATHINFO_EXTENSION );
	if ( '' === $filename || '' === $extension ) {
		return false;
	}

	$pattern = '/(?:^|[\/_-])' . preg_quote( $filename, '/' ) . '(?:-\d+x\d+)?\.' . preg_quote( $extension, '/' ) . '(?:[?"\'\s]|$)/i';
	return (bool) preg_match( $pattern, $content );
}

/**
 * Render the listing cover once at the beginning of a News/Event detail.
 *
 * If an editor has deliberately placed the same attachment in the body, the
 * body placement wins and the automatic figure is omitted.
 */
function cywater_detail_featured_figure( $post_id = null ) {
	$post_id       = $post_id ? absint( $post_id ) : get_the_ID();
	$attachment_id = get_post_thumbnail_id( $post_id );
	if ( ! $attachment_id || cywater_content_contains_attachment( $post_id, $attachment_id ) ) {
		return '';
	}

	$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	if ( '' === $alt ) {
		$alt = get_the_title( $post_id );
	}
	$image = wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'class'   => 'cywater-detail-cover-image',
			'alt'     => $alt,
			'loading' => 'eager',
		)
	);
	if ( ! $image ) {
		return '';
	}

	$caption = wp_get_attachment_caption( $attachment_id );
	return '<figure class="cywater-detail-cover">' . $image . ( $caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
}

/**
 * Order Events for every archive section.
 *
 * Upcoming records lead each category and run from the nearest start date
 * forward. Archive records follow from newest to oldest. Empty dates stay at
 * the end of their placement group, and old manual-order metadata is retained
 * but deliberately ignored so the visible Start date remains authoritative.
 *
 * @param WP_Post[] $events Event posts.
 * @return WP_Post[]
 */
function cywater_sort_events_for_archive( $events ) {
	$events = array_values( array_filter( (array) $events, static fn( $event ) => $event instanceof WP_Post ) );
	usort(
		$events,
		static function ( $left, $right ) {
			$left_upcoming  = 'upcoming' === (string) get_post_meta( $left->ID, '_cyw_status', true );
			$right_upcoming = 'upcoming' === (string) get_post_meta( $right->ID, '_cyw_status', true );
			if ( $left_upcoming !== $right_upcoming ) {
				return $left_upcoming ? -1 : 1;
			}

			$left_date  = (string) get_post_meta( $left->ID, '_cyw_start_date', true );
			$right_date = (string) get_post_meta( $right->ID, '_cyw_start_date', true );
			if ( $left_date !== $right_date ) {
				if ( '' === $left_date ) {
					return 1;
				}
				if ( '' === $right_date ) {
					return -1;
				}
				return $left_upcoming ? strcmp( $left_date, $right_date ) : strcmp( $right_date, $left_date );
			}

			$published = strcmp( (string) $right->post_date_gmt, (string) $left->post_date_gmt );
			return 0 !== $published ? $published : ( $right->ID <=> $left->ID );
		}
	);
	return $events;
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
	/*
	 * The account plugin owns one reusable compact card for registration,
	 * verification, and closure. Give those managed pages one presentation
	 * context instead of adding page-ID or shortcode-specific offsets.
	 */
	if ( is_page( array( 'member-register', 'verify-email', 'close-account' ) ) ) {
		$classes[] = 'cywater-compact-account-page';
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
	if ( class_exists( 'CYWater_Membership_Account_Routing' ) ) {
		return CYWater_Membership_Account_Routing::login_url( $redirect );
	}
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
		$workspace_url = class_exists( 'CYWater_Forum_Workspace' ) ? CYWater_Forum_Workspace::url() : home_url( '/forum-workspace/' );
		return '<a class="btn btn-accent" href="' . esc_url( cywater_login_url( $workspace_url ) ) . '">' . esc_html__( 'Sign in', 'cywater' ) . '</a>';
	}
	$user_id  = get_current_user_id();
	$blockers = CYWater_Forum_Roles::submission_blockers( $user_id );
	if ( ! $blockers ) {
		$url = class_exists( 'CYWater_Forum_Workspace' ) ? CYWater_Forum_Workspace::url() : home_url( '/forum-workspace/' );
		return '<a class="btn btn-accent" href="' . esc_url( $url ) . '">' . esc_html__( 'Write a Forum post', 'cywater' ) . '</a>';
	}
	if ( in_array( 'email_unverified', $blockers, true ) ) {
		return '<a class="btn btn-accent" href="' . esc_url( home_url( '/verify-email/' ) ) . '">' . esc_html__( 'Verify email', 'cywater' ) . '</a>';
	}
	if ( in_array( 'membership_inactive', $blockers, true ) ) {
		return '<a class="btn btn-accent" href="' . esc_url( home_url( '/membership/' ) ) . '">' . esc_html__( 'View membership', 'cywater' ) . '</a>';
	}
	if ( in_array( 'not_endorsed', $blockers, true ) && class_exists( 'CYWater_Forum_Endorsement' ) ) {
		return '<a class="btn btn-accent" href="' . esc_url( CYWater_Forum_Endorsement::page_url() ) . '">' . esc_html__( 'Request endorsement', 'cywater' ) . '</a>';
	}
	return '';
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
