<?php
/**
 * Editable Board directory backed by cywater-core.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php

while ( have_posts() ) :
	the_post();

	$role_posts = get_posts(
		array(
			'post_type'      => 'cyw_board_role',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_cyw_order',
			'orderby'        => array( 'meta_value_num' => 'ASC', 'title' => 'ASC' ),
		)
	);
	$roles_by_title = array();
	foreach ( $role_posts as $role_post ) {
		$roles_by_title[ $role_post->post_title ] = $role_post;
	}
	$role_defaults = array(
		'President'            => 'Appointment to be confirmed.',
		'President-Elect'      => 'Appointment to be confirmed.',
		'Treasurer'            => 'Appointment to be confirmed.',
		'Directors-at-Large'   => 'Two to five appointments to be confirmed.',
		'Executive Director'   => 'Optional, ex officio, and non-voting.',
	);

	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'breadcrumbs' => '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a><span>/</span><a href="' . esc_url( home_url( '/about/' ) ) . '">About</a><span>/</span><span>Board</span>',
			'eyebrow'     => 'Leadership',
			'title'        => 'Board of Directors.',
			'lead'         => 'The Board governs CYWater, sets strategic direction, oversees finances, appoints committees, and ensures compliance with law and mission.',
		)
	);
	?>
	<section class="section">
		<div class="container container-narrow">
			<div class="board-status callout" data-reveal>
				<strong>Current Board leadership.</strong>
				<p>The following appointments have been confirmed for public display. Affiliations and terms will be added only after separate confirmation.</p>
			</div>
			<div class="section-head" data-reveal style="margin-top:var(--sp-8)">
				<span class="eyebrow">Bylaws structure</span>
				<h2>Board composition</h2>
				<p class="lead">The Bylaws define the following positions. The Executive Director, if appointed, serves ex officio and does not vote.</p>
			</div>
			<div class="role-grid">
				<?php $index = 0; foreach ( $role_defaults as $title => $fallback ) : ++$index; $role = $roles_by_title[ $title ] ?? null; ?>
					<article class="role-card" data-reveal>
						<span class="role-index"><?php echo esc_html( str_pad( (string) $index, 2, '0', STR_PAD_LEFT ) ); ?></span>
						<div>
							<h3><?php echo esc_html( $title ); ?></h3>
							<?php
							if ( $role && get_post_meta( $role->ID, '_cyw_confirmed_public', true ) && get_post_meta( $role->ID, '_cyw_person_name', true ) ) {
								$name        = (string) get_post_meta( $role->ID, '_cyw_person_name', true );
								$affiliation = (string) get_post_meta( $role->ID, '_cyw_affiliation', true );
								echo '<p><strong>' . esc_html( $name ) . '</strong>' . ( $affiliation ? '<br>' . esc_html( $affiliation ) : '' ) . '</p>';
							} else {
								echo '<p>' . esc_html( $fallback ) . '</p>';
							}
							?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>

			<hr class="divider" style="margin:var(--sp-8) 0">

			<div class="section-head" data-reveal><span class="eyebrow">Standing committees</span><h2>Committee framework</h2></div>
			<div class="prose" data-reveal>
				<ul><li>Awards Committee</li><li>Scientific and Technical Committee</li><li>Nomination Committee</li><li>Tellers Committee</li></ul>
				<p>Committee chairs and members will be published after appointment and confirmation.</p>
			</div>
			<?php edit_post_link( 'Edit Board introduction', '<span class="admin-edit-link">', '</span>' ); ?>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
