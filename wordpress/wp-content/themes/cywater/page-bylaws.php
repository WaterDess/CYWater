<?php
/**
 * Bylaws page with an editable article body.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'breadcrumbs' => '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a><span>/</span><a href="' . esc_url( home_url( '/about/' ) ) . '">About</a><span>/</span><span>Bylaws</span>',
			'eyebrow'     => 'Governance document',
			'title'        => 'CYWater Bylaws',
			'lead'         => 'Bylaws of the International Association of Contemporary Young Scholars in Water Sciences.',
			'actions'      => '<a class="btn btn-primary" href="' . esc_url( cywater_asset_uri( 'docs/CYWater-Bylaws.docx' ) ) . '" download>Download bylaws (.docx)</a>',
		)
	);
	?>
	<section class="section">
		<div class="container">
			<div class="bylaws-layout">
				<aside class="toc" data-reveal>
					<h4>Contents</h4>
					<ol><?php foreach ( array( 'Name and mission', 'Membership', 'Governance', 'Board of directors', 'Meetings', 'Conferences and publications', 'Committees', 'Amendments', 'Merger or dissolution' ) as $index => $label ) : $target = 'a' . ( $index + 1 ); ?><li><a href="#<?php echo esc_attr( $target ); ?>" data-target="<?php echo esc_attr( $target ); ?>"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?></ol>
				</aside>
				<div class="prose entry-content"><?php the_content(); ?></div>
			</div>
			<?php edit_post_link( __( 'Edit this page', 'cywater' ), '<span class="admin-edit-link">', '</span>' ); ?>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
