<?php
/**
 * About page, preserving the static site's editorial structure.
 *
 * @package CYWater
 */
get_header();
while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero' );
	?>
	<section class="section"><div class="container"><div class="about-intro-grid">
		<div data-reveal><span class="eyebrow">Our mission</span><div class="stack entry-content" style="--flow:1.25rem; margin-top:var(--sp-4)"><?php the_content(); ?></div>
			<hr class="divider" style="margin:var(--sp-7) 0"><span class="eyebrow">What we value</span>
			<div class="values-list" style="margin-top:var(--sp-5)">
				<?php $value_index = 0; ?>
				<?php foreach ( array( 'Open participation' => 'Membership is open to individuals who support the Association\'s mission and are interested in water sciences.', 'Scientific exchange' => 'Conferences, publications, workshops, and professional exchange connect water-science communities worldwide.', 'Public benefit' => 'The Association\'s scientific, charitable, and educational work is conducted for public benefit.', 'Accountable governance' => 'A Board of Directors oversees strategy, finances, committees, legal compliance, and the Association\'s mission.' ) as $label => $copy ) : ?>
					<?php ++$value_index; ?>
					<div class="value-item"><span class="value-num"><?php echo esc_html( sprintf( '%02d', $value_index ) ); ?></span><div><h3 style="font-size:var(--fs-md)"><?php echo esc_html( $label ); ?></h3><p style="font-size:var(--fs-sm); margin-top:.3rem"><?php echo esc_html( $copy ); ?></p></div></div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="media-frame" data-reveal><img src="<?php echo esc_url( cywater_asset_uri( 'img/article/founding-2011.jpg' ) ); ?>" alt="CYWater founding gathering in 2011" loading="lazy"></div>
	</div></div></section>
	<section class="section section-tint"><div class="container container-narrow"><div class="section-head center" data-reveal><span class="eyebrow center">Milestones</span><h2>Milestones</h2></div><div class="timeline" data-reveal>
		<?php foreach ( array( '2011' => 'CYWater was founded as an international water-science community.', '2012' => 'The Young Scientist Best Paper Award was established.', '2013' => 'The first Annual Meeting was held in Beijing.', '2020' => 'The Annual Meeting moved online and reached participants across multiple continents.', '2026' => 'The next Annual Meeting will take place in Nanjing, China.' ) as $year => $copy ) : ?><div class="timeline-item"><div class="timeline-year"><?php echo esc_html( $year ); ?></div><p style="margin-top:.3rem"><?php echo esc_html( $copy ); ?></p></div><?php endforeach; ?>
	</div></div></section>
	<section class="section"><div class="container container-narrow"><div class="section-head center" data-reveal><span class="eyebrow center">Governance</span><h2>Governance</h2></div><p class="lead" style="text-align:center; margin-bottom:var(--sp-6)">The Board of Directors holds the Association's corporate powers, sets strategic direction, oversees finances, appoints committees, and ensures compliance with law and mission. Current appointments are being updated.</p><div class="hero-actions" style="justify-content:center"><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/board/' ) ); ?>">Board</a><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>">Bylaws</a></div></div></section>
	<?php edit_post_link( __( 'Edit this page', 'cywater' ), '<span class="admin-edit-link">', '</span>' ); ?>
	<?php
endwhile;
get_footer();
