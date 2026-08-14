<?php
/**
 * Forum articles under one topic.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php cywater_forum_term_archive( __( 'Topic', 'cywater' ) ); ?>
</main>
<?php get_footer(); ?>
