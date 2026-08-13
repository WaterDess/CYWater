<?php
/**
 * Events archive.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php

$events = get_posts(
	array(
		'post_type'      => 'cyw_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

usort(
	$events,
	static function ( $left, $right ) {
		$left_order  = metadata_exists( 'post', $left->ID, '_cyw_event_order' ) ? (int) get_post_meta( $left->ID, '_cyw_event_order', true ) : 10000;
		$right_order = metadata_exists( 'post', $right->ID, '_cyw_event_order' ) ? (int) get_post_meta( $right->ID, '_cyw_event_order', true ) : 10000;
		if ( $left_order !== $right_order ) {
			return $left_order <=> $right_order;
		}

		$left_date  = (string) get_post_meta( $left->ID, '_cyw_start_date', true );
		$right_date = (string) get_post_meta( $right->ID, '_cyw_start_date', true );
		return strcmp( $right_date, $left_date );
	}
);

$is_gathering = static function ( $event ) {
	$source_id = (string) get_post_meta( $event->ID, '_cyw_source_id', true );
	return has_term( 'gathering', 'cyw_event_type', $event ) || str_contains( $source_id, 'annual-gathering-' );
};

$is_member_program = static function ( $event ) {
	return has_term( 'member-program', 'cyw_event_type', $event );
};

$meetings   = array_values( array_filter( $events, static fn( $event ) => ! $is_gathering( $event ) && ! $is_member_program( $event ) ) );
$gatherings = array_values( array_filter( $events, $is_gathering ) );
$programs   = array_values( array_filter( $events, $is_member_program ) );
$upcoming   = array_values(
	array_filter(
		$events,
		static fn( $event ) => 'upcoming' === get_post_meta( $event->ID, '_cyw_status', true )
	)
);
$featured   = null;

foreach ( $meetings as $meeting ) {
	if ( 'upcoming' === get_post_meta( $meeting->ID, '_cyw_status', true ) ) {
		$featured = $meeting;
		break;
	}
}

if ( ! $featured && $meetings ) {
	$featured = $meetings[0];
}

$hero_title = $featured ? rtrim( get_the_title( $featured ), '.' ) . '.' : 'CYWater events.';
$hero_lead  = $featured ? get_the_excerpt( $featured ) : 'CYWater Annual Meetings and the Annual Gathering during the AGU Fall Meeting.';
if ( $featured && 'event:annual-2026' === get_post_meta( $featured->ID, '_cyw_source_id', true ) ) {
	$hero_lead .= ' Registration will open in August.';
}

get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => 'Events',
		'title'    => $hero_title,
		'lead'     => $hero_lead,
	)
);

$render_events = static function ( $items ) {
	foreach ( $items as $event ) {
		$event_id     = $event->ID;
		$image        = cywater_featured_image_url( $event_id, 'full' );
		$start        = (string) get_post_meta( $event_id, '_cyw_start_date', true );
		$date         = (string) ( get_post_meta( $event_id, '_cyw_date_label', true ) ?: $start );
		$location     = (string) get_post_meta( $event_id, '_cyw_location', true );
		$status       = (string) get_post_meta( $event_id, '_cyw_status', true );
		$source       = (string) get_post_meta( $event_id, '_cyw_source_id', true );
		$image_alt    = (string) ( get_post_meta( $event_id, '_cyw_image_alt', true ) ?: get_the_title( $event ) );
		$year         = preg_match( '/\b(20\d{2})\b/', $date, $matches ) ? $matches[1] : get_the_date( 'Y', $event );
		$event_types  = wp_get_post_terms( $event_id, 'cyw_event_type' );
		$visual_title = ! is_wp_error( $event_types ) && $event_types ? $event_types[0]->name : 'Event';
		$focus        = str_ends_with( $source, 'annual-gathering-2017' ) ? ' is-focus-lower' : '';
		?>
		<a class="event-archive-row" href="<?php echo esc_url( get_permalink( $event ) ); ?>" data-reveal>
			<span class="event-archive-media<?php echo esc_attr( $focus ); ?>">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy">
				<?php else : ?>
					<?php
					get_template_part(
						'template-parts/title-visual',
						null,
						array(
							'title'      => $visual_title,
							'year'       => $year,
							'aria_label' => get_the_title( $event ),
						)
					);
					?>
				<?php endif; ?>
			</span>
			<span class="event-archive-copy">
				<span class="badge <?php echo esc_attr( 'upcoming' === $status ? 'badge-live' : 'badge-mute' ); ?>"><?php echo esc_html( 'upcoming' === $status ? 'Upcoming' : 'Archive' ); ?></span>
				<h3><?php echo esc_html( get_the_title( $event ) ); ?></h3>
				<span class="meta">
					<?php echo esc_html( $date ); ?>
					<?php if ( $location ) : ?> &middot; <?php echo esc_html( $location ); ?><?php endif; ?>
				</span>
				<span class="event-archive-lead"><?php echo esc_html( get_the_excerpt( $event ) ); ?></span>
			</span>
			<span class="link">View details</span>
		</a>
		<?php
	}
};

$render_upcoming = static function ( $items ) {
	foreach ( $items as $event ) {
		$event_id    = $event->ID;
		$image       = cywater_featured_image_url( $event_id, 'cywater-card' );
		$date        = (string) ( get_post_meta( $event_id, '_cyw_date_label', true ) ?: get_post_meta( $event_id, '_cyw_start_date', true ) );
		$location    = (string) get_post_meta( $event_id, '_cyw_location', true );
		$event_types = wp_get_post_terms( $event_id, 'cyw_event_type' );
		$type        = ! is_wp_error( $event_types ) && $event_types ? $event_types[0]->name : 'Event';
		$year        = preg_match( '/\b(20\d{2})\b/', $date, $matches ) ? $matches[1] : get_the_date( 'Y', $event );
		?>
		<a class="upcoming-event-card" href="<?php echo esc_url( get_permalink( $event ) ); ?>">
			<span class="upcoming-event-media">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $event ) ); ?>" loading="lazy">
				<?php else : ?>
					<?php
					get_template_part(
						'template-parts/title-visual',
						null,
						array(
							'title'      => $type,
							'year'       => $year,
							'aria_label' => get_the_title( $event ),
						)
					);
					?>
				<?php endif; ?>
			</span>
			<span class="upcoming-event-body">
				<span class="eyebrow upcoming-event-type"><?php echo esc_html( $type ); ?></span>
				<h3><?php echo esc_html( get_the_title( $event ) ); ?></h3>
				<span class="meta">
					<?php echo esc_html( $date ); ?>
					<?php if ( $location ) : ?> &middot; <?php echo esc_html( $location ); ?><?php endif; ?>
				</span>
				<span class="link">View details</span>
			</span>
		</a>
		<?php
	}
};
?>
<section class="section event-index-section">
	<div class="container event-index-layout">
		<aside class="event-index-nav" aria-label="Event categories">
			<div class="event-index-nav-inner">
				<span class="eyebrow">Browse</span>
				<nav>
					<?php if ( $upcoming ) : ?><a href="#upcoming">Upcoming</a><?php endif; ?>
					<a href="#annual-meetings">Annual Meetings</a>
					<a href="#annual-gathering">Annual Gathering</a>
					<?php if ( $programs ) : ?><a href="#member-programs">Member Programs</a><?php endif; ?>
				</nav>
			</div>
		</aside>

		<div class="event-index-content">
			<?php if ( $upcoming ) : ?>
			<section id="upcoming" class="event-category-section event-upcoming" aria-labelledby="upcoming-title">
				<div class="event-category-heading">
					<div class="section-head">
						<span class="eyebrow">On the horizon</span>
						<h2 id="upcoming-title">Upcoming</h2>
					</div>
					<div class="event-carousel-controls" aria-label="Upcoming event carousel controls">
						<button type="button" data-carousel-previous aria-label="Show previous upcoming events">&larr;</button>
						<button type="button" data-carousel-next aria-label="Show next upcoming events">&rarr;</button>
					</div>
				</div>
				<div class="upcoming-event-carousel" data-event-carousel tabindex="0" aria-label="Upcoming events">
					<?php $render_upcoming( $upcoming ); ?>
				</div>
			</section>
			<?php endif; ?>

			<section id="annual-meetings" class="event-category-section" aria-labelledby="annual-meetings-title">
			<div class="section-head">
				<span class="eyebrow">Conference series</span>
				<h2 id="annual-meetings-title">Annual Meetings</h2>
			</div>
			<div class="event-archive-list">
				<?php $render_events( $meetings ); ?>
			</div>
		</section>

			<section id="annual-gathering" class="event-category-section" aria-labelledby="annual-gathering-title">
			<div class="section-head">
				<span class="eyebrow">AGU tradition</span>
				<h2 id="annual-gathering-title">Annual Gathering</h2>
			</div>
			<div class="event-archive-list">
				<?php $render_events( $gatherings ); ?>
			</div>
			</section>

			<?php if ( $programs ) : ?>
			<section id="member-programs" class="event-category-section" aria-labelledby="member-programs-title">
				<div class="section-head">
					<span class="eyebrow">Member participation</span>
					<h2 id="member-programs-title">Member Programs</h2>
				</div>
				<div class="event-archive-list">
					<?php $render_events( $programs ); ?>
				</div>
			</section>
			<?php endif; ?>
		</div>
	</div>
</section>
</main>
<?php get_footer(); ?>
