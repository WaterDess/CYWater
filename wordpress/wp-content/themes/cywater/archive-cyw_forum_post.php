<?php
/**
 * Forum article index.
 *
 * @package CYWater
 */

get_header();

$is_forum_guest = ! is_user_logged_in();
$register_url    = class_exists( 'CYWater_Membership_Account_Routing' )
	? CYWater_Membership_Account_Routing::registration_url( get_post_type_archive_link( 'cyw_forum_post' ) )
	: home_url( '/member-register/' );
$login_url       = class_exists( 'CYWater_Membership_Account_Routing' )
	? CYWater_Membership_Account_Routing::login_url( get_post_type_archive_link( 'cyw_forum_post' ) )
	: wp_login_url( get_post_type_archive_link( 'cyw_forum_post' ) );

$guest_actions = '<a class="btn btn-primary" href="' . esc_url( $register_url ) . '">' . esc_html__( 'Create an account', 'cywater' ) . '</a>'
	. '<a class="btn btn-outline" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Sign in', 'cywater' ) . '</a>';

$categories = array();
$topics     = array();
if ( ! $is_forum_guest ) {
	$categories = get_terms(
		array(
			'taxonomy'   => 'cyw_forum_category',
			'hide_empty' => true,
		)
	);
	$topics     = get_terms(
		array(
			'taxonomy'   => 'cyw_forum_topic',
			'hide_empty' => true,
			'number'     => 24,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
}
?>
<main>
<?php
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => 'Community writing',
		'title'   => 'CYWater Forum.',
		'lead'    => $is_forum_guest
			? 'The Forum is a registered-account community for water scholars and practitioners. Create an account or sign in to browse member writing.'
			: 'Verified CYWater members with an active individual membership may publish articles on research, practice, and early-career life and join the discussion.',
		'actions' => $is_forum_guest ? $guest_actions : cywater_forum_hero_actions(),
	)
);
?>

<?php if ( $is_forum_guest ) : ?>
	<section class="section forum-access-gate">
		<div class="container container-narrow">
			<div class="forum-access-card" data-page-enter="content">
				<span class="eyebrow"><?php esc_html_e( 'Registered community', 'cywater' ); ?></span>
				<h2><?php esc_html_e( 'Join the conversation.', 'cywater' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'A free CYWater account lets you browse Forum posts, read replies, and like member writing. An active individual membership is required to publish posts or add replies.', 'cywater' ); ?></p>
				<div class="hero-actions">
					<a class="btn btn-primary" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create an account', 'cywater' ); ?></a>
					<a class="btn btn-outline" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'cywater' ); ?></a>
				</div>
			</div>
		</div>
	</section>
<?php else : ?>
<?php
/*
 * Filters and the grid share one section. Two stacked sections put a full
 * section's padding between a one-line filter strip and the cards it filters,
 * which reads as a broken layout rather than as breathing room.
 */
?>
<section class="section forum-index-section">
	<div class="container">
		<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
			<nav class="forum-filters" aria-label="<?php esc_attr_e( 'Forum categories', 'cywater' ); ?>" data-reveal>
				<span class="eyebrow"><?php esc_html_e( 'Categories', 'cywater' ); ?></span>
				<div class="tag-list">
					<?php foreach ( $categories as $category ) : ?>
						<a class="badge badge-teal" href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
					<?php endforeach; ?>
				</div>
			</nav>
		<?php endif; ?>
		<?php if ( have_posts() ) : ?>
			<div class="grid grid-3 forum-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/forum-card' );
				endwhile;
				?>
			</div>
			<?php cywater_forum_pagination(); ?>
		<?php else : ?>
			<p class="lead"><?php esc_html_e( 'No forum articles have been published yet.', 'cywater' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! is_wp_error( $topics ) && $topics ) : ?>
	<section class="section section-tint">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center"><?php esc_html_e( 'Browse', 'cywater' ); ?></span><h2><?php esc_html_e( 'Topics.', 'cywater' ); ?></h2></div>
			<div class="tag-list" style="justify-content:center" data-reveal>
				<?php foreach ( $topics as $topic ) : ?>
					<a class="badge" href="<?php echo esc_url( get_term_link( $topic ) ); ?>"><?php echo esc_html( $topic->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php endif; ?>
</main>
<?php get_footer(); ?>
