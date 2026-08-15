<?php
/**
 * CYWater homepage.
 *
 * @package CYWater
 */

get_header();

$highlights = array(
	array(
		'source' => 'news:13th-annual-3rd',
		'tag'    => 'Conference',
		'action' => 'Read more',
		'date'   => '2025-10-29',
		'title'  => '13th Annual Meeting — 3rd round notice',
		'excerpt' => 'Co-hosted with the Yangtze Technology & Economy Society Youth Committee. Full programme and venue details released.',
		'alt'    => 'Participants at the 2025 CYWater Annual Meeting',
	),
	array(
		'source' => 'event:annual-gathering-2024',
		'tag'    => 'Annual Gathering',
		'action' => 'View gathering',
		'date'   => '2024-12-10',
		'title'  => 'CYWater Annual Gathering — Washington, DC 2024',
		'excerpt' => 'Members gathered during AGU24 for community exchange, conversation, and professional connection.',
		'alt'    => 'CYWater members at the 2024 Annual Gathering in Washington, DC',
	),
	array(
		'source' => 'news:12th-summer-xian',
		'tag'    => 'Conference',
		'action' => 'Read more',
		'date'   => '2024-12-08',
		'title'  => "12th Summer Meeting — Xi'an, Aug 2024",
		'excerpt' => 'Hosted by Xi\'an University of Technology. Theme: "Gathering strength for new quality productive forces in water."',
		'alt'    => 'Participants at the 2024 CYWater Annual Meeting',
	),
);

$render_highlight = static function ( $highlight ) {
	$post = cywater_source_post( $highlight['source'] );
	if ( ! $post ) {
		return;
	}

	$image = cywater_featured_image_url( $post->ID, 'full' );
	$date  = $highlight['date'] ?? ( 'cyw_event' === $post->post_type
		? (string) get_post_meta( $post->ID, '_cyw_start_date', true )
		: get_the_date( 'Y-m-d', $post ) );
	$title = $highlight['title'] ?? get_the_title( $post );
	$excerpt = $highlight['excerpt'] ?? get_the_excerpt( $post );
	$alt = $highlight['alt'] ?? $title;
	?>
	<article class="card" data-reveal>
		<?php if ( $image ) : ?>
			<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<div class="card-media"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy"></div>
			</a>
		<?php endif; ?>
		<div class="card-body">
			<div class="card-meta"><span class="card-tag"><?php echo esc_html( $highlight['tag'] ); ?></span><span>&middot; <?php echo esc_html( $date ); ?></span></div>
			<h3 class="card-title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<p class="card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<a class="link" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( $highlight['action'] ); ?></a>
		</div>
	</article>
	<?php
};

$association_name = 'International Association of Contemporary Young Scholars in Water Sciences (CYWater)';
$hero_title       = cywater_page_field( 'hero_title', "Advancing water sciences,\nempowering young scholars." );
$hero_title_lines = preg_split( '/\R+/', trim( $hero_title ) );

if ( 1 === count( $hero_title_lines ) && str_contains( $hero_title, ',' ) ) {
	$hero_title_parts = explode( ',', $hero_title, 2 );
	$hero_title_lines = array( trim( $hero_title_parts[0] ) . ',', trim( $hero_title_parts[1] ) );
}
?>
<main>
	<section class="hero">
		<div class="container">
			<svg class="hero-watermark" viewBox="0 0 200 200" aria-hidden="true"><path d="M100 20C100 20 40 80 40 130a60 60 0 0 0 120 0C160 80 100 20 100 20Z" fill="none" stroke="#0F766E" stroke-width="2"/></svg>
			<div class="hero-inner" data-reveal>
				<span class="eyebrow hero-identity" aria-label="<?php echo esc_attr( $association_name ); ?>">
					<span class="hero-identity-copy" aria-hidden="true">
						<span class="hero-identity-line">International Association of Contemporary</span>
						<span class="hero-identity-line">Young Scholars in Water Sciences (CYWater)</span>
					</span>
				</span>
				<h1>
					<?php foreach ( $hero_title_lines as $hero_title_line ) : ?>
						<span class="hero-title-line"><?php echo esc_html( $hero_title_line ); ?></span>
					<?php endforeach; ?>
				</h1>
				<p class="lead"><?php echo esc_html( cywater_page_field( 'lead', 'An international, non-profit association advancing water sciences education, research, and professional development — through scientific exchange, publications, and conferences.' ) ); ?></p>
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
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/><path d="m9 14 2 2 4-4"/></svg></div><h3>Annual Meetings</h3><p>Our flagship scientific gathering has connected water scholars every year since 2013.</p><a class="link" href="<?php echo esc_url( home_url( '/events/' ) ); ?>">Learn more</a></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4Z"/><path d="M4 4v12a4 4 0 0 0 4 4"/><path d="M8 9h8M8 13h5"/></svg></div><h3>Annual Gathering</h3><p>A community gathering held during the AGU Fall Meeting, continuing a tradition established in 2011.</p><a class="link" href="<?php echo esc_url( home_url( '/events/#annual-gathering' ) ); ?>">Learn more</a></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="5"/><path d="m9 13-2 8 5-3 5 3-2-8"/></svg></div><h3>Best Paper Award</h3><p>The Young Scientist Best Paper Award, recognising outstanding contributions to water sciences since 2012.</p><a class="link" href="<?php echo esc_url( home_url( '/awards/' ) ); ?>">Learn more</a></div>
				<div class="feature" data-reveal><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><circle cx="17" cy="10" r="2.5"/><path d="M15 21a5 5 0 0 1 7-4.5"/></svg></div><h3>Global Partnerships</h3><p>Universities, research institutes, industry, societies, and foundations help expand scientific exchange and early-career development.</p><a class="link" href="<?php echo esc_url( home_url( '/membership/#partnerships' ) ); ?>">Learn more</a></div>
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
			<div class="latest-head" data-reveal><div class="section-head" style="margin-bottom:0"><span class="eyebrow">Highlights</span><h2>Meetings, recognition &amp; community.</h2></div><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/events/' ) ); ?>">Explore events</a></div>
			<div class="grid grid-3"><?php foreach ( $highlights as $highlight ) { $render_highlight( $highlight ); } ?></div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="cta-band" data-reveal><span class="eyebrow" style="color:var(--teal-soft)">Get involved</span><h2 style="margin-top:var(--sp-3)">Join the CYWater community.</h2><p class="lead">Since 2011, CYWater has connected water scholars through scientific exchange, annual meetings, and the Young Scientist Best Paper Award.</p><div class="hero-actions" style="margin-top:var(--sp-5)"><a class="btn btn-primary btn-lg" href="<?php echo esc_url( home_url( '/events/' ) ); ?>">See our events</a><a class="btn btn-ghost btn-lg" href="<?php echo esc_url( home_url( '/about/' ) ); ?>" style="color:#fff">Read our story</a></div></div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
