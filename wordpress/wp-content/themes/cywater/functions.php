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

define( 'CYWATER_THEME_VERSION', '0.1.0' );

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
	if ( is_home() || is_singular( 'post' ) || is_category() ) {
		return 'news';
	}
	if ( is_page( array( 'about', 'board', 'bylaws' ) ) ) {
		return 'about';
	}
	if ( is_page( array( 'membership', 'account', 'membership-account', 'membership-billing', 'membership-cancel', 'membership-checkout', 'membership-confirmation', 'membership-order', 'member-login', 'member-profile', 'members' ) ) ) {
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
