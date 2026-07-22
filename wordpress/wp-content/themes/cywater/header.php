<?php
/**
 * Site header.
 *
 * @package CYWater
 */
$section = cywater_current_section();
$account = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : wp_login_url();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-page="<?php echo esc_attr( $section ); ?>">
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="header-inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'CYWater home', 'cywater' ); ?>">
			<img class="brand-logo" src="<?php echo esc_url( cywater_asset_uri( 'img/logo.png' ) ); ?>" alt="CYWater" width="41" height="50">
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
				foreach ( $links as $key => $link ) :
					?>
					<li><a class="nav-link" href="<?php echo esc_url( home_url( $link[1] ) ); ?>" <?php echo $key === $section ? 'aria-current="page"' : ''; ?>><span><?php echo esc_html( $link[0] ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="header-actions">
			<a class="btn btn-ghost" href="<?php echo esc_url( $account ); ?>"><?php echo is_user_logged_in() ? esc_html__( 'Account', 'cywater' ) : esc_html__( 'Sign in', 'cywater' ); ?></a>
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Join CYWater', 'cywater' ); ?></a>
			<button class="nav-toggle" aria-label="<?php esc_attr_e( 'Menu', 'cywater' ); ?>" aria-expanded="false"><span></span></button>
		</div>
	</div>
</header>
<nav class="nav-mobile" aria-label="<?php esc_attr_e( 'Mobile', 'cywater' ); ?>">
	<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/board/' ) ); ?>" class="sub-link"><?php esc_html_e( 'Board', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>" class="sub-link"><?php esc_html_e( 'Bylaws', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Membership', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Events', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php esc_html_e( 'News', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards', 'cywater' ); ?></a>
	<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'cywater' ); ?></a>
	<a class="btn btn-primary btn-block" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'Join CYWater', 'cywater' ); ?></a>
</nav>
