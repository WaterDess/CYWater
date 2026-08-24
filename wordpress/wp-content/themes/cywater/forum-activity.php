<?php
/**
 * Private Forum activity view for the signed-in account.
 *
 * @package CYWater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$account_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : home_url( '/member-account/' );

get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow'     => __( 'My account', 'cywater' ),
		'title'       => __( 'My Forum activity.', 'cywater' ),
		'lead'        => __( 'Review your writing and return to the Forum posts you have liked.', 'cywater' ),
		'breadcrumbs' => '<a href="' . esc_url( $account_url ) . '">' . esc_html__( 'Account', 'cywater' ) . '</a><span>/</span><span>' . esc_html__( 'Forum activity', 'cywater' ) . '</span>',
	)
);
?>
<section class="section forum-activity-section">
	<div class="container">
		<?php
		if ( class_exists( 'CYWater_Forum_Community' ) ) {
			echo CYWater_Forum_Community::activity_panel( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>
</section>
<?php get_footer(); ?>
