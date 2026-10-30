<?php
/**
 * Admin: booking staff accounts. Each person has their own login with the "HAKKA Booking Staff"
 * role and only sees the locations assigned to them.
 *
 * Managers can only create and change accounts with that role, never other users.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Admin_Staff {

	public static function init() {
		add_action( 'admin_post_hkr_add_staff', array( __CLASS__, 'add' ) );
		add_action( 'admin_post_hkr_update_staff', array( __CLASS__, 'update' ) );
	}

	private static function staff_users() {
		$active   = get_users( array( 'role' => HKR_Roles::STAFF, 'orderby' => 'display_name' ) );
		$disabled = get_users( array( 'meta_key' => 'hkr_disabled', 'meta_value' => '1', 'orderby' => 'display_name' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return array_merge( $active, $disabled );
	}

	private static function is_managed( $user ) {
		return $user && ( in_array( HKR_Roles::STAFF, (array) $user->roles, true ) || '1' === get_user_meta( $user->ID, 'hkr_disabled', true ) ) && ! user_can( $user, 'manage_options' );
	}

	public static function page() {
		$locations = HKR_Locations::all();
		$users     = self::staff_users();
		$staff_page = (int) HKR_Settings::get( 'staff_page_id' );
		?>
		<div class="wrap hkr-wrap">
			<h1><?php esc_html_e( 'Booking staff', 'hakka-reservations' ); ?></h1>
			<p><?php esc_html_e( 'Each staff member gets their own login. They can only see and confirm bookings for the locations ticked for them, and cannot change anything else on the website.', 'hakka-reservations' ); ?>
			<?php if ( $staff_page ) : ?>
				<?php echo esc_html( sprintf( /* translators: %s: URL */ __( 'Staff log in at %s', 'hakka-reservations' ), get_permalink( $staff_page ) ) ); ?>
			<?php endif; ?></p>

			<div class="hkr-columns">
				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Add a staff member', 'hakka-reservations' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="hkr_add_staff">
						<?php wp_nonce_field( 'hkr_add_staff' ); ?>
						<table class="form-table" role="presentation">
							<tr><th><label for="hkr-st-first"><?php esc_html_e( 'First name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-st-first" name="first_name" class="regular-text" required></td></tr>
							<tr><th><label for="hkr-st-last"><?php esc_html_e( 'Last name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-st-last" name="last_name" class="regular-text"></td></tr>
							<tr><th><label for="hkr-st-email"><?php esc_html_e( 'Email', 'hakka-reservations' ); ?></label></th><td><input type="email" id="hkr-st-email" name="email" class="regular-text" required><p class="description"><?php esc_html_e( 'They will get an email to set their own password.', 'hakka-reservations' ); ?></p></td></tr>
							<tr><th><?php esc_html_e( 'Locations', 'hakka-reservations' ); ?></th><td>
								<?php foreach ( $locations as $loc ) : ?>
									<label style="display:block"><input type="checkbox" name="locations[]" value="<?php echo esc_attr( $loc['id'] ); ?>"> <?php echo esc_html( $loc['name'] ); ?></label>
								<?php endforeach; ?>
							</td></tr>
						</table>
						<?php submit_button( __( 'Add staff member', 'hakka-reservations' ) ); ?>
					</form>
				</section>
				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Managers', 'hakka-reservations' ); ?></h2>
					<p><?php esc_html_e( 'To let someone change locations, hours, capacity and settings without being a full administrator, an administrator can give their user account the "Reservations Manager" role in Users.', 'hakka-reservations' ); ?></p>
				</section>
			</div>

			<section class="hkr-panel">
				<h2><?php esc_html_e( 'Staff accounts', 'hakka-reservations' ); ?></h2>
				<?php if ( ! $users ) : ?>
					<p><?php esc_html_e( 'No staff accounts yet.', 'hakka-reservations' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Name', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Email', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Locations', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Access', 'hakka-reservations' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $users as $u ) : ?>
							<?php
							$assigned = array_map( 'intval', (array) get_user_meta( $u->ID, 'hkr_locations', true ) );
							$disabled = '1' === get_user_meta( $u->ID, 'hkr_disabled', true );
							$form_id  = 'hkr-staff-' . $u->ID;
							?>
							<tr>
								<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
								<td><?php echo esc_html( $u->user_email ); ?></td>
								<td>
									<?php foreach ( $locations as $loc ) : ?>
										<label style="display:block"><input form="<?php echo esc_attr( $form_id ); ?>" type="checkbox" name="locations[]" value="<?php echo esc_attr( $loc['id'] ); ?>" <?php checked( in_array( $loc['id'], $assigned, true ) ); ?>> <?php echo esc_html( $loc['name'] ); ?></label>
									<?php endforeach; ?>
								</td>
								<td><?php echo $disabled ? '<strong>' . esc_html__( 'Switched off', 'hakka-reservations' ) . '</strong>' : esc_html__( 'Active', 'hakka-reservations' ); ?></td>
								<td>
									<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="hkr_update_staff">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
										<?php wp_nonce_field( 'hkr_update_staff_' . $u->ID ); ?>
										<button class="button" name="do" value="save"><?php esc_html_e( 'Save locations', 'hakka-reservations' ); ?></button>
										<button class="button" name="do" value="reset"><?php esc_html_e( 'Send password reset', 'hakka-reservations' ); ?></button>
										<?php if ( $disabled ) : ?>
											<button class="button" name="do" value="enable"><?php esc_html_e( 'Switch on', 'hakka-reservations' ); ?></button>
										<?php else : ?>
											<button class="button" name="do" value="disable" onclick="return confirm('<?php echo esc_js( __( 'Switch off this person\'s access? They will be logged out.', 'hakka-reservations' ) ); ?>');"><?php esc_html_e( 'Switch off', 'hakka-reservations' ); ?></button>
										<?php endif; ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	private static function posted_locations() {
		$ids   = isset( $_POST['locations'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['locations'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$valid = wp_list_pluck( HKR_Locations::all(), 'id' );
		return array_values( array_intersect( $ids, $valid ) );
	}

	public static function add() {
		HKR_Admin::check( 'hkr_manage', 'hkr_add_staff' );
		$back  = admin_url( 'admin.php?page=hkr-staff' );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		if ( ! is_email( $email ) || '' === $first ) {
			HKR_Admin::redirect( $back, __( 'Please enter a first name and a valid email.', 'hakka-reservations' ), 'error' );
		}
		if ( email_exists( $email ) ) {
			HKR_Admin::redirect( $back, __( 'A user with that email already exists. An administrator can give that account the "HAKKA Booking Staff" role in Users.', 'hakka-reservations' ), 'error' );
		}
		// Login name is not shown anywhere public; base it on the name, not the email.
		$base  = sanitize_user( strtolower( $first . ( $last ? '.' . $last : '' ) ), true );
		$base  = $base ? $base : 'staff';
		$login = $base;
		$n     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $n++;
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'nickname'     => $first,
				'role'         => HKR_Roles::STAFF,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			HKR_Admin::redirect( $back, $user_id->get_error_message(), 'error' );
		}
		update_user_meta( $user_id, 'hkr_locations', self::posted_locations() );
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
		wp_new_user_notification( $user_id, null, 'user' );
		hkr_log( 0, 'staff:added', '', trim( $first . ' ' . $last ) . ' <' . $email . '>' );
		/* translators: 1: name, 2: email */
		HKR_Admin::redirect( $back, sprintf( __( '%1$s was added. We emailed %2$s a link to set their password.', 'hakka-reservations' ), $first, $email ) );
	}

	public static function update() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		HKR_Admin::check( 'hkr_manage', 'hkr_update_staff_' . $user_id );
		$back = admin_url( 'admin.php?page=hkr-staff' );
		$user = get_userdata( $user_id );
		if ( ! self::is_managed( $user ) ) {
			HKR_Admin::redirect( $back, __( 'That account is not a booking staff account.', 'hakka-reservations' ), 'error' );
		}
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'save';
		switch ( $do ) {
			case 'reset':
				$result = retrieve_password( $user->user_login );
				$msg    = is_wp_error( $result ) ? $result->get_error_message() : sprintf( /* translators: %s: email */ __( 'Password reset email sent to %s.', 'hakka-reservations' ), $user->user_email );
				HKR_Admin::redirect( $back, $msg, is_wp_error( $result ) ? 'error' : 'success' );
				break;
			case 'disable':
				$user->set_role( '' );
				update_user_meta( $user_id, 'hkr_disabled', '1' );
				WP_Session_Tokens::get_instance( $user_id )->destroy_all();
				hkr_log( 0, 'staff:disabled', '', $user->display_name );
				HKR_Admin::redirect( $back, sprintf( /* translators: %s: name */ __( '%s can no longer log in to bookings.', 'hakka-reservations' ), $user->display_name ) );
				break;
			case 'enable':
				$user->set_role( HKR_Roles::STAFF );
				delete_user_meta( $user_id, 'hkr_disabled' );
				hkr_log( 0, 'staff:enabled', '', $user->display_name );
				HKR_Admin::redirect( $back, sprintf( /* translators: %s: name */ __( '%s can log in again.', 'hakka-reservations' ), $user->display_name ) );
				break;
			default:
				update_user_meta( $user_id, 'hkr_locations', self::posted_locations() );
				hkr_log( 0, 'staff:locations', '', $user->display_name );
				HKR_Admin::redirect( $back, __( 'Locations saved.', 'hakka-reservations' ) );
		}
	}
}
