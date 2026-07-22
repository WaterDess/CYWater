<?php
/**
 * Editable Board directory backed by cywater-core.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero', null, array( 'eyebrow' => cywater_page_field( 'eyebrow', 'Leadership' ), 'title' => get_the_title(), 'lead' => cywater_page_field( 'lead', get_the_excerpt() ) ) );
	?>
	<section class="section">
		<div class="container container-narrow">
			<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
			<div class="role-grid" style="margin-top:var(--sp-7)">
				<?php
				$roles = new WP_Query(
					array(
						'post_type'      => 'cyw_board_role',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'meta_key'       => '_cyw_order',
						'orderby'        => array( 'meta_value_num' => 'ASC', 'title' => 'ASC' ),
					)
				);
				$index = 0;
				while ( $roles->have_posts() ) :
					$roles->the_post();
					++$index;
					$name        = get_post_meta( get_the_ID(), '_cyw_person_name', true );
					$affiliation = get_post_meta( get_the_ID(), '_cyw_affiliation', true );
					$confirmed   = (bool) get_post_meta( get_the_ID(), '_cyw_confirmed_public', true );
					?>
					<article class="role-card" data-reveal><span class="role-index"><?php echo esc_html( str_pad( (string) $index, 2, '0', STR_PAD_LEFT ) ); ?></span><div><h2><?php the_title(); ?></h2><?php if ( $confirmed && $name ) : ?><p><strong><?php echo esc_html( $name ); ?></strong><?php echo $affiliation ? '<br>' . esc_html( $affiliation ) : ''; ?></p><?php else : ?><p>Appointment to be confirmed.</p><?php endif; ?></div></article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
			<?php edit_post_link( 'Edit Board introduction', '<span class="admin-edit-link">', '</span>' ); ?>
		</div>
	</section>
	<?php
endwhile;
get_footer();
