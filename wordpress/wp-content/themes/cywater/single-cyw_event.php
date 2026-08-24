<?php
/**
 * Event detail.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php
while ( have_posts() ) :
	the_post();
	$date      = get_post_meta( get_the_ID(), '_cyw_date_label', true ) ?: get_post_meta( get_the_ID(), '_cyw_start_date', true );
	$location  = get_post_meta( get_the_ID(), '_cyw_location', true );
	$format    = get_post_meta( get_the_ID(), '_cyw_format', true ) ?: 'Event';
	$attendees = get_post_meta( get_the_ID(), '_cyw_attendees', true );
	$status    = get_post_meta( get_the_ID(), '_cyw_status', true );
	$is_logo_call = '1' === (string) get_post_meta( get_the_ID(), '_cywater_logo_call_enabled', true );
	$reveal_attr  = $is_logo_call ? '' : ' data-reveal';
	$event_summary = cywater_event_summary( get_the_ID() );
	?>
	<section class="event-hero">
		<div class="container" data-page-enter="hero">
			<div class="crumbs" style="color:rgba(255,255,255,.6)"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:rgba(255,255,255,.6)">Home</a><span>/</span><a href="<?php echo esc_url( get_post_type_archive_link( 'cyw_event' ) ); ?>" style="color:rgba(255,255,255,.6)">Events</a></div>
			<span class="badge badge-teal" style="margin-bottom:var(--sp-3)"><?php echo esc_html( $format ); ?></span>
			<h1><?php the_title(); ?></h1>
			<div class="event-meta-row">
				<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg><?php echo esc_html( $date ); ?></span>
				<?php if ( $location ) : ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg><?php echo esc_html( $location ); ?></span><?php endif; ?>
				<?php if ( $attendees ) : ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/></svg><?php echo esc_html( $attendees ); ?></span><?php endif; ?>
				<span class="badge <?php echo esc_attr( 'upcoming' === $status ? 'badge-live' : 'badge-mute' ); ?>"><?php echo esc_html( 'upcoming' === $status ? 'Upcoming' : 'Archive' ); ?></span>
			</div>
		</div>
	</section>
	<section class="section">
		<div class="container">
			<div class="event-body"><div>
				<?php do_action( 'cywater_event_before_content', get_the_ID() ); ?>
				<span class="eyebrow">About this event</span>
				<?php if ( ! $is_logo_call && $event_summary ) : ?><p class="lead" style="margin:var(--sp-4) 0 var(--sp-5)"<?php echo $reveal_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $event_summary ); ?></p><?php endif; ?>
				<div class="prose entry-content"<?php echo $reveal_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo cywater_article_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div style="text-align:center; margin-top:var(--sp-6)"><a class="link" href="<?php echo esc_url( get_post_type_archive_link( 'cyw_event' ) ); ?>">Back to Events</a></div>
			</div></div>
		</div>
	</section>
	<?php
endwhile;
?>
</main>
<?php get_footer();
