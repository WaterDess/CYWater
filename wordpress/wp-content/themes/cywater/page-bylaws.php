<?php
/**
 * Bylaws page with an editable article body.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero' );
	?>
	<section class="section"><div class="container"><div class="bylaws-layout">
		<aside class="toc" data-reveal><h4>Contents</h4><ol><?php foreach ( array( 'Name and mission', 'Membership', 'Governance', 'Board of directors', 'Meetings', 'Conferences and publications', 'Committees', 'Amendments', 'Merger or dissolution' ) as $index => $label ) : ?><li><a href="#a<?php echo esc_attr( $index + 1 ); ?>"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?></ol><a class="btn btn-outline" href="<?php echo esc_url( cywater_asset_uri( 'docs/CYWater-Bylaws.docx' ) ); ?>" download>Download bylaws (.docx)</a></aside>
		<div class="prose entry-content" data-reveal><?php the_content(); ?></div>
	</div><?php edit_post_link( __( 'Edit this page', 'cywater' ), '<span class="admin-edit-link">', '</span>' ); ?></div></section>
	<?php
endwhile;
get_footer();
