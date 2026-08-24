<?php
/**
 * Front-end workspace for member-authored Forum articles.
 *
 * Business rules and writes remain in CYWater Forum. This template owns only
 * the public layout and accepted CYWater control language.
 *
 * @package CYWater
 */

get_header();

$workspace_ready = class_exists( 'CYWater_Forum_Workspace' ) && class_exists( 'CYWater_Forum_Roles' );
$user_id         = get_current_user_id();
$is_logged_in    = is_user_logged_in();
$blockers        = $workspace_ready && $is_logged_in ? CYWater_Forum_Roles::submission_blockers( $user_id ) : array( 'signed_out' );
$can_submit      = $workspace_ready && $is_logged_in && ! $blockers && ! CYWater_Forum_Roles::is_staff( $user_id );
$articles        = $workspace_ready && $is_logged_in ? CYWater_Forum_Workspace::articles_for_user( $user_id ) : array();
$edit_id         = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing         = null;
$page_error      = '';

if ( $edit_id && $can_submit ) {
	$editing = CYWater_Forum_Workspace::editable_post( $user_id, $edit_id );
	if ( is_wp_error( $editing ) ) {
		$page_error = $editing->get_error_message();
		$editing    = null;
	}
}

$notice_code = isset( $_GET['forum_notice'] ) ? sanitize_key( wp_unslash( $_GET['forum_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error_code  = isset( $_GET['forum_error'] ) ? sanitize_key( wp_unslash( $_GET['forum_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$notice      = $workspace_ready ? CYWater_Forum_Workspace::result_message( 'notice', $notice_code ) : '';
$error       = $workspace_ready ? CYWater_Forum_Workspace::result_message( 'error', $error_code ) : '';
$error       = $error ?: $page_error;

$categories = $workspace_ready ? get_terms( array( 'taxonomy' => CYWater_Forum_Content::CATEGORY, 'hide_empty' => false, 'orderby' => 'name' ) ) : array();
$topics     = $workspace_ready ? get_terms( array( 'taxonomy' => CYWater_Forum_Content::TOPIC, 'hide_empty' => false, 'orderby' => 'name' ) ) : array();
$category_ids = $editing instanceof WP_Post ? wp_get_object_terms( $editing->ID, CYWater_Forum_Content::CATEGORY, array( 'fields' => 'ids' ) ) : array();
$topic_ids    = $editing instanceof WP_Post ? wp_get_object_terms( $editing->ID, CYWater_Forum_Content::TOPIC, array( 'fields' => 'ids' ) ) : array();
$selected_category = ! is_wp_error( $category_ids ) ? absint( $category_ids[0] ?? 0 ) : 0;
$selected_topic    = ! is_wp_error( $topic_ids ) ? absint( $topic_ids[0] ?? 0 ) : 0;
$workspace_url     = $workspace_ready ? CYWater_Forum_Workspace::url() : home_url( '/forum-workspace/' );

$blocker_copy = array(
	'signed_out'          => array( __( 'Sign in to open your Forum workspace.', 'cywater' ), cywater_login_url( $workspace_url ), __( 'Sign in', 'cywater' ) ),
	'email_unverified'    => array( __( 'Verify your email address before saving or submitting a Forum article.', 'cywater' ), home_url( '/verify-email/' ), __( 'Verify email', 'cywater' ) ),
	'membership_inactive' => array( __( 'An active Student, Professional, or Lifetime membership is required to submit Forum articles.', 'cywater' ), home_url( '/membership/' ), __( 'View membership', 'cywater' ) ),
	'not_endorsed'        => array( __( 'This account does not meet the current Forum participation policy.', 'cywater' ), home_url( '/forum/' ), __( 'Return to Forum', 'cywater' ) ),
);
?>
<main>
	<?php
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'eyebrow' => __( 'Member workspace', 'cywater' ),
			'title'   => __( 'Write for the CYWater Forum.', 'cywater' ),
			'lead'    => __( 'Save a draft or publish your own article directly, then update it from this workspace without entering WordPress administration.', 'cywater' ),
			'actions' => '<a class="btn btn-outline" href="' . esc_url( get_post_type_archive_link( 'cyw_forum_post' ) ) . '">' . esc_html__( 'Browse Forum', 'cywater' ) . '</a>',
		)
	);
	?>

	<section class="section forum-workspace-section">
		<div class="container">
			<?php if ( ! $workspace_ready ) : ?>
				<div class="forum-workspace-notice is-error" role="alert"><?php esc_html_e( 'The Forum workspace is temporarily unavailable.', 'cywater' ); ?></div>
			<?php else : ?>
				<?php if ( $notice ) : ?><div class="forum-workspace-notice is-success" role="status"><?php echo esc_html( $notice ); ?></div><?php endif; ?>
				<?php if ( $error ) : ?><div class="forum-workspace-notice is-error" role="alert"><?php echo esc_html( $error ); ?></div><?php endif; ?>

				<?php if ( CYWater_Forum_Roles::is_staff( $user_id ) ) : ?>
					<div class="forum-workspace-gate card">
						<span class="badge badge-teal"><?php esc_html_e( 'Staff workspace', 'cywater' ); ?></span>
						<h2><?php esc_html_e( 'Forum moderation stays in WordPress administration.', 'cywater' ); ?></h2>
						<p><?php esc_html_e( 'Community Moderators use the protected Forum administration area to edit across authors, take down, restore or permanently delete articles, moderate discussion, and review engagement counts.', 'cywater' ); ?></p>
						<a class="btn btn-accent" href="<?php echo esc_url( admin_url( 'edit.php?post_type=cyw_forum_post' ) ); ?>"><?php esc_html_e( 'Manage Forum', 'cywater' ); ?></a>
					</div>
				<?php elseif ( ! $can_submit ) : ?>
					<div class="forum-workspace-gate card">
						<span class="badge badge-mute"><?php esc_html_e( 'Submission unavailable', 'cywater' ); ?></span>
						<h2><?php esc_html_e( 'Your articles remain visible to you.', 'cywater' ); ?></h2>
						<div class="forum-workspace-blockers">
							<?php foreach ( $blockers as $blocker ) : ?>
								<?php $item = $blocker_copy[ $blocker ] ?? array( __( 'This account is not currently eligible to submit.', 'cywater' ), home_url( '/account/' ), __( 'Open account', 'cywater' ) ); ?>
								<p><?php echo esc_html( $item[0] ); ?></p>
								<a class="btn btn-outline" href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[2] ); ?></a>
							<?php endforeach; ?>
						</div>
						<button class="btn btn-accent" type="button" disabled><?php esc_html_e( 'Submission unavailable', 'cywater' ); ?></button>
					</div>
				<?php endif; ?>

				<?php if ( $can_submit ) : ?>
					<div class="forum-workspace-layout">
						<section class="forum-workspace-editor" aria-labelledby="forum-workspace-editor-title">
							<div class="forum-workspace-heading">
								<div><span class="eyebrow"><?php echo $editing instanceof WP_Post ? esc_html__( 'Edit article', 'cywater' ) : esc_html__( 'New article', 'cywater' ); ?></span><h2 id="forum-workspace-editor-title"><?php echo $editing instanceof WP_Post ? esc_html( $editing->post_title ) : esc_html__( 'Prepare your submission.', 'cywater' ); ?></h2></div>
								<?php if ( $editing instanceof WP_Post ) : ?><a class="btn btn-ghost" href="<?php echo esc_url( $workspace_url ); ?>"><?php esc_html_e( 'Start a new article', 'cywater' ); ?></a><?php endif; ?>
							</div>
							<form class="forum-workspace-form card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
								<input type="hidden" name="action" value="cywater_forum_workspace_save">
								<input type="hidden" name="forum_post_id" value="<?php echo esc_attr( $editing instanceof WP_Post ? $editing->ID : 0 ); ?>">
								<?php wp_nonce_field( 'cywater_forum_workspace_save_' . ( $editing instanceof WP_Post ? $editing->ID : 0 ), 'cywater_forum_workspace_nonce' ); ?>

								<div class="field">
									<label class="field-label" for="forum-title"><?php esc_html_e( 'Article title', 'cywater' ); ?> <span class="req" aria-hidden="true">*</span></label>
									<input class="input" id="forum-title" name="forum_title" type="text" maxlength="180" required value="<?php echo esc_attr( $editing instanceof WP_Post ? $editing->post_title : '' ); ?>" <?php echo 'title_required' === $error_code ? 'aria-invalid="true"' : ''; ?>>
								</div>

								<div class="field">
									<label class="field-label" for="forum-content"><?php esc_html_e( 'Article text', 'cywater' ); ?> <span class="req" aria-hidden="true">*</span></label>
									<textarea class="textarea forum-workspace-textarea" id="forum-content" name="forum_content" required <?php echo 'content_required' === $error_code ? 'aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $editing instanceof WP_Post ? $editing->post_content : '' ); ?></textarea>
									<p class="field-hint"><?php esc_html_e( 'Use clear paragraphs. Publishing makes the article visible immediately; Community Moderators may remove content that violates Forum policy.', 'cywater' ); ?></p>
								</div>

								<div class="field-row">
									<div class="field">
										<label class="field-label" for="forum-category"><?php esc_html_e( 'Category', 'cywater' ); ?></label>
										<select class="select" id="forum-category" name="forum_category" <?php echo 'category_invalid' === $error_code ? 'aria-invalid="true"' : ''; ?>><option value="0"><?php esc_html_e( 'No category selected', 'cywater' ); ?></option><?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $selected_category, $category->term_id ); ?>><?php echo esc_html( $category->name ); ?></option><?php endforeach; endif; ?></select>
									</div>
									<div class="field">
										<label class="field-label" for="forum-topic"><?php esc_html_e( 'Topic', 'cywater' ); ?></label>
										<select class="select" id="forum-topic" name="forum_topic" <?php echo 'topic_invalid' === $error_code ? 'aria-invalid="true"' : ''; ?>><option value="0"><?php esc_html_e( 'No topic selected', 'cywater' ); ?></option><?php if ( ! is_wp_error( $topics ) ) : foreach ( $topics as $topic ) : ?><option value="<?php echo esc_attr( $topic->term_id ); ?>" <?php selected( $selected_topic, $topic->term_id ); ?>><?php echo esc_html( $topic->name ); ?></option><?php endforeach; endif; ?></select>
									</div>
								</div>

								<div class="field">
									<label class="field-label" for="forum-cover"><?php esc_html_e( 'Cover image', 'cywater' ); ?></label>
									<input class="input forum-workspace-file" id="forum-cover" name="forum_cover" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
									<p class="field-hint"><?php esc_html_e( 'Optional. JPG, PNG, or WebP; maximum 5 MB. A new upload replaces the current cover.', 'cywater' ); ?></p>
									<?php $editing_cover_url = $editing instanceof WP_Post && class_exists( 'CYWater_Forum_Covers' ) ? CYWater_Forum_Covers::url( $editing->ID ) : ''; ?>
									<?php if ( $editing_cover_url ) : ?><div class="forum-workspace-cover"><img src="<?php echo esc_url( $editing_cover_url ); ?>" alt=""></div><?php endif; ?>
								</div>

								<div class="forum-workspace-actions">
									<?php if ( $editing instanceof WP_Post ) : ?>
										<button class="btn btn-ghost forum-remove-trigger" type="submit" form="forum-remove-article" data-confirm="<?php echo esc_attr( 'publish' === $editing->post_status ? __( 'Remove this published article from the Forum?', 'cywater' ) : __( 'Delete this draft?', 'cywater' ) ); ?>"><?php echo 'publish' === $editing->post_status ? esc_html__( 'Remove article', 'cywater' ) : esc_html__( 'Delete draft', 'cywater' ); ?></button>
									<?php endif; ?>
									<?php if ( ! $editing instanceof WP_Post || 'draft' === $editing->post_status ) : ?>
										<button class="btn btn-outline" type="submit" name="forum_intent" value="draft"><?php esc_html_e( 'Save draft', 'cywater' ); ?></button>
									<?php endif; ?>
									<button class="btn btn-accent" type="submit" name="forum_intent" value="publish"><?php echo $editing instanceof WP_Post && 'publish' === $editing->post_status ? esc_html__( 'Update article', 'cywater' ) : esc_html__( 'Publish article', 'cywater' ); ?></button>
								</div>
							</form>
							<?php if ( $editing instanceof WP_Post ) : ?>
								<form id="forum-remove-article" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="cywater_forum_workspace_trash">
									<input type="hidden" name="forum_post_id" value="<?php echo esc_attr( (string) $editing->ID ); ?>">
									<?php wp_nonce_field( 'cywater_forum_workspace_trash_' . $editing->ID, 'cywater_forum_workspace_trash_nonce' ); ?>
								</form>
							<?php endif; ?>
						</section>
					</div>
				<?php endif; ?>

				<?php if ( $is_logged_in && ! CYWater_Forum_Roles::is_staff( $user_id ) ) : ?>
					<section class="forum-workspace-articles" aria-labelledby="forum-workspace-articles-title">
						<div class="section-head"><span class="eyebrow"><?php esc_html_e( 'My articles', 'cywater' ); ?></span><h2 id="forum-workspace-articles-title"><?php esc_html_e( 'Drafts and published work.', 'cywater' ); ?></h2></div>
						<?php if ( ! $articles ) : ?>
							<p class="lead"><?php esc_html_e( 'You have not started a Forum article yet.', 'cywater' ); ?></p>
						<?php else : ?>
							<div class="forum-workspace-status-grid">
								<?php foreach ( array( 'draft', 'publish' ) as $status ) : ?>
									<?php $status_articles = array_values( array_filter( $articles, static fn( $article ) => $status === $article->post_status ) ); ?>
									<section class="forum-workspace-status card" aria-labelledby="forum-workspace-status-<?php echo esc_attr( $status ); ?>">
										<div class="forum-workspace-status-head"><span class="badge <?php echo 'publish' === $status ? 'badge-live' : 'badge-mute'; ?>" id="forum-workspace-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( CYWater_Forum_Workspace::status_label( $status ) ); ?></span><span><?php echo esc_html( (string) count( $status_articles ) ); ?></span></div>
										<?php if ( ! $status_articles ) : ?><p class="field-hint"><?php esc_html_e( 'No articles in this state.', 'cywater' ); ?></p><?php endif; ?>
										<?php foreach ( $status_articles as $article ) : ?>
											<article class="forum-workspace-article-row">
												<h3><?php echo esc_html( $article->post_title ?: __( 'Untitled article', 'cywater' ) ); ?></h3>
												<p><?php echo esc_html( sprintf( __( 'Updated %s', 'cywater' ), get_the_modified_date( get_option( 'date_format' ), $article ) ) ); ?></p>
											<div class="forum-workspace-row-actions">
												<?php if ( $can_submit ) : ?><a class="link" href="<?php echo esc_url( add_query_arg( 'edit', $article->ID, $workspace_url ) ); ?>"><?php esc_html_e( 'Edit article', 'cywater' ); ?></a><?php else : ?><span class="field-hint"><?php esc_html_e( 'Editing is paused.', 'cywater' ); ?></span><?php endif; ?>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
													<input type="hidden" name="action" value="cywater_forum_workspace_trash">
													<input type="hidden" name="forum_post_id" value="<?php echo esc_attr( (string) $article->ID ); ?>">
													<?php wp_nonce_field( 'cywater_forum_workspace_trash_' . $article->ID, 'cywater_forum_workspace_trash_nonce' ); ?>
													<button class="link forum-remove-trigger" type="submit" data-confirm="<?php echo esc_attr( 'publish' === $article->post_status ? __( 'Remove this published article from the Forum?', 'cywater' ) : __( 'Delete this draft?', 'cywater' ) ); ?>"><?php echo 'publish' === $article->post_status ? esc_html__( 'Remove article', 'cywater' ) : esc_html__( 'Delete draft', 'cywater' ); ?></button>
												</form>
											</div>
											<?php if ( 'publish' === $status ) : ?><a class="link" href="<?php echo esc_url( get_permalink( $article ) ); ?>"><?php esc_html_e( 'Read article', 'cywater' ); ?></a><?php endif; ?>
											</article>
										<?php endforeach; ?>
									</section>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
