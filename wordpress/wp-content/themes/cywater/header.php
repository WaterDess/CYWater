<?php
/**
 * Site header.
 *
 * @package CYWater
 */
$section = cywater_current_section();
$account = function_exists( 'pmpro_url' )
	? pmpro_url( is_user_logged_in() ? 'account' : 'login' )
	: wp_login_url();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php cywater_brand_head_assets(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-page="<?php echo esc_attr( $section ); ?>">
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="header-inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'CYWater home', 'cywater' ); ?>">
			<?php echo cywater_brand_logo_markup( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="brand-name" aria-hidden="true">CYWater</span>
		</a>
		<nav class="nav" aria-label="<?php esc_attr_e( 'Primary', 'cywater' ); ?>">
			<ul class="nav-list">
				<li class="has-dropdown">
					<a class="nav-link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>" <?php echo 'about' === $section ? 'aria-current="page"' : ''; ?>>
						<span><?php esc_html_e( 'About', 'cywater' ); ?></span>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</a>
					<div class="dropdown">
						<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About CYWater', 'cywater' ); ?></a>
						<a href="<?php echo esc_url( home_url( '/board/' ) ); ?>"><?php esc_html_e( 'Board', 'cywater' ); ?></a>
						<a href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>"><?php esc_html_e( 'Bylaws', 'cywater' ); ?></a>
					</div>
				</li>
				<?php
				$links = array(
					'membership' => array( 'Membership', '/membership/' ),
					'events'     => array( 'Events', '/events/' ),
					'news'       => array( 'News', '/news/' ),
					'awards'     => array( 'Awards', '/awards/' ),
					'contact'    => array( 'Contact', '/contact/' ),
				);
				// Sits next to Membership: the forum is a member activity
				// rather than another editorial section.
				if ( cywater_forum_enabled() ) {
					$links = array_slice( $links, 0, 1, true )
						+ array( 'forum' => array( 'Forum', '/forum/' ) )
						+ array_slice( $links, 1, null, true );
				}
				foreach ( $links as $key => $link ) :
					?>
					<li><a class="nav-link" href="<?php echo esc_url( home_url( $link[1] ) ); ?>" <?php echo $key === $section ? 'aria-current="page"' : ''; ?>><span><?php echo esc_html( $link[0] ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="header-actions">
			<a class="btn btn-ghost" href="<?php echo esc_url( $account ); ?>"><?php echo is_user_logged_in() ? esc_html__( 'Account', 'cywater' ) : esc_html__( 'Sign in', 'cywater' ); ?></a>
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Join CYWater', 'cywater' ); ?></a>
			<button class="nav-toggle" aria-label="<?php esc_attr_e( 'Menu', 'cywater' ); ?>" aria-controls="cywater-mobile-nav" aria-expanded="false"><span></span></button>
		</div>
	</div>
</header>
<nav id="cywater-mobile-nav" class="nav-mobile" aria-label="<?php esc_attr_e( 'Mobile', 'cywater' ); ?>">
	<div class="nav-mobile-actions" role="group" aria-label="<?php esc_attr_e( 'Account', 'cywater' ); ?>">
		<a class="btn btn-outline btn-block nav-mobile-account" href="<?php echo esc_url( $account ); ?>"><?php echo is_user_logged_in() ? esc_html__( 'Account', 'cywater' ) : esc_html__( 'Sign in', 'cywater' ); ?></a>
		<a class="btn btn-primary btn-block" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Join CYWater', 'cywater' ); ?></a>
	</div>
	<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/board/' ) ); ?>" class="sub-link"><?php esc_html_e( 'Board', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>" class="sub-link"><?php esc_html_e( 'Bylaws', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Membership', 'cywater' ); ?></a>
	<?php if ( cywater_forum_enabled() ) : ?>
		<a href="<?php echo esc_url( home_url( '/forum/' ) ); ?>"><?php esc_html_e( 'Forum', 'cywater' ); ?></a>
	<?php endif; ?>
	<a href="<?php echo esc_url( home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Events', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php esc_html_e( 'News', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'cywater' ); ?></a>
</nav>
