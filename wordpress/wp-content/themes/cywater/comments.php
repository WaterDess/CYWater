<?php
/**
 * Questions and replies on a forum article.
 *
 * Rendered only where cywater-forum has opened discussion. Whether a given
 * visitor may reply is that plugin's decision; this template asks and renders
 * the answer.
 *
 * @package CYWater
 */

if ( post_password_required() ) {
	return;
}

$may_reply = is_user_logged_in()
	&& class_exists( 'CYWater_Forum_Comments' )
	&& CYWater_Forum_Comments::may_comment( get_current_user_id() );

// Say what actually happens, so nobody wonders where their reply went.
$hold_first     = class_exists( 'CYWater_Forum_Settings' ) && CYWater_Forum_Settings::is_enabled( 'comments_hold_first' );
$moderation_note = $hold_first
	? __( 'Your first reply is checked by a moderator. Once it has been approved, your later replies appear immediately.', 'cywater' )
	: __( 'Replies are reviewed by a moderator before they appear.', 'cywater' );
?>
<div id="comments" class="forum-comments">
	<div class="section-head" data-reveal>
		<span class="eyebrow"><?php esc_html_e( 'Discussion', 'cywater' ); ?></span>
		<h2>
			<?php
			$count = (int) get_comments_number();
			echo esc_html(
				$count
					/* translators: %d: reply count. */
					? sprintf( _n( '%d reply.', '%d replies.', $count, 'cywater' ), $count )
					: __( 'Questions and replies.', 'cywater' )
			);
			?>
		</h2>
	</div>

	<?php if ( have_comments() ) : ?>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => __( 'Previous', 'cywater' ),
				'next_text' => __( 'Next', 'cywater' ),
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() ) : ?>
		<?php if ( get_comments_number() ) : ?>
			<p class="forum-comments-note"><?php esc_html_e( 'This discussion is closed.', 'cywater' ); ?></p>
		<?php endif; ?>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<div class="forum-comments-gate">
			<p><?php esc_html_e( 'Sign in to ask a question or reply.', 'cywater' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( cywater_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Sign in', 'cywater' ); ?></a>
		</div>
	<?php elseif ( ! $may_reply ) : ?>
		<div class="forum-comments-gate">
			<p><?php esc_html_e( 'An active CYWater membership is required to take part in the discussion.', 'cywater' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'View membership', 'cywater' ); ?></a>
		</div>
	<?php else : ?>
		<?php
		comment_form(
			array(
				'title_reply'         => __( 'Ask a question or reply', 'cywater' ),
				'title_reply_to'      => __( 'Reply to %s', 'cywater' ),
				'cancel_reply_link'   => __( 'Cancel', 'cywater' ),
				'label_submit'        => __( 'Post reply', 'cywater' ),
				'class_submit'        => 'btn btn-primary',
				'comment_notes_before' => '',
				'comment_notes_after'  => '<p class="forum-comments-note">' . esc_html( $moderation_note ) . '</p>',
				'comment_field'       => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your reply', 'cywater' ) . '</label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>',
			)
		);
		?>
	<?php endif; ?>
</div>
