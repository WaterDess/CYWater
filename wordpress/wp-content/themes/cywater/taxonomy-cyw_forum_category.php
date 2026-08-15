<?php
/**
 * Forum articles in one category.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php cywater_forum_term_archive( __( 'Category', 'cywater' ) ); ?>
</main>
<?php get_footer(); ?>
