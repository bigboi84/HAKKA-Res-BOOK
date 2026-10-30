<?php
/**
 * Admin: global settings, and the Messages & Email screen (templates, preview, test send).
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Admin_Settings {

	public static function init() {
		add_action( 'admin_post_hkr_save_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_hkr_save_templates', array( __CLASS__, 'save_templates' ) );
		add_action( 'admin_post_hkr_preview_email', array( __CLASS__, 'preview' ) );
		add_action( 'admin_post_hkr_test_email', array( __CLASS__, 'test' ) );
	}

	public static function page() {
		$s = HKR_Settings::all();
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		?>
		<div class="wrap hkr-wrap">
			<h1><?php esc_html_e( 'Reservation settings', 'hakka-reservations' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hkr_save_settings">
				<?php wp_nonce_field( 'hkr_save_settings' ); ?>

				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Email sender', 'hakka-reservations' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Guest emails for every location are sent from this address unless a location sets its own. Staff alerts go to each location\'s own alert email (Reservations → Locations).', 'hakka-reservations' ); ?></p>
					<table class="form-table" role="presentation">
						<tr><th><label for="hkr-business"><?php esc_html_e( 'Business name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-business" name="s[business_name]" class="regular-text" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td></tr>
						<tr><th><label for="hkr-fromname"><?php esc_html_e( '"From" name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-fromname" name="s[from_name]" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td></tr>
						<tr><th><label for="hkr-from"><?php esc_html_e( '"From" address', 'hakka-reservations' ); ?></label></th><td><input type="email" id="hkr-from" name="s[from_email]" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>" required>
							<p class="description"><?php esc_html_e( 'This mailbox must be set up in Post SMTP (with SPF/DKIM for the domain) so emails are delivered and not marked as spam.', 'hakka-reservations' ); ?></p></td></tr>
						<tr><th><label for="hkr-reply"><?php esc_html_e( 'Reply-to address', 'hakka-reservations' ); ?></label></th><td><input type="email" id="hkr-reply" name="s[reply_to]" class="regular-text" value="<?php echo esc_attr( $s['reply_to'] ); ?>"></td></tr>
					</table>
				</section>

				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Look and feel', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Logo for emails', 'hakka-reservations' ); ?></th><td><?php HKR_Admin::image_field( 's[logo_id]', $s['logo_id'], __( 'A white or light logo works best on the dark email header.', 'hakka-reservations' ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Default booking form image', 'hakka-reservations' ); ?></th><td><?php HKR_Admin::image_field( 's[default_header_id]', $s['default_header_id'], __( 'Used when a location has no header image of its own.', 'hakka-reservations' ) ); ?></td></tr>
						<tr><th><label for="hkr-primary"><?php esc_html_e( 'Brand colour', 'hakka-reservations' ); ?></label></th><td><input type="color" id="hkr-primary" name="s[color_primary]" value="<?php echo esc_attr( $s['color_primary'] ); ?>"></td></tr>
						<tr><th><label for="hkr-dark"><?php esc_html_e( 'Dark colour', 'hakka-reservations' ); ?></label></th><td><input type="color" id="hkr-dark" name="s[color_dark]" value="<?php echo esc_attr( $s['color_dark'] ); ?>"></td></tr>
					</table>
				</section>

				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Booking form', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label for="hkr-celebrations"><?php esc_html_e( 'Celebration choices', 'hakka-reservations' ); ?></label></th><td>
							<textarea id="hkr-celebrations" name="s[celebration_options]" rows="4" class="regular-text"><?php echo esc_textarea( $s['celebration_options'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Guests also see "None" and "Other" (where they can type what they are celebrating).', 'hakka-reservations' ); ?></p>
						</td></tr>
						<?php
						$field_rows = array(
							'field_high_chair' => __( 'High chair', 'hakka-reservations' ),
							'field_dietary'    => __( 'Dietary needs or allergies', 'hakka-reservations' ),
							'field_requests'   => __( 'Special requests', 'hakka-reservations' ),
							'field_seating'    => __( 'Seating preference', 'hakka-reservations' ),
						);
						foreach ( $field_rows as $key => $label ) :
							?>
							<tr><th><label for="hkr-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
								<select id="hkr-<?php echo esc_attr( $key ); ?>" name="s[<?php echo esc_attr( $key ); ?>]">
									<option value="off" <?php selected( $s[ $key ], 'off' ); ?>><?php esc_html_e( 'Hidden', 'hakka-reservations' ); ?></option>
									<option value="optional" <?php selected( $s[ $key ], 'optional' ); ?>><?php esc_html_e( 'Optional', 'hakka-reservations' ); ?></option>
									<?php if ( 'field_high_chair' !== $key ) : ?>
										<option value="required" <?php selected( $s[ $key ], 'required' ); ?>><?php esc_html_e( 'Required', 'hakka-reservations' ); ?></option>
									<?php endif; ?>
								</select>
							</td></tr>
						<?php endforeach; ?>
						<tr><th><label for="hkr-seating-options"><?php esc_html_e( 'Seating choices', 'hakka-reservations' ); ?></label></th><td><textarea id="hkr-seating-options" name="s[seating_options]" rows="3" class="regular-text"><?php echo esc_textarea( $s['seating_options'] ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Spots left', 'hakka-reservations' ); ?></th><td><label><input type="checkbox" name="s[show_spots_left]" value="1" <?php checked( $s['show_spots_left'], 1 ); ?>> <?php esc_html_e( 'Show "x spots left" on nearly full time slots', 'hakka-reservations' ); ?></label></td></tr>
						<tr><th><label for="hkr-terms"><?php esc_html_e( 'Booking terms', 'hakka-reservations' ); ?></label></th><td><textarea id="hkr-terms" name="s[terms_text]" rows="3" class="large-text"><?php echo esc_textarea( $s['terms_text'] ); ?></textarea></td></tr>
						<tr><th><label for="hkr-terms-page"><?php esc_html_e( 'Terms page', 'hakka-reservations' ); ?></label></th><td>
							<select id="hkr-terms-page" name="s[terms_page_id]">
								<option value="0"><?php esc_html_e( 'None', 'hakka-reservations' ); ?></option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( (int) $s['terms_page_id'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td></tr>
						<tr><th><label for="hkr-cc"><?php esc_html_e( 'Default country code', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-cc" name="s[country_code]" class="small-text" value="<?php echo esc_attr( $s['country_code'] ); ?>"><p class="description"><?php esc_html_e( 'Added to 7-digit phone numbers, e.g. 1868 for Trinidad and Tobago.', 'hakka-reservations' ); ?></p></td></tr>
					</table>
				</section>

				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Staff screen and security', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						$page_selects = array(
							'staff_page_id'  => __( 'Staff bookings page', 'hakka-reservations' ),
							'manage_page_id' => __( 'Guest "manage booking" page', 'hakka-reservations' ),
							'test_page_id'   => __( 'Test booking page', 'hakka-reservations' ),
						);
						foreach ( $page_selects as $key => $label ) :
							?>
							<tr><th><label for="hkr-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
								<select id="hkr-<?php echo esc_attr( $key ); ?>" name="s[<?php echo esc_attr( $key ); ?>]">
									<option value="0"><?php esc_html_e( 'None', 'hakka-reservations' ); ?></option>
									<?php foreach ( $pages as $p ) : ?>
										<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( (int) $s[ $key ], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( (int) $s[ $key ] ) : ?><a href="<?php echo esc_url( get_permalink( (int) $s[ $key ] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'hakka-reservations' ); ?></a><?php endif; ?>
							</td></tr>
						<?php endforeach; ?>
						<tr><th><label for="hkr-session"><?php esc_html_e( 'Staff stay logged in for (hours)', 'hakka-reservations' ); ?></label></th><td><input type="number" min="1" id="hkr-session" name="s[staff_session_hours]" class="small-text" value="<?php echo esc_attr( $s['staff_session_hours'] ); ?>"></td></tr>
						<tr><th><label for="hkr-poll"><?php esc_html_e( 'Staff screen checks for new bookings every (seconds)', 'hakka-reservations' ); ?></label></th><td><input type="number" min="10" id="hkr-poll" name="s[staff_poll_seconds]" class="small-text" value="<?php echo esc_attr( $s['staff_poll_seconds'] ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Hide usernames', 'hakka-reservations' ); ?></th><td><label><input type="checkbox" name="s[hide_usernames]" value="1" <?php checked( $s['hide_usernames'], 1 ); ?>> <?php esc_html_e( 'Turn off author pages and the public user list, so login names are not shown on the site', 'hakka-reservations' ); ?></label></td></tr>
						<tr><th><label for="hkr-rate"><?php esc_html_e( 'Online bookings per visitor per hour', 'hakka-reservations' ); ?></label></th><td><input type="number" min="0" id="hkr-rate" name="s[rate_limit]" class="small-text" value="<?php echo esc_attr( $s['rate_limit'] ); ?>"><p class="description"><?php esc_html_e( 'Stops spam. 0 = no limit.', 'hakka-reservations' ); ?></p></td></tr>
						<tr><th><label for="hkr-ts-site"><?php esc_html_e( 'Cloudflare Turnstile (optional)', 'hakka-reservations' ); ?></label></th><td>
							<input type="text" id="hkr-ts-site" name="s[turnstile_site]" class="regular-text" value="<?php echo esc_attr( $s['turnstile_site'] ); ?>" placeholder="<?php esc_attr_e( 'Site key', 'hakka-reservations' ); ?>"><br>
							<input type="password" name="s[turnstile_secret]" class="regular-text" value="" placeholder="<?php echo $s['turnstile_secret'] ? esc_attr__( 'Secret key saved – type to replace', 'hakka-reservations' ) : esc_attr__( 'Secret key', 'hakka-reservations' ); ?>" autocomplete="new-password">
							<?php if ( $s['turnstile_secret'] ) : ?><br><label><input type="checkbox" name="s[turnstile_clear]" value="1"> <?php esc_html_e( 'Remove the saved secret key', 'hakka-reservations' ); ?></label><?php endif; ?>
						</td></tr>
					</table>
				</section>

				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Guest data', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label for="hkr-retention"><?php esc_html_e( 'Remove guest details after (months)', 'hakka-reservations' ); ?></label></th><td><input type="number" min="0" id="hkr-retention" name="s[retention_months]" class="small-text" value="<?php echo esc_attr( $s['retention_months'] ); ?>"><p class="description"><?php esc_html_e( 'Names, phone numbers and emails of past bookings are removed after this long. Booking counts stay for reports. 0 = keep.', 'hakka-reservations' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'When the plugin is deleted', 'hakka-reservations' ); ?></th><td><label><input type="checkbox" name="s[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Also delete all bookings, locations and settings', 'hakka-reservations' ); ?></label></td></tr>
					</table>
				</section>

				<?php submit_button( __( 'Save settings', 'hakka-reservations' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save() {
		HKR_Admin::check( 'hkr_manage', 'hkr_save_settings' );
		$in  = isset( $_POST['s'] ) ? (array) wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per key below.
		$out = array(
			'business_name'       => sanitize_text_field( isset( $in['business_name'] ) ? $in['business_name'] : '' ),
			'from_name'           => sanitize_text_field( isset( $in['from_name'] ) ? $in['from_name'] : '' ),
			'from_email'          => sanitize_email( isset( $in['from_email'] ) ? $in['from_email'] : '' ),
			'reply_to'            => sanitize_email( isset( $in['reply_to'] ) ? $in['reply_to'] : '' ),
			'logo_id'             => absint( isset( $in['logo_id'] ) ? $in['logo_id'] : 0 ),
			'default_header_id'   => absint( isset( $in['default_header_id'] ) ? $in['default_header_id'] : 0 ),
			'color_primary'       => sanitize_hex_color( isset( $in['color_primary'] ) ? $in['color_primary'] : '' ) ?: '#dd3333',
			'color_dark'          => sanitize_hex_color( isset( $in['color_dark'] ) ? $in['color_dark'] : '' ) ?: '#111111',
			'celebration_options' => implode( "\n", hkr_lines( isset( $in['celebration_options'] ) ? $in['celebration_options'] : '' ) ),
			'seating_options'     => implode( "\n", hkr_lines( isset( $in['seating_options'] ) ? $in['seating_options'] : '' ) ),
			'show_spots_left'     => empty( $in['show_spots_left'] ) ? 0 : 1,
			'terms_text'          => sanitize_textarea_field( isset( $in['terms_text'] ) ? $in['terms_text'] : '' ),
			'terms_page_id'       => absint( isset( $in['terms_page_id'] ) ? $in['terms_page_id'] : 0 ),
			'country_code'        => preg_replace( '/\D+/', '', isset( $in['country_code'] ) ? $in['country_code'] : '' ),
			'staff_page_id'       => absint( isset( $in['staff_page_id'] ) ? $in['staff_page_id'] : 0 ),
			'manage_page_id'      => absint( isset( $in['manage_page_id'] ) ? $in['manage_page_id'] : 0 ),
			'test_page_id'        => absint( isset( $in['test_page_id'] ) ? $in['test_page_id'] : 0 ),
			'staff_session_hours' => max( 1, (int) ( isset( $in['staff_session_hours'] ) ? $in['staff_session_hours'] : 12 ) ),
			'staff_poll_seconds'  => max( 10, (int) ( isset( $in['staff_poll_seconds'] ) ? $in['staff_poll_seconds'] : 30 ) ),
			'hide_usernames'      => empty( $in['hide_usernames'] ) ? 0 : 1,
			'rate_limit'          => max( 0, (int) ( isset( $in['rate_limit'] ) ? $in['rate_limit'] : 5 ) ),
			'turnstile_site'      => sanitize_text_field( isset( $in['turnstile_site'] ) ? $in['turnstile_site'] : '' ),
			'retention_months'    => max( 0, (int) ( isset( $in['retention_months'] ) ? $in['retention_months'] : 0 ) ),
			'delete_on_uninstall' => empty( $in['delete_on_uninstall'] ) ? 0 : 1,
		);
		foreach ( array( 'field_high_chair', 'field_dietary', 'field_requests', 'field_seating' ) as $key ) {
			$value       = isset( $in[ $key ] ) ? sanitize_key( $in[ $key ] ) : 'optional';
			$out[ $key ] = in_array( $value, array( 'off', 'optional', 'required' ), true ) ? $value : 'optional';
		}
		if ( ! empty( $in['turnstile_clear'] ) ) {
			$out['turnstile_secret'] = '';
		} elseif ( ! empty( $in['turnstile_secret'] ) ) {
			$out['turnstile_secret'] = sanitize_text_field( $in['turnstile_secret'] );
		}
		if ( ! is_email( $out['from_email'] ) ) {
			HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-settings' ), __( 'Please enter a valid "From" address.', 'hakka-reservations' ), 'error' );
		}
		HKR_Settings::update( $out );
		hkr_purge_page_cache();
		hkr_log( 0, 'settings:global', '', '' );
		HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-settings' ), __( 'Settings saved.', 'hakka-reservations' ) );
	}

	/* ---------------- Messages & Email ---------------- */

	public static function messages_page() {
		$templates = HKR_Settings::templates();
		$types     = HKR_Settings::template_types();
		$locations = HKR_Locations::all();
		$user      = wp_get_current_user();
		?>
		<div class="wrap hkr-wrap">
			<h1><?php esc_html_e( 'Messages & Email', 'hakka-reservations' ); ?></h1>
			<p><?php esc_html_e( 'Edit the wording of each email. The booking details card, buttons and location details are added automatically, so every email shows the party size, date, time, booking type and notes clearly.', 'hakka-reservations' ); ?></p>
			<p class="description"><?php esc_html_e( 'Placeholders:', 'hakka-reservations' ); ?> <code>{guest_name}</code> <code>{guest_first_name}</code> <code>{guest_phone}</code> <code>{party_size}</code> <code>{date}</code> <code>{date_short}</code> <code>{time}</code> <code>{end_time}</code> <code>{booking_type}</code> <code>{celebration}</code> <code>{location_name}</code> <code>{location_address}</code> <code>{location_phone}</code> <code>{booking_ref}</code> <code>{manage_link}</code> <code>{staff_message}</code> <code>{guest_message}</code> <code>{change_summary}</code></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hkr_save_templates">
				<?php wp_nonce_field( 'hkr_save_templates' ); ?>
				<?php foreach ( $types as $type => $info ) : ?>
					<?php $tpl = $templates[ $type ]; ?>
					<details class="hkr-panel hkr-template" <?php echo 'guest_received' === $type ? 'open' : ''; ?>>
						<summary><strong><?php echo esc_html( $info['label'] ); ?></strong></summary>
						<table class="form-table" role="presentation">
							<tr><th><label for="hkr-t-<?php echo esc_attr( $type ); ?>-subject"><?php esc_html_e( 'Subject', 'hakka-reservations' ); ?></label></th><td><input type="text" class="large-text" id="hkr-t-<?php echo esc_attr( $type ); ?>-subject" name="t[<?php echo esc_attr( $type ); ?>][subject]" value="<?php echo esc_attr( $tpl['subject'] ); ?>"></td></tr>
							<tr><th><label for="hkr-t-<?php echo esc_attr( $type ); ?>-heading"><?php esc_html_e( 'Heading', 'hakka-reservations' ); ?></label></th><td><input type="text" class="large-text" id="hkr-t-<?php echo esc_attr( $type ); ?>-heading" name="t[<?php echo esc_attr( $type ); ?>][heading]" value="<?php echo esc_attr( $tpl['heading'] ); ?>"></td></tr>
							<tr><th><label for="hkr-t-<?php echo esc_attr( $type ); ?>-banner"><?php esc_html_e( 'Banner line', 'hakka-reservations' ); ?></label></th><td><input type="text" class="large-text" id="hkr-t-<?php echo esc_attr( $type ); ?>-banner" name="t[<?php echo esc_attr( $type ); ?>][banner]" value="<?php echo esc_attr( $tpl['banner'] ); ?>"></td></tr>
							<tr><th><label for="hkr-t-<?php echo esc_attr( $type ); ?>-body"><?php esc_html_e( 'Message', 'hakka-reservations' ); ?></label></th><td><textarea class="large-text code" rows="5" id="hkr-t-<?php echo esc_attr( $type ); ?>-body" name="t[<?php echo esc_attr( $type ); ?>][body]"><?php echo esc_textarea( $tpl['body'] ); ?></textarea></td></tr>
							<tr><th></th><td>
								<?php foreach ( $locations as $loc ) : ?>
									<a class="button" target="_blank" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hkr_preview_email&type=' . $type . '&location=' . $loc['id'] ), 'hkr_preview_email' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: location */ __( 'Preview (%s)', 'hakka-reservations' ), $loc['name'] ) ); ?></a>
								<?php endforeach; ?>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hkr_test_email&type=' . $type ), 'hkr_test_email' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Send test to %s', 'hakka-reservations' ), $user->user_email ) ); ?></a>
							</td></tr>
						</table>
					</details>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Save first, then preview, to see your changes.', 'hakka-reservations' ); ?></p>
				<?php submit_button( __( 'Save email wording', 'hakka-reservations' ) ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Put every email back to the original wording?', 'hakka-reservations' ) ); ?>');">
				<input type="hidden" name="action" value="hkr_save_templates">
				<input type="hidden" name="reset" value="1">
				<?php wp_nonce_field( 'hkr_save_templates' ); ?>
				<button class="button-link"><?php esc_html_e( 'Reset all emails to the original wording', 'hakka-reservations' ); ?></button>
			</form>
		</div>
		<?php
	}

	public static function save_templates() {
		HKR_Admin::check( 'hkr_manage', 'hkr_save_templates' );
		if ( ! empty( $_POST['reset'] ) ) {
			delete_option( HKR_Settings::TEMPLATES );
			HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-messages' ), __( 'Emails reset to the original wording.', 'hakka-reservations' ) );
		}
		$in  = isset( $_POST['t'] ) ? (array) wp_unslash( $_POST['t'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		$out = array();
		foreach ( array_keys( HKR_Settings::template_types() ) as $type ) {
			if ( empty( $in[ $type ] ) ) {
				continue;
			}
			$out[ $type ] = array(
				'subject' => sanitize_text_field( isset( $in[ $type ]['subject'] ) ? $in[ $type ]['subject'] : '' ),
				'heading' => sanitize_text_field( isset( $in[ $type ]['heading'] ) ? $in[ $type ]['heading'] : '' ),
				'banner'  => sanitize_text_field( isset( $in[ $type ]['banner'] ) ? $in[ $type ]['banner'] : '' ),
				'body'    => wp_kses_post( isset( $in[ $type ]['body'] ) ? $in[ $type ]['body'] : '' ),
			);
		}
		update_option( HKR_Settings::TEMPLATES, $out, false );
		hkr_log( 0, 'settings:emails', '', '' );
		HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-messages' ), __( 'Email wording saved.', 'hakka-reservations' ) );
	}

	private static function sample_for( $type, $location_id ) {
		$sample = HKR_Mailer::sample_booking( $location_id );
		if ( in_array( $type, array( 'guest_confirmed', 'guest_reminder', 'guest_changed' ), true ) ) {
			$sample['status'] = 'confirmed';
		} elseif ( 'guest_declined' === $type ) {
			$sample['status'] = 'declined';
		} elseif ( 'guest_cancelled' === $type ) {
			$sample['status'] = 'cancelled';
		}
		return $sample;
	}

	private static function sample_extra( $type ) {
		$extra = array( 'token' => 'preview' );
		if ( in_array( $type, array( 'guest_declined', 'guest_changed', 'guest_cancelled' ), true ) ) {
			$extra['staff_message'] = __( 'We are fully booked at 7:00pm. We can offer 8:00pm instead – please call us to confirm.', 'hakka-reservations' );
		}
		if ( 'staff_changed' === $type ) {
			$extra['change_summary'] = __( 'The guest asked to change this booking. Please call them.', 'hakka-reservations' );
			$extra['guest_message']  = __( 'Could we move to 8pm and add two more guests?', 'hakka-reservations' );
		}
		return $extra;
	}

	public static function preview() {
		HKR_Admin::check( 'hkr_manage', 'hkr_preview_email' );
		$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'guest_received';
		$loc   = isset( $_GET['location'] ) ? absint( $_GET['location'] ) : 0;
		$types = HKR_Settings::template_types();
		if ( ! isset( $types[ $type ] ) ) {
			wp_die( esc_html__( 'Unknown email.', 'hakka-reservations' ) );
		}
		$rendered = HKR_Mailer::render( $type, self::sample_for( $type, $loc ), self::sample_extra( $type ) );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<div style="font:14px sans-serif;padding:10px 16px;background:#fffbe6;border-bottom:1px solid #eee;"><strong>' . esc_html__( 'Subject:', 'hakka-reservations' ) . '</strong> ' . esc_html( $rendered['subject'] ) . '</div>';
		echo $rendered['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping in HKR_Mailer.
		exit;
	}

	public static function test() {
		HKR_Admin::check( 'hkr_manage', 'hkr_test_email' );
		$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'guest_received';
		$locs  = HKR_Locations::all();
		$user  = wp_get_current_user();
		$extra = array_merge( self::sample_extra( $type ), array( 'to' => $user->user_email ) );
		$sent  = HKR_Mailer::send( $type, self::sample_for( $type, $locs ? $locs[0]['id'] : 0 ), $extra );
		HKR_Admin::redirect(
			admin_url( 'admin.php?page=hkr-messages' ),
			/* translators: %s: email */
			$sent ? sprintf( __( 'Test email sent to %s.', 'hakka-reservations' ), $user->user_email ) : __( 'The test email could not be sent. Check Post SMTP\'s email log.', 'hakka-reservations' ),
			$sent ? 'success' : 'error'
		);
	}
}
