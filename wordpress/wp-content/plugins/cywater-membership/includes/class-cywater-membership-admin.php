<?php
/**
 * Administrator navigation for the existing WordPress and PMPro member record.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Admin {
	private const REQUIRED_PROFILE_FIELDS = array(
		'cyw_institution_name',
		'cyw_country',
		'cyw_institution_type',
		'cyw_professional_title',
		'cyw_career_stage',
	);

	public static function register() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_record' ), 50 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_record' ), 50 );
		add_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_action' ), 20, 2 );
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_user_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_user_column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( __CLASS__, 'render_user_filter' ) );
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users' ) );
		add_action( 'admin_post_cywater_revoke_user_sessions', array( __CLASS__, 'process_revoke_user_sessions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'session_notice' ) );
	}

	public static function render_user_record( $user ) {
		if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$completed_required = 0;
		foreach ( self::REQUIRED_PROFILE_FIELDS as $field_key ) {
			if ( self::has_profile_value( get_user_meta( $user->ID, $field_key, true ) ) ) {
				++$completed_required;
			}
		}

		$directory_enabled = (bool) get_user_meta( $user->ID, 'cyw_profile_public', true );
		$selected_fields   = (array) get_user_meta( $user->ID, 'cyw_public_fields', true );
		$field_labels      = CYWater_Membership_Privacy::public_field_labels();
		$selected_labels   = array();
		foreach ( $selected_fields as $field_key ) {
			if ( isset( $field_labels[ $field_key ] ) ) {
				$selected_labels[] = $field_labels[ $field_key ];
			}
		}

		$member_url = add_query_arg(
			array(
				'page' => 'pmpro-memberslist',
				's'    => $user->user_email,
			),
			admin_url( 'admin.php' )
		);
		$orders_url = add_query_arg(
			array(
				'page' => 'pmpro-orders',
				's'    => $user->user_email,
			),
			admin_url( 'admin.php' )
		);
		$events_url           = admin_url( 'edit.php?post_type=cyw_event' );
		$email_verified       = CYWater_Membership_Account_Security::is_verified( $user->ID );
		$closure_requested_at = CYWater_Membership_Account_Security::closure_requested_at( $user->ID );
		$closure_deadline     = CYWater_Membership_Account_Security::closure_deadline( $user->ID );
		$closure_status       = CYWater_Membership_Account_Security::closure_status( $user->ID );
		$last_login_at        = CYWater_Membership_Account_Security::last_login_at( $user->ID );
		$active_levels        = array();
		if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			foreach ( (array) pmpro_getMembershipLevelsForUser( $user->ID ) as $level ) {
				$label = (string) $level->name;
				if ( ! empty( $level->enddate ) ) {
					$label .= ' — ' . sprintf( __( 'expires %s', 'cywater-membership' ), wp_date( get_option( 'date_format' ), (int) $level->enddate ) );
				} else {
					$label .= ' — ' . __( 'no expiration', 'cywater-membership' );
				}
				$active_levels[] = $label;
			}
		}
		?>
		<h2 id="cywater-member-record"><?php esc_html_e( 'CYWater member record', 'cywater-membership' ); ?></h2>
		<p class="description"><?php esc_html_e( 'This view links the existing WordPress account, PMPro membership and order records, and CYWater privacy choices. It does not create a second member database.', 'cywater-membership' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Account', 'cywater-membership' ); ?></th>
				<td>
					<?php echo esc_html( sprintf( __( 'User ID %1$d · created %2$s', 'cywater-membership' ), $user->ID, get_date_from_gmt( $user->user_registered, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?>
					<br><?php echo esc_html( $last_login_at ? sprintf( __( 'Last sign-in: %s', 'cywater-membership' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_login_at ) ) : __( 'Last sign-in: not recorded yet', 'cywater-membership' ) ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email ownership', 'cywater-membership' ); ?></th>
				<td><strong><?php echo esc_html( $email_verified ? __( 'Verified', 'cywater-membership' ) : __( 'Verification required', 'cywater-membership' ) ); ?></strong></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Required profile', 'cywater-membership' ); ?></th>
				<td><?php echo esc_html( sprintf( '%d of %d fields completed', $completed_required, count( self::REQUIRED_PROFILE_FIELDS ) ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Public directory', 'cywater-membership' ); ?></th>
				<td>
					<strong><?php echo esc_html( $directory_enabled ? __( 'Opted in', 'cywater-membership' ) : __( 'Private', 'cywater-membership' ) ); ?></strong>
					<?php if ( $directory_enabled ) : ?>
						<br><?php echo esc_html( $selected_labels ? implode( ', ', $selected_labels ) : __( 'No fields selected', 'cywater-membership' ) ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Active membership', 'cywater-membership' ); ?></th>
				<td><?php echo esc_html( $active_levels ? implode( '; ', $active_levels ) : __( 'No active membership', 'cywater-membership' ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Membership and orders', 'cywater-membership' ); ?></th>
				<td>
					<?php if ( current_user_can( 'pmpro_memberslist' ) ) : ?>
						<a href="<?php echo esc_url( $member_url ); ?>"><?php esc_html_e( 'View in PMPro Members', 'cywater-membership' ); ?></a>
					<?php endif; ?>
					<?php if ( current_user_can( 'pmpro_orders' ) ) : ?>
						<?php if ( current_user_can( 'pmpro_memberslist' ) ) : ?> | <?php endif; ?>
						<a href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View PMPro orders and refunds', 'cywater-membership' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Event registrations', 'cywater-membership' ); ?></th>
				<td><a href="<?php echo esc_url( $events_url ); ?>"><?php esc_html_e( 'Open Events, then choose the event attendee report', 'cywater-membership' ); ?></a></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Account closure', 'cywater-membership' ); ?></th>
				<td>
					<strong><?php echo esc_html( $closure_requested_at ? sprintf( __( 'Requested %1$s — %2$s', 'cywater-membership' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $closure_requested_at ), 'review_due' === $closure_status ? __( 'administrator review due', 'cywater-membership' ) : __( 'seven-day cooling-off period', 'cywater-membership' ) ) : __( 'No request', 'cywater-membership' ) ); ?></strong>
					<?php if ( $closure_deadline ) : ?><br><?php echo esc_html( sprintf( __( 'Review date: %s. Do not erase order, refund, or event records without applying the retention policy.', 'cywater-membership' ), wp_date( get_option( 'date_format' ), $closure_deadline ) ) ); ?><?php endif; ?>
				</td>
			</tr>
			<?php if ( get_current_user_id() !== $user->ID && current_user_can( 'edit_user', $user->ID ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Signed-in devices', 'cywater-membership' ); ?></th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cywater_revoke_user_sessions" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
						<?php wp_nonce_field( 'cywater_revoke_user_sessions_' . $user->ID ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Sign out this user on all devices', 'cywater-membership' ); ?></button>
					</form>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public static function add_user_row_action( $actions, $user ) {
		if ( $user instanceof WP_User && current_user_can( 'edit_user', $user->ID ) ) {
			$url = add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) . '#cywater-member-record';
			$actions['cywater_member_record'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'CYWater record', 'cywater-membership' )
			);
		}
		return $actions;
	}

	public static function add_user_columns( $columns ) {
		$columns['cywater_account']    = __( 'CYWater account', 'cywater-membership' );
		$columns['cywater_membership'] = __( 'Membership', 'cywater-membership' );
		return $columns;
	}

	public static function render_user_column( $output, $column_name, $user_id ) {
		if ( 'cywater_account' === $column_name ) {
			$parts   = array( CYWater_Membership_Account_Security::is_verified( $user_id ) ? __( 'Email verified', 'cywater-membership' ) : __( 'Verification required', 'cywater-membership' ) );
			$status  = CYWater_Membership_Account_Security::closure_status( $user_id );
			if ( 'cooling_off' === $status ) {
				$parts[] = __( 'Closure: cooling off', 'cywater-membership' );
			} elseif ( 'review_due' === $status ) {
				$parts[] = __( 'Closure: review due', 'cywater-membership' );
			}
			$last_login = CYWater_Membership_Account_Security::last_login_at( $user_id );
			if ( $last_login ) {
				$parts[] = sprintf( __( 'Last sign-in: %s', 'cywater-membership' ), wp_date( get_option( 'date_format' ), $last_login ) );
			}
			return esc_html( implode( ' · ', $parts ) );
		}
		if ( 'cywater_membership' === $column_name ) {
			$levels = array();
			if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
				foreach ( (array) pmpro_getMembershipLevelsForUser( $user_id ) as $level ) {
					$levels[] = ! empty( $level->enddate ) ? sprintf( '%1$s — %2$s', $level->name, wp_date( get_option( 'date_format' ), (int) $level->enddate ) ) : (string) $level->name;
				}
			}
			return esc_html( $levels ? implode( '; ', $levels ) : __( 'None', 'cywater-membership' ) );
		}
		return $output;
	}

	public static function render_user_filter( $which ) {
		if ( 'top' !== $which || ! current_user_can( 'list_users' ) ) {
			return;
		}
		$selected = isset( $_GET['cywater_account_state'] ) ? sanitize_key( wp_unslash( $_GET['cywater_account_state'] ) ) : '';
		?>
		<label class="screen-reader-text" for="cywater-account-state"><?php esc_html_e( 'Filter by CYWater account state', 'cywater-membership' ); ?></label>
		<select name="cywater_account_state" id="cywater-account-state">
			<option value=""><?php esc_html_e( 'All CYWater account states', 'cywater-membership' ); ?></option>
			<option value="verification_required" <?php selected( $selected, 'verification_required' ); ?>><?php esc_html_e( 'Verification required', 'cywater-membership' ); ?></option>
			<option value="closure_cooling" <?php selected( $selected, 'closure_cooling' ); ?>><?php esc_html_e( 'Closure: cooling off', 'cywater-membership' ); ?></option>
			<option value="closure_due" <?php selected( $selected, 'closure_due' ); ?>><?php esc_html_e( 'Closure: review due', 'cywater-membership' ); ?></option>
		</select>
		<?php
	}

	public static function filter_users( $query ) {
		if ( ! is_admin() || ! current_user_can( 'list_users' ) ) {
			return;
		}
		$state = isset( $_GET['cywater_account_state'] ) ? sanitize_key( wp_unslash( $_GET['cywater_account_state'] ) ) : '';
		if ( ! $state ) {
			return;
		}
		$meta_query = (array) $query->get( 'meta_query' );
		if ( 'verification_required' === $state ) {
			$meta_query[] = array( 'key' => 'cyw_verified_email', 'compare' => 'NOT EXISTS' );
		} elseif ( 'closure_cooling' === $state ) {
			$meta_query[] = array( 'key' => 'cyw_account_closure_requested_at', 'value' => time() - ( 7 * DAY_IN_SECONDS ), 'compare' => '>', 'type' => 'NUMERIC' );
		} elseif ( 'closure_due' === $state ) {
			$meta_query[] = array( 'key' => 'cyw_account_closure_requested_at', 'value' => time() - ( 7 * DAY_IN_SECONDS ), 'compare' => '<=', 'type' => 'NUMERIC' );
		} else {
			return;
		}
		$query->set( 'meta_query', $meta_query );
	}

	public static function process_revoke_user_sessions() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id || get_current_user_id() === $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You cannot revoke sessions for this account.', 'cywater-membership' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cywater_revoke_user_sessions_' . $user_id );
		CYWater_Membership_Account_Security::revoke_all_sessions( $user_id );
		wp_safe_redirect( add_query_arg( array( 'user_id' => $user_id, 'cywater_sessions' => 'revoked' ), admin_url( 'user-edit.php' ) ) . '#cywater-member-record' );
		exit;
	}

	public static function session_notice() {
		if ( isset( $_GET['cywater_sessions'] ) && 'revoked' === sanitize_key( wp_unslash( $_GET['cywater_sessions'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The user has been signed out on all devices.', 'cywater-membership' ) . '</p></div>';
		}
	}

	private static function has_profile_value( $value ) {
		if ( is_array( $value ) ) {
			return (bool) array_filter( $value );
		}
		return '' !== trim( (string) $value );
	}
}
