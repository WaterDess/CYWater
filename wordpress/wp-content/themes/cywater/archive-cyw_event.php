<?php
/**
 * Events archive.
 *
 * @package CYWater
 */

get_header();

$events = array();
while ( have_posts() ) {
	the_post();
	$events[] = get_post();
}
wp_reset_postdata();

$is_gathering = static function ( $event ) {
	$source_id = (string) get_post_meta( $event->ID, '_cyw_source_id', true );
	return has_term( 'gathering', 'cyw_event_type', $event ) || str_contains( $source_id, 'annual-gathering-' );
};

$meetings   = array_values( array_filter( $events, static fn( $event ) => ! $is_gathering( $event ) ) );
$gatherings = array_values( array_filter( $events, $is_gathering ) );
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
		$event_id = $event->ID;
		$image    = cywater_featured_image_url( $event_id, 'cywater-card' );
		$start    = (string) get_post_meta( $event_id, '_cyw_start_date', true );
		$date     = (string) ( get_post_meta( $event_id, '_cyw_date_label', true ) ?: $start );
		$location = (string) get_post_meta( $event_id, '_cyw_location', true );
		$status   = (string) get_post_meta( $event_id, '_cyw_status', true );
		$source   = (string) get_post_meta( $event_id, '_cyw_source_id', true );
		$year     = preg_match( '/\b(20\d{2})\b/', $date, $matches ) ? $matches[1] : get_the_date( 'Y', $event );
		$focus    = str_ends_with( $source, 'annual-gathering-2017' ) ? ' is-focus-lower' : '';
		?>
		<a class="event-archive-row" href="<?php echo esc_url( get_permalink( $event ) ); ?>" data-reveal>
			<span class="event-archive-media<?php echo esc_attr( $focus ); ?>">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $event ) ); ?>" loading="lazy">
				<?php else : ?>
					<span class="event-year-mark"><?php echo esc_html( $year ); ?></span>
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
?>
<section class="section">
	<div class="container container-narrow">
		<section aria-labelledby="annual-meetings-title">
			<div class="section-head">
				<span class="eyebrow">Conference series</span>
				<h2 id="annual-meetings-title">Annual Meetings</h2>
			</div>
			<div class="event-archive-list">
				<?php $render_events( $meetings ); ?>
			</div>
		</section>

		<section id="annual-gathering" class="event-series-section" aria-labelledby="annual-gathering-title">
			<div class="section-head">
				<span class="eyebrow">AGU tradition</span>
				<h2 id="annual-gathering-title">Annual Gathering</h2>
			</div>
			<div class="event-archive-list">
				<?php $render_events( $gatherings ); ?>
			</div>
		</section>
	</div>
</section>
<?php get_footer(); ?>
