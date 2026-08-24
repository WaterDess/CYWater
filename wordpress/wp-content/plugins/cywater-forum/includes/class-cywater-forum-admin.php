<?php
/**
 * Administrator view of Forum participation.
 *
 * This is a projection, in the same spirit as the CYWater member record: it
 * reads endorsement, role, and article state in place and stores no second copy
 * of any of it. The one thing it writes is the administrator override, which is
 * itself recorded as an auditable endorsement record.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Admin {
	public static function register() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_section' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_section' ) );
		// profile.php fires personal_options_update, not edit_user_profile_update.
		// Without this the checkbox silently reverts on one's own profile.
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_section' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );
		add_filter( 'user_row_actions', array( __CLASS__, 'author_row_action' ), 20, 2 );
	}

	public static function render_user_section( $user ) {
		if ( ! current_user_can( 'edit_others_cyw_forum_posts' ) || ! $user instanceof WP_User ) {
			return;
		}

		$endorsements        = CYWater_Forum_Endorsement::endorsements( $user->ID );
		$submission_blockers = CYWater_Forum_Roles::submission_blockers( $user->ID );
		$endorsements_enabled = CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' );
		$override            = $endorsements_enabled && CYWater_Forum_Settings::is_enabled( 'admin_override' );
		$granted             = CYWater_Forum_Endorsement::is_admin_authorized( $user->ID );
		?>
		<h2 id="cywater-forum-authorship"><?php esc_html_e( 'CYWater Forum participation', 'cywater-forum' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Personal front-end publishing', 'cywater-forum' ); ?></th>
				<td>
					<?php if ( array() === $submission_blockers ) : ?>
						<strong><?php esc_html_e( 'Yes', 'cywater-forum' ); ?></strong>
						<p class="description"><?php esc_html_e( 'The account meets the ordinary member requirements and may create, publish, or update its own Forum posts on the public site. Staff management access, if assigned, is separate.', 'cywater-forum' ); ?></p>
					<?php else : ?>
						<strong><?php esc_html_e( 'No', 'cywater-forum' ); ?></strong>
						<p class="description"><?php echo esc_html( implode( ', ', array_map( array( __CLASS__, 'blocker_label' ), $submission_blockers ) ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Published articles', 'cywater-forum' ); ?></th>
				<td><?php echo esc_html( (string) CYWater_Forum_Content::published_count( $user->ID ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Historical endorsements', 'cywater-forum' ); ?></th>
				<td>
					<?php if ( ! $endorsements_enabled ) : ?>
						<p class="description"><?php esc_html_e( 'The invitation and endorsement workflow is paused. Existing records remain read-only and no new request or grant is processed.', 'cywater-forum' ); ?></p>
					<?php endif; ?>
					<?php if ( ! $endorsements ) : ?>
						<?php esc_html_e( 'None recorded.', 'cywater-forum' ); ?>
					<?php else : ?>
						<ul>
							<?php foreach ( $endorsements as $record ) : ?>
								<?php
								$endorser = get_user_by( 'id', absint( $record['endorser'] ?? 0 ) );
								$name     = $endorser ? $endorser->display_name : __( 'Removed account', 'cywater-forum' );
								?>
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: endorser name, 2: date, 3: how it was granted. */
											__( '%1$s on %2$s (%3$s)', 'cywater-forum' ),
											$name,
											wp_date( get_option( 'date_format' ), absint( $record['at'] ?? 0 ) ),
											'admin' === ( $record['via'] ?? '' ) ? __( 'administrator', 'cywater-forum' ) : __( 'endorsement link', 'cywater-forum' )
										)
									);
									?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $endorsements_enabled ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Legacy administrator override', 'cywater-forum' ); ?></th>
					<td>
					<?php if ( $override ) : ?>
						<?php wp_nonce_field( 'cywater_forum_authorship', 'cywater_forum_authorship_nonce' ); ?>
						<label>
							<input type="checkbox" name="cywater_forum_admin_grant" value="1" <?php checked( $granted ); ?> />
							<?php esc_html_e( 'Authorise this account to satisfy the legacy endorsement prerequisite.', 'cywater-forum' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'This satisfies only the legacy endorsement prerequisite; membership and email-verification requirements still apply.', 'cywater-forum' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Legacy override is disabled in the Forum settings.', 'cywater-forum' ); ?></p>
					<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public static function save_user_section( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! current_user_can( 'edit_others_cyw_forum_posts' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' ) || ! CYWater_Forum_Settings::is_enabled( 'admin_override' ) ) {
			return;
		}
		if ( ! isset( $_POST['cywater_forum_authorship_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_forum_authorship_nonce'] ) ), 'cywater_forum_authorship' ) ) {
			return;
		}

		$requested = ! empty( $_POST['cywater_forum_admin_grant'] );
		$current   = CYWater_Forum_Endorsement::is_admin_authorized( $user_id );
		if ( $requested === $current ) {
			return;
		}

		if ( $requested ) {
			CYWater_Forum_Endorsement::admin_grant( $user_id, get_current_user_id() );
		} else {
			CYWater_Forum_Endorsement::admin_revoke( $user_id );
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function add_users_column( $columns ) {
		if ( current_user_can( 'edit_others_cyw_forum_posts' ) ) {
			$columns['cyw_forum'] = __( 'Forum', 'cywater-forum' );
		}
		return $columns;
	}

	public static function render_users_column( $output, $column, $user_id ) {
		if ( 'cyw_forum' !== $column ) {
			return $output;
		}
		$count = CYWater_Forum_Content::published_count( $user_id );
		if ( CYWater_Forum_Roles::is_staff( $user_id ) ) {
			return CYWater_Forum_Roles::can_submit( $user_id )
				? esc_html__( 'Forum staff · eligible member', 'cywater-forum' )
				: esc_html__( 'Forum staff · personal posting unavailable', 'cywater-forum' );
		}
		if ( CYWater_Forum_Roles::can_submit( $user_id ) ) {
			/* translators: %d: published article count. */
			return esc_html( sprintf( _n( 'Eligible to publish, %d article', 'Eligible to publish, %d articles', $count, 'cywater-forum' ), $count ) );
		}
		$account = get_userdata( $user_id );
		if ( ( $account instanceof WP_User && in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $account->roles, true ) ) || $count > 0 ) {
			return esc_html__( 'Not currently eligible', 'cywater-forum' );
		}
		return esc_html__( '—', 'cywater-forum' );
	}

	/** Replace WordPress' login-derived author archive action with the Forum page. */
	public static function author_row_action( $actions, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $actions;
		}
		unset( $actions['view'] );
		if ( class_exists( 'CYWater_Forum_Community' ) && CYWater_Forum_Content::published_count( $user->ID ) > 0 ) {
			$actions['cywater_forum_author'] = '<a href="' . esc_url( CYWater_Forum_Community::author_url( $user->ID ) ) . '">' . esc_html__( 'View Forum author page', 'cywater-forum' ) . '</a>';
		}
		return $actions;
	}

	public static function blocker_label( $blocker ) {
		$labels = array(
			'signed_out'          => __( 'not signed in', 'cywater-forum' ),
			'not_endorsed'        => __( 'no endorsement', 'cywater-forum' ),
			'membership_inactive' => __( 'membership inactive', 'cywater-forum' ),
			'email_unverified'    => __( 'email unverified', 'cywater-forum' ),
		);
		return $labels[ $blocker ] ?? $blocker;
	}
}
