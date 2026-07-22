<?php
/**
 * CYWater homepage.
 *
 * @package CYWater
 */
get_header();
?>
<main>
	<section class="hero">
		<div class="container">
			<svg class="hero-watermark" viewBox="0 0 200 200" aria-hidden="true"><path d="M100 20C100 20 40 80 40 130a60 60 0 0 0 120 0C160 80 100 20 100 20Z" fill="none" stroke="#0F766E" stroke-width="2"/></svg>
			<div class="hero-inner" data-reveal>
				<span class="eyebrow"><?php echo esc_html( cywater_page_field( 'eyebrow', 'International Association · Water Sciences' ) ); ?></span>
				<h1><?php echo wp_kses_post( nl2br( esc_html( cywater_page_field( 'hero_title', "Advancing water sciences,\nempowering young scholars." ) ) ) ); ?></h1>
				<p class="lead"><?php echo esc_html( cywater_page_field( 'lead', 'An international, non-profit association advancing water sciences education, research, and professional development through scientific exchange, publications, and conferences.' ) ); ?></p>
				<div class="hero-actions">
					<a class="btn btn-accent btn-lg" href="<?php echo esc_url( home_url( '/events/' ) ); ?>">Upcoming events</a>
					<a class="btn btn-outline btn-lg" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About CYWater</a>
				</div>
			</div>
		</div>
	</section>

	<section class="section-tight">
		<div class="container">
			<div class="highlight-strip" data-reveal>
				<div class="stat"><div class="stat-num">2011</div><div class="stat-label">Founded</div></div>
				<div class="stat"><div class="stat-num">30+</div><div class="stat-label">Countries &amp; regions</div></div>
				<div class="stat"><div class="stat-num">13</div><div class="stat-label">Annual Meetings held</div></div>
				<div class="stat"><div class="stat-num">14</div><div class="stat-label">Award years</div></div>
			</div>
		</div>
	</section>

	<section class="section mission-band">
		<div class="container">
			<div class="mission-grid">
				<div data-reveal>
					<span class="eyebrow">Our mission</span>
					<h2 style="margin-top:var(--sp-3); color:var(--white)">Advancing water sciences for the public benefit.</h2>
					<p class="lead" style="color:rgba(255,255,255,.78); margin-top:var(--sp-4)">CYWater advances water sciences education, research, and professional development by empowering young, early-career, and other professionals through scientific exchange, publications, and conferences.</p>
				</div>
				<div class="mission-stats" data-reveal>
					<div class="stat"><div class="stat-num">2011</div><div class="stat-label" style="color:rgba(255,255,255,.6)">Association founded</div></div>
					<div class="stat"><div class="stat-num">2013</div><div class="stat-label" style="color:rgba(255,255,255,.6)">Best Paper Award established</div></div>
					<div class="stat"><div class="stat-num">2013</div><div class="stat-label" style="color:rgba(255,255,255,.6)">First Annual Meeting</div></div>
					<div class="stat"><div class="stat-num">2026</div><div class="stat-label" style="color:rgba(255,255,255,.6)">Next Annual Meeting in Nanjing</div></div>
				</div>
			</div>
		</div>
	</section>

	<section class="section pillars">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center">What we do</span><h2>Exchange, conferences, and recognition.</h2></div>
			<div class="grid grid-4">
				<?php
				$features = array(
					array( 'Annual Meetings', 'Our flagship scientific gathering has connected water scholars every year since 2013.', '/events/' ),
					array( 'Annual Gathering', 'A community gathering held during the AGU Fall Meeting.', '/events/#annual-gathering' ),
					array( 'Best Paper Award', 'Recognising outstanding contributions to water sciences since 2012.', '/awards/' ),
					array( 'Global Partnerships', 'Institutions and societies help expand scientific exchange and early-career development.', '/membership/#partnerships' ),
				);
				foreach ( $features as $feature ) :
					?>
					<div class="feature" data-reveal><h3><?php echo esc_html( $feature[0] ); ?></h3><p><?php echo esc_html( $feature[1] ); ?></p><a class="link" href="<?php echo esc_url( home_url( $feature[2] ) ); ?>">Learn more</a></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section-tint">
		<div class="container">
			<div class="section-head center" data-reveal><span class="eyebrow center">Our themes</span><h2>Frontiers of hydrological sciences.</h2></div>
			<div class="tag-list" style="justify-content:center" data-reveal>
				<?php foreach ( array( 'Hydroclimate & Global Change', 'Hydrological Hazards', 'Ecohydrology & Geomorphology', 'Observation & Modelling', 'Surface & Groundwater', 'Water Quality', 'Remote Sensing', 'AI for Water Science', 'Climate Adaptation', 'Water Security' ) as $theme ) : ?>
					<span class="badge badge-teal"><?php echo esc_html( $theme ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="latest-head" data-reveal><div class="section-head" style="margin-bottom:0"><span class="eyebrow">Highlights</span><h2>Meetings, recognition &amp; community.</h2></div><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/news/' ) ); ?>">Explore news</a></div>
			<div class="grid grid-3">
				<?php
				$latest = new WP_Query(
					array(
						'post_type'      => array( 'post', 'cyw_event', 'cyw_award' ),
						'posts_per_page' => 3,
						'post_status'    => 'publish',
					)
				);
				while ( $latest->have_posts() ) :
					$latest->the_post();
					get_template_part( 'template-parts/content-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="cta-band" data-reveal><span class="eyebrow" style="color:var(--teal-soft)">Get involved</span><h2 style="margin-top:var(--sp-3)">Join the CYWater community.</h2><p class="lead">Connect through scientific exchange, annual meetings, and recognition of outstanding water research.</p><div class="hero-actions" style="margin-top:var(--sp-5)"><a class="btn btn-primary btn-lg" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>">Membership options</a><a class="btn btn-ghost btn-lg" href="<?php echo esc_url( home_url( '/about/' ) ); ?>" style="color:#fff">Read our story</a></div></div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
