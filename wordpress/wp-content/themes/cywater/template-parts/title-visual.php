<?php
/**
 * Reusable title-and-year visual for records without a verified photograph.
 *
 * @package CYWater
 */

$visual_title = trim( (string) ( $args['title'] ?? '' ) );
$visual_year  = trim( (string) ( $args['year'] ?? '' ) );
$aria_label   = trim( (string) ( $args['aria_label'] ?? $visual_title . ' ' . $visual_year ) );
?>
<span class="title-visual" role="img" aria-label="<?php echo esc_attr( $aria_label ); ?>">
	<strong><?php echo esc_html( $visual_title ); ?></strong>
	<span class="title-visual-year"><?php echo esc_html( $visual_year ); ?></span>
</span>
