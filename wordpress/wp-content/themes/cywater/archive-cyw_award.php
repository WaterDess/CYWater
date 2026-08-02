<?php
/**
 * Awards yearbook.
 *
 * @package CYWater
 */

get_header();
?>
<main>
<?php
get_template_part(
	'template-parts/page-hero',
	null,
	array(
		'eyebrow' => 'Recognition',
		'title'    => 'Young Scientist Best Paper Award.',
		'lead'     => 'CYWater established the Young Scientist Best Paper Award in 2012. It is awarded annually to an individual for outstanding contributions to water sciences.',
	)
);

$awards = new WP_Query(
	array(
		'post_type'      => 'cyw_award',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_cyw_year',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	)
);

$render_paper = static function ( $paper ) {
	if ( empty( $paper ) ) {
		return;
	}
	?>
	<div class="award-paper">
		<strong><?php echo esc_html( $paper['author'] ?? '' ); ?></strong>
		<span><?php echo esc_html( $paper['title'] ?? '' ); ?></span>
		<?php if ( ! empty( $paper['journal'] ) ) : ?><span class="award-journal"><?php echo esc_html( $paper['journal'] ); ?></span><?php endif; ?>
		<?php if ( ! empty( $paper['doi'] ) ) : ?><a class="award-doi" href="<?php echo esc_url( 'https://doi.org/' . $paper['doi'] ); ?>" target="_blank" rel="noopener">doi: <?php echo esc_html( $paper['doi'] ); ?></a><?php endif; ?>
	</div>
	<?php
};
?>
<section class="section-tight">
	<div class="container container-narrow">
		<div class="award-intro" data-reveal>
			<div><span class="eyebrow">Eligibility</span><h2 style="margin-top:var(--sp-3)">Recognizing early-career research.</h2></div>
			<p class="lead">Applicants must be no more than 35 years old when submitting an application. Each yearbook entry below identifies the Best Paper Award and, where applicable, Outstanding Papers.</p>
		</div>
	</div>
</section>
<section class="section section-tint">
	<div class="container container-narrow">
		<div class="section-head" data-reveal><span class="eyebrow">2012&ndash;2025</span><h2>Award yearbook.</h2></div>
		<div id="awards-yearbook" class="awards-yearbook">
			<?php
			while ( $awards->have_posts() ) :
				$awards->the_post();
				$record = json_decode( (string) get_post_meta( get_the_ID(), '_cyw_award_record', true ), true );
				$record = is_array( $record ) ? $record : array();
				$year   = get_post_meta( get_the_ID(), '_cyw_year', true );
				$best   = $record['bestPaper'] ?? array();
				$best['author']  = get_post_meta( get_the_ID(), '_cyw_recipient', true ) ?: ( $best['author'] ?? '' );
				$best['title']   = get_post_meta( get_the_ID(), '_cyw_paper_title', true ) ?: ( $best['title'] ?? '' );
				$best['journal'] = get_post_meta( get_the_ID(), '_cyw_journal', true ) ?: ( $best['journal'] ?? '' );
				$article_id      = get_post_meta( get_the_ID(), '_cyw_article_id', true ) ?: ( $record['articleId'] ?? '' );
				$article_url     = $article_id ? cywater_source_permalink( 'news:' . $article_id ) : '';
				?>
				<article class="award-year" id="award-<?php echo esc_attr( $year ); ?>" data-reveal>
					<div class="award-year-label"><?php echo esc_html( $year ); ?></div>
					<div class="award-year-content">
						<?php if ( ! empty( $best['author'] ) || ! empty( $best['title'] ) ) : ?>
							<div class="award-group"><h3>Best Paper Award</h3><?php $render_paper( $best ); ?></div>
							<?php if ( ! empty( $record['outstanding'] ) ) : ?>
								<div class="award-group"><h4>Outstanding Papers</h4><?php foreach ( $record['outstanding'] as $paper ) { $render_paper( $paper ); } ?></div>
							<?php endif; ?>
						<?php else : ?>
							<p class="award-pending"><?php echo esc_html( $record['note'] ?? 'Award record pending confirmation.' ); ?></p>
						<?php endif; ?>
						<?php if ( $article_url ) : ?><a class="link" href="<?php echo esc_url( $article_url ); ?>">Read award announcement</a><?php endif; ?>
					</div>
				</article>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
</main>
<?php get_footer(); ?>
