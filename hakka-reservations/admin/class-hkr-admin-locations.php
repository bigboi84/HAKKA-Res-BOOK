<?php
/**
 * Admin: locations – details, email routing, opening hours, special dates, slots and booking types.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Admin_Locations {

	public static function init() {
		add_action( 'admin_post_hkr_save_location', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_hkr_test_location_email', array( __CLASS__, 'test_email' ) );
	}

	public static function page() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id || isset( $_GET['new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::edit( $id );
			return;
		}
		?>
		<div class="wrap hkr-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Locations', 'hakka-reservations' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hkr-locations&new=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add location', 'hakka-reservations' ); ?></a>
			<hr class="wp-header-end">
			<table class="widefat striped hkr-locations-table">
				<thead><tr>
					<th><?php esc_html_e( 'Location', 'hakka-reservations' ); ?></th>
					<th><?php esc_html_e( 'Staff booking alerts go to', 'hakka-reservations' ); ?></th>
					<th><?php esc_html_e( 'Opening hours', 'hakka-reservations' ); ?></th>
					<th><?php esc_html_e( 'Booking types', 'hakka-reservations' ); ?></th>
					<th><?php esc_html_e( 'Online bookings', 'hakka-reservations' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( HKR_Locations::all() as $loc ) : ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=hkr-locations&id=' . $loc['id'] ) ); ?>"><?php echo esc_html( $loc['name'] ); ?></a></strong><br>
							<?php echo esc_html( $loc['address'] ); ?><br><?php echo esc_html( hkr_display_phone( $loc['phone'] ) ); ?>
						</td>
						<td><?php echo esc_html( implode( ', ', hkr_parse_emails( $loc['alert_emails'] ) ) ); ?></td>
						<td><?php echo wp_kses( implode( '<br>', array_map( 'esc_html', HKR_Locations::hours_summary( $loc ) ) ), array( 'br' => array() ) ); ?>
							<br><small><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( 'Last booking %d min before closing', 'hakka-reservations' ), (int) $loc['settings']['last_before_close'] ) ); ?></small></td>
						<td>
							<?php
							$lines = array();
							foreach ( HKR_Locations::areas( $loc['id'] ) as $a ) {
								$lines[] = esc_html( $a['name'] . ( null !== $a['capacity'] ? ' (' . $a['capacity'] . ')' : '' ) );
							}
							echo wp_kses( implode( '<br>', $lines ), array( 'br' => array() ) );
							if ( '' !== (string) $loc['settings']['max_covers'] ) {
								echo '<br><small>' . esc_html( sprintf( /* translators: %d: guests */ __( 'Whole location: %d guests per slot', 'hakka-reservations' ), (int) $loc['settings']['max_covers'] ) ) . '</small>';
							}
							?>
						</td>
						<td><?php echo $loc['active'] && $loc['accepts_bookings'] ? esc_html__( 'On', 'hakka-reservations' ) : '<strong>' . esc_html__( 'Off', 'hakka-reservations' ) . '</strong>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function edit( $id ) {
		$loc = $id ? HKR_Locations::get( $id ) : null;
		if ( $id && ! $loc ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Location not found.', 'hakka-reservations' ) . '</p></div>';
			return;
		}
		if ( ! $loc ) {
			$loc = array(
				'id'                => 0,
				'name'              => '',
				'slug'              => '',
				'active'            => 1,
				'accepts_bookings'  => 1,
				'address'           => '',
				'phone'             => '',
				'map_url'           => '',
				'sort_order'        => count( HKR_Locations::all() ) + 1,
				'header_image_id'   => 0,
				'header_image_alt'  => '',
				'email_image_id'    => 0,
				'alert_emails'      => '',
				'from_name'         => '',
				'from_email'        => '',
				'reply_to'          => '',
				'bcc'               => '',
				'confirm_note'      => __( 'We will call you to confirm your booking.', 'hakka-reservations' ),
				'settings'          => HKR_Locations::default_settings(),
				'hours'             => HKR_Locations::normalize_hours( array() ),
				'special_dates'     => array(),
			);
		}
		$s     = $loc['settings'];
		$areas = $loc['id'] ? HKR_Locations::areas( $loc['id'], false ) : array();
		$days  = HKR_Locations::weekdays();
		?>
		<div class="wrap hkr-wrap">
			<h1><?php echo $loc['id'] ? esc_html( $loc['name'] ) : esc_html__( 'Add location', 'hakka-reservations' ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=hkr-locations' ) ); ?>">&larr; <?php esc_html_e( 'All locations', 'hakka-reservations' ); ?></a></p>

			<nav class="hkr-tabs" aria-label="<?php esc_attr_e( 'Location sections', 'hakka-reservations' ); ?>">
				<a href="#hkr-general"><?php esc_html_e( 'General', 'hakka-reservations' ); ?></a>
				<a href="#hkr-email"><?php esc_html_e( 'Email routing', 'hakka-reservations' ); ?></a>
				<a href="#hkr-hours"><?php esc_html_e( 'Opening hours', 'hakka-reservations' ); ?></a>
				<a href="#hkr-special"><?php esc_html_e( 'Special dates', 'hakka-reservations' ); ?></a>
				<a href="#hkr-slots"><?php esc_html_e( 'Slots & capacity', 'hakka-reservations' ); ?></a>
				<a href="#hkr-types"><?php esc_html_e( 'Booking types', 'hakka-reservations' ); ?></a>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hkr-location-form">
				<input type="hidden" name="action" value="hkr_save_location">
				<input type="hidden" name="id" value="<?php echo esc_attr( $loc['id'] ); ?>">
				<?php wp_nonce_field( 'hkr_save_location' ); ?>

				<section class="hkr-panel" id="hkr-general">
					<h2><?php esc_html_e( 'General', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label for="hkr-l-name"><?php esc_html_e( 'Name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-name" name="loc[name]" class="regular-text" value="<?php echo esc_attr( $loc['name'] ); ?>" required></td></tr>
						<tr><th><label for="hkr-l-slug"><?php esc_html_e( 'Slug', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-slug" name="loc[slug]" class="regular-text" value="<?php echo esc_attr( $loc['slug'] ); ?>"><p class="description"><?php esc_html_e( 'Used in links such as /reservations/?loc=brentwood. Leave blank to make one from the name.', 'hakka-reservations' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Status', 'hakka-reservations' ); ?></th><td>
							<label><input type="checkbox" name="loc[active]" value="1" <?php checked( $loc['active'], 1 ); ?>> <?php esc_html_e( 'Active', 'hakka-reservations' ); ?></label><br>
							<label><input type="checkbox" name="loc[accepts_bookings]" value="1" <?php checked( $loc['accepts_bookings'], 1 ); ?>> <?php esc_html_e( 'Accepts online bookings', 'hakka-reservations' ); ?></label>
						</td></tr>
						<tr><th><label for="hkr-l-address"><?php esc_html_e( 'Address', 'hakka-reservations' ); ?></label></th><td><textarea id="hkr-l-address" name="loc[address]" rows="2" class="large-text"><?php echo esc_textarea( $loc['address'] ); ?></textarea></td></tr>
						<tr><th><label for="hkr-l-phone"><?php esc_html_e( 'Phone', 'hakka-reservations' ); ?></label></th><td><input type="tel" id="hkr-l-phone" name="loc[phone]" class="regular-text" value="<?php echo esc_attr( hkr_display_phone( $loc['phone'] ) ); ?>"></td></tr>
						<tr><th><label for="hkr-l-map"><?php esc_html_e( 'Google Maps link', 'hakka-reservations' ); ?></label></th><td><input type="url" id="hkr-l-map" name="loc[map_url]" class="large-text" value="<?php echo esc_attr( $loc['map_url'] ); ?>"></td></tr>
						<tr><th><label for="hkr-l-order"><?php esc_html_e( 'Display order', 'hakka-reservations' ); ?></label></th><td><input type="number" id="hkr-l-order" name="loc[sort_order]" class="small-text" value="<?php echo esc_attr( $loc['sort_order'] ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Booking form header image', 'hakka-reservations' ); ?></th><td>
							<?php HKR_Admin::image_field( 'loc[header_image_id]', $loc['header_image_id'], __( 'Shown at the top of the booking form when a guest picks this location. Recommended 1600 × 600 px, JPG or WebP, under 300 KB.', 'hakka-reservations' ), 'hkr-hero-img' ); ?>
						</td></tr>
						<tr><th><?php esc_html_e( 'Different photo for phones (optional)', 'hakka-reservations' ); ?></th><td>
							<?php HKR_Admin::image_field( 'hero_mobile_id', $s['hero_mobile_id'], __( 'Only if the main photo does not crop well on a phone. A taller photo works best, about 1200 × 800 px.', 'hakka-reservations' ), 'hkr-hero-img-m' ); ?>
						</td></tr>
						<tr><th><?php esc_html_e( 'Position the photo', 'hakka-reservations' ); ?></th><td>
							<?php HKR_Admin::frame_editor( 'hero_frame', $s['hero_frame'], 'hkr-hero-img', 'hkr-hero-img-m', array( $loc['name'], $loc['address'] ) ); ?>
							<p class="description"><?php esc_html_e( 'Drag each preview until the best part of the photo shows, and zoom in if needed. This is exactly how the top of the booking form looks on a computer and on a phone. Click "Save location" to keep it.', 'hakka-reservations' ); ?></p>
						</td></tr>
						<tr><th><label for="hkr-l-alt"><?php esc_html_e( 'Image description (alt text)', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-alt" name="loc[header_image_alt]" class="large-text" value="<?php echo esc_attr( $loc['header_image_alt'] ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Email header image (optional)', 'hakka-reservations' ); ?></th><td>
							<?php HKR_Admin::image_field( 'loc[email_image_id]', $loc['email_image_id'], __( 'Shown at the top of guest emails for this location. If empty, the booking form image is used.', 'hakka-reservations' ) ); ?>
						</td></tr>
						<tr><th><label for="hkr-l-note"><?php esc_html_e( 'Confirmation note for guests', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-note" name="loc[confirm_note]" class="large-text" value="<?php echo esc_attr( $loc['confirm_note'] ); ?>"><p class="description"><?php esc_html_e( 'Shown after booking. Every booking is confirmed by staff.', 'hakka-reservations' ); ?></p></td></tr>
					</table>
				</section>

				<section class="hkr-panel" id="hkr-email">
					<h2><?php esc_html_e( 'Email routing', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label for="hkr-l-alerts"><?php esc_html_e( 'Staff booking alert email(s)', 'hakka-reservations' ); ?></label></th><td>
							<input type="text" id="hkr-l-alerts" name="loc[alert_emails]" class="large-text" value="<?php echo esc_attr( $loc['alert_emails'] ); ?>" required>
							<p class="description"><?php esc_html_e( 'New-booking alerts for this location go only here. Separate several addresses with commas.', 'hakka-reservations' ); ?></p>
						</td></tr>
						<tr><th><label for="hkr-l-fromname"><?php esc_html_e( 'Guest email "From" name', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-fromname" name="loc[from_name]" class="regular-text" value="<?php echo esc_attr( $loc['from_name'] ); ?>" placeholder="<?php echo esc_attr( HKR_Settings::get( 'from_name' ) ); ?>"></td></tr>
						<tr><th><label for="hkr-l-from"><?php esc_html_e( 'Guest email "From" address', 'hakka-reservations' ); ?></label></th><td><input type="email" id="hkr-l-from" name="loc[from_email]" class="regular-text" value="<?php echo esc_attr( $loc['from_email'] ); ?>" placeholder="<?php echo esc_attr( HKR_Settings::get( 'from_email' ) ); ?>"><p class="description"><?php esc_html_e( 'Leave blank to use the main sender from Settings (shared by every location).', 'hakka-reservations' ); ?></p></td></tr>
						<tr><th><label for="hkr-l-reply"><?php esc_html_e( 'Reply-to address', 'hakka-reservations' ); ?></label></th><td><input type="email" id="hkr-l-reply" name="loc[reply_to]" class="regular-text" value="<?php echo esc_attr( $loc['reply_to'] ); ?>" placeholder="<?php echo esc_attr( HKR_Settings::get( 'reply_to' ) ); ?>"></td></tr>
						<tr><th><label for="hkr-l-bcc"><?php esc_html_e( 'BCC on guest emails (optional)', 'hakka-reservations' ); ?></label></th><td><input type="text" id="hkr-l-bcc" name="loc[bcc]" class="regular-text" value="<?php echo esc_attr( $loc['bcc'] ); ?>"></td></tr>
						<?php if ( $loc['id'] ) : ?>
						<tr><th><?php esc_html_e( 'Test', 'hakka-reservations' ); ?></th><td>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hkr_test_location_email&kind=guest&id=' . $loc['id'] ), 'hkr_test_location_' . $loc['id'] ) ); ?>"><?php esc_html_e( 'Send test guest email to me', 'hakka-reservations' ); ?></a>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hkr_test_location_email&kind=staff&id=' . $loc['id'] ), 'hkr_test_location_' . $loc['id'] ) ); ?>"><?php esc_html_e( 'Send test staff alert to this location', 'hakka-reservations' ); ?></a>
						</td></tr>
						<?php endif; ?>
					</table>
				</section>

				<section class="hkr-panel" id="hkr-hours">
					<h2><?php esc_html_e( 'Opening hours', 'hakka-reservations' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Set each day separately. For a midnight close, enter 12:00 AM (00:00). Add a second range for split service, e.g. lunch and dinner.', 'hakka-reservations' ); ?></p>
					<table class="widefat hkr-hours">
						<tbody>
						<?php foreach ( $days as $d => $label ) : ?>
							<?php $ranges = $loc['hours'][ $d ]; ?>
							<tr data-day="<?php echo esc_attr( $d ); ?>">
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td class="hkr-closed-cell"><label><input type="checkbox" class="hkr-closed" name="closed[<?php echo esc_attr( $d ); ?>]" value="1" <?php checked( empty( $ranges ) ); ?>> <?php esc_html_e( 'Closed', 'hakka-reservations' ); ?></label></td>
								<td class="hkr-ranges">
									<?php
									if ( ! $ranges ) {
										$ranges = array( array( '11:00', '23:00' ) );
									}
									foreach ( $ranges as $i => $r ) {
										self::range_inputs( 'hours[' . $d . '][' . $i . ']', $r );
									}
									?>
									<button type="button" class="button-link hkr-add-range" data-name="hours[<?php echo esc_attr( $d ); ?>]"><?php esc_html_e( '+ Add another time range', 'hakka-reservations' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="button" class="button hkr-copy-monday"><?php esc_html_e( 'Copy Monday to Tuesday – Friday', 'hakka-reservations' ); ?></button>
						<button type="button" class="button hkr-copy-all"><?php esc_html_e( 'Copy Monday to every day', 'hakka-reservations' ); ?></button>
					</p>
				</section>

				<section class="hkr-panel" id="hkr-special">
					<h2><?php esc_html_e( 'Special dates (holidays and events)', 'hakka-reservations' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These replace the normal hours for the dates given: closed for a holiday, or different hours for an event.', 'hakka-reservations' ); ?></p>
					<table class="widefat hkr-special">
						<thead><tr>
							<th><?php esc_html_e( 'From', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'To', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Closed', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Open', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Close', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Note', 'hakka-reservations' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody>
						<?php
						$specials = $loc['special_dates'];
						foreach ( $specials as $i => $sd ) {
							self::special_row( $i, $sd );
						}
						?>
						</tbody>
					</table>
					<template id="hkr-special-template"><?php self::special_row( '__i__', array() ); ?></template>
					<p><button type="button" class="button hkr-add-special"><?php esc_html_e( 'Add special date', 'hakka-reservations' ); ?></button></p>
				</section>

				<section class="hkr-panel" id="hkr-slots">
					<h2><?php esc_html_e( 'Slots & capacity', 'hakka-reservations' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::number_row( 'slot_length', __( 'Booking length (minutes)', 'hakka-reservations' ), $s['slot_length'], __( 'How long each booking holds its seats. 60 = one-hour slots.', 'hakka-reservations' ), 15 );
						self::number_row( 'step', __( 'A new time slot starts every (minutes)', 'hakka-reservations' ), $s['step'], __( 'Usually the same as the booking length.', 'hakka-reservations' ), 5 );
						self::number_row( 'last_before_close', __( 'Last booking before closing (minutes)', 'hakka-reservations' ), $s['last_before_close'], __( 'No bookings can start in this window before closing. 60 = the last booking is one hour before closing.', 'hakka-reservations' ), 0 );
						self::number_row( 'max_covers', __( 'Guests per time slot, whole location', 'hakka-reservations' ), $s['max_covers'], __( 'The total for all booking types together. Leave blank if only the booking types have limits.', 'hakka-reservations' ), 0, true );
						self::number_row( 'max_bookings', __( 'Bookings per time slot', 'hakka-reservations' ), $s['max_bookings'], __( 'Optional limit on the number of bookings (not guests). Leave blank for no limit.', 'hakka-reservations' ), 0, true );
						self::number_row( 'min_party', __( 'Smallest online party', 'hakka-reservations' ), $s['min_party'], '', 1 );
						self::number_row( 'max_party', __( 'Largest online party', 'hakka-reservations' ), $s['max_party'], __( 'Bigger groups are asked to call.', 'hakka-reservations' ), 1 );
						self::number_row( 'large_party', __( 'Flag as large party from', 'hakka-reservations' ), $s['large_party'], __( 'Highlights the booking for staff.', 'hakka-reservations' ), 0 );
						self::number_row( 'notice_hours', __( 'Minimum notice (hours)', 'hakka-reservations' ), $s['notice_hours'], __( 'How far ahead a guest must book online.', 'hakka-reservations' ), 0 );
						self::number_row( 'advance_days', __( 'Bookings open (days ahead)', 'hakka-reservations' ), $s['advance_days'], '', 1 );
						self::number_row( 'cancel_cutoff', __( 'Guests can cancel online until (hours before)', 'hakka-reservations' ), $s['cancel_cutoff'], '', 0 );
						self::number_row( 'reminder_hours', __( 'Send reminder (hours before)', 'hakka-reservations' ), $s['reminder_hours'], __( 'Confirmed bookings only. 0 = no reminders.', 'hakka-reservations' ), 0 );
						?>
					</table>
					<h3><?php esc_html_e( 'Different settings on certain days (optional)', 'hakka-reservations' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Leave blank to use the settings above.', 'hakka-reservations' ); ?></p>
					<table class="widefat hkr-overrides">
						<thead><tr>
							<th></th>
							<th><?php esc_html_e( 'Booking length', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Last booking before closing', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Guests per slot (location)', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Largest online party', 'hakka-reservations' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $days as $d => $label ) : ?>
							<?php $o = isset( $s['day_overrides'][ $d ] ) ? (array) $s['day_overrides'][ $d ] : array(); ?>
							<tr>
								<th><?php echo esc_html( $label ); ?></th>
								<?php foreach ( array( 'slot_length', 'last_before_close', 'max_covers', 'max_party' ) as $key ) : ?>
									<td><input type="number" min="0" class="small-text" name="overrides[<?php echo esc_attr( $d ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $o[ $key ] ) ? $o[ $key ] : '' ); ?>"></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</section>

				<section class="hkr-panel" id="hkr-types">
					<h2><?php esc_html_e( 'Booking types', 'hakka-reservations' ); ?></h2>
					<p class="description"><?php esc_html_e( 'What guests can book here, e.g. Dining, Event, Corporate Event, or areas such as Bar and Private room. Capacity is guests per time slot for that type; leave it blank if only the whole-location limit applies.', 'hakka-reservations' ); ?></p>
					<table class="widefat hkr-areas">
						<thead><tr>
							<th><?php esc_html_e( 'Name', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Description for guests', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Capacity per slot', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Staff hard max', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Min party', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Max party (online)', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Ask about a celebration', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Days (blank = all)', 'hakka-reservations' ); ?></th>
							<th><?php esc_html_e( 'Active', 'hakka-reservations' ); ?></th>
							<th></th>
						</tr></thead>
						<?php
						foreach ( $areas as $i => $a ) {
							self::area_row( $i, $a );
						}
						?>
					</table>
					<template id="hkr-area-template"><?php self::area_row( '__i__', array() ); ?></template>
					<p><button type="button" class="button hkr-add-area"><?php esc_html_e( 'Add booking type', 'hakka-reservations' ); ?></button></p>
				</section>

				<div class="hkr-savebar"><?php submit_button( __( 'Save location', 'hakka-reservations' ), 'primary', 'submit', false ); ?></div>
			</form>
		</div>
		<?php
	}

	private static function range_inputs( $name, $range ) {
		$open  = isset( $range[0] ) ? $range[0] : '';
		$close = isset( $range[1] ) ? $range[1] : '';
		if ( '24:00' === $close ) {
			$close = '00:00';
		}
		?>
		<span class="hkr-range">
			<input type="time" name="<?php echo esc_attr( $name ); ?>[0]" value="<?php echo esc_attr( $open ); ?>" aria-label="<?php esc_attr_e( 'Opens', 'hakka-reservations' ); ?>">
			–
			<input type="time" name="<?php echo esc_attr( $name ); ?>[1]" value="<?php echo esc_attr( $close ); ?>" aria-label="<?php esc_attr_e( 'Closes', 'hakka-reservations' ); ?>">
			<button type="button" class="button-link hkr-remove-range" aria-label="<?php esc_attr_e( 'Remove this range', 'hakka-reservations' ); ?>">&times;</button>
		</span>
		<?php
	}

	private static function special_row( $i, $sd ) {
		$range = ! empty( $sd['ranges'][0] ) ? $sd['ranges'][0] : array( '', '' );
		if ( '24:00' === $range[1] ) {
			$range[1] = '00:00';
		}
		$n = 'special[' . $i . ']';
		?>
		<tr>
			<td><input type="date" name="<?php echo esc_attr( $n ); ?>[from]" value="<?php echo esc_attr( isset( $sd['from'] ) ? $sd['from'] : '' ); ?>"></td>
			<td><input type="date" name="<?php echo esc_attr( $n ); ?>[to]" value="<?php echo esc_attr( isset( $sd['to'] ) ? $sd['to'] : '' ); ?>"></td>
			<td><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[closed]" value="1" <?php checked( ! empty( $sd['closed'] ) ); ?>></td>
			<td><input type="time" name="<?php echo esc_attr( $n ); ?>[open]" value="<?php echo esc_attr( $range[0] ); ?>"></td>
			<td><input type="time" name="<?php echo esc_attr( $n ); ?>[close]" value="<?php echo esc_attr( $range[1] ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[note]" value="<?php echo esc_attr( isset( $sd['note'] ) ? $sd['note'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Christmas Day', 'hakka-reservations' ); ?>"></td>
			<td><button type="button" class="button-link hkr-remove-row"><?php esc_html_e( 'Remove', 'hakka-reservations' ); ?></button></td>
		</tr>
		<?php
	}

	private static function area_row( $i, $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'id'              => 0,
				'name'            => '',
				'description'     => '',
				'capacity'        => null,
				'hard_max'        => null,
				'min_party'       => 1,
				'max_party'       => null,
				'ask_celebration' => 1,
				'days'            => array(),
				'active'          => 1,
			)
		);
		$n     = 'areas[' . $i . ']';
		$short = array( 1 => 'M', 2 => 'T', 3 => 'W', 4 => 'T', 5 => 'F', 6 => 'S', 7 => 'S' );
		$names = HKR_Locations::weekdays();
		$image = isset( $a['image_id'] ) ? (int) $a['image_id'] : 0;
		$thumb = $image ? wp_get_attachment_image_url( $image, 'thumbnail' ) : '';
		?>
		<tbody class="hkr-area">
		<tr class="hkr-area-row">
			<td><input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $a['id'] ); ?>"><input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $a['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Dining', 'hakka-reservations' ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[description]" value="<?php echo esc_attr( $a['description'] ); ?>"></td>
			<td><input type="number" min="0" class="small-text" name="<?php echo esc_attr( $n ); ?>[capacity]" value="<?php echo esc_attr( null === $a['capacity'] ? '' : $a['capacity'] ); ?>"></td>
			<td><input type="number" min="0" class="small-text" name="<?php echo esc_attr( $n ); ?>[hard_max]" value="<?php echo esc_attr( null === $a['hard_max'] ? '' : $a['hard_max'] ); ?>"></td>
			<td><input type="number" min="1" class="small-text" name="<?php echo esc_attr( $n ); ?>[min_party]" value="<?php echo esc_attr( $a['min_party'] ); ?>"></td>
			<td><input type="number" min="1" class="small-text" name="<?php echo esc_attr( $n ); ?>[max_party]" value="<?php echo esc_attr( null === $a['max_party'] ? '' : $a['max_party'] ); ?>"></td>
			<td><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[ask_celebration]" value="1" <?php checked( $a['ask_celebration'], 1 ); ?>></td>
			<td class="hkr-days">
				<?php foreach ( $short as $d => $letter ) : ?>
					<label title="<?php echo esc_attr( $names[ $d ] ); ?>"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[days][]" value="<?php echo esc_attr( $d ); ?>" <?php checked( in_array( $d, (array) $a['days'], true ) ); ?>><?php echo esc_html( $letter ); ?></label>
				<?php endforeach; ?>
			</td>
			<td><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[active]" value="1" <?php checked( $a['active'], 1 ); ?>></td>
			<td><button type="button" class="button-link hkr-remove-row"><?php esc_html_e( 'Remove', 'hakka-reservations' ); ?></button></td>
		</tr>
		<tr class="hkr-area-photo">
			<td colspan="10">
				<details>
					<summary>
						<span class="hkr-area-thumb"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt=""><?php endif; ?></span>
						<?php echo $image ? esc_html__( 'Photo for this booking type – change or position it', 'hakka-reservations' ) : esc_html__( 'Add a photo for this booking type', 'hakka-reservations' ); ?>
					</summary>
					<div class="hkr-area-photo__body">
						<?php HKR_Admin::image_field( $n . '[image_id]', $image, __( 'Shown on this option in the booking form, and in the header when a guest picks it (e.g. a photo of the bar). Leave empty to keep the location photo.', 'hakka-reservations' ), 'hkr-area-img-' . $i ); ?>
						<?php HKR_Admin::frame_editor( $n . '[frame]', isset( $a['image_frame'] ) ? $a['image_frame'] : array(), 'hkr-area-img-' . $i, '', array( $a['name'] ? $a['name'] : __( 'Booking type', 'hakka-reservations' ), '' ) ); ?>
					</div>
				</details>
			</td>
		</tr>
		</tbody>
		<?php
	}

	private static function number_row( $key, $label, $value, $help = '', $min = 0, $blank_ok = false ) {
		?>
		<tr><th><label for="hkr-s-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
			<input type="number" min="<?php echo esc_attr( $min ); ?>" step="any" class="small-text" id="hkr-s-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php echo $blank_ok ? '' : 'required'; ?>>
			<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
		</td></tr>
		<?php
	}

	public static function save() {
		HKR_Admin::check( 'hkr_manage', 'hkr_save_location' );
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- nonce checked above; each value is sanitized below.
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$in  = isset( $_POST['loc'] ) ? (array) wp_unslash( $_POST['loc'] ) : array();
		$old = $id ? HKR_Locations::get( $id ) : null;

		$alerts = hkr_parse_emails( isset( $in['alert_emails'] ) ? $in['alert_emails'] : '' );
		$data   = array(
			'name'             => sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ),
			'slug'             => sanitize_title( isset( $in['slug'] ) && '' !== $in['slug'] ? $in['slug'] : ( isset( $in['name'] ) ? $in['name'] : '' ) ),
			'active'           => empty( $in['active'] ) ? 0 : 1,
			'accepts_bookings' => empty( $in['accepts_bookings'] ) ? 0 : 1,
			'address'          => sanitize_textarea_field( isset( $in['address'] ) ? $in['address'] : '' ),
			'phone'            => hkr_normalize_phone( isset( $in['phone'] ) ? $in['phone'] : '' ),
			'map_url'          => esc_url_raw( isset( $in['map_url'] ) ? $in['map_url'] : '' ),
			'sort_order'       => isset( $in['sort_order'] ) ? (int) $in['sort_order'] : 0,
			'header_image_id'  => isset( $in['header_image_id'] ) ? absint( $in['header_image_id'] ) : 0,
			'header_image_alt' => sanitize_text_field( isset( $in['header_image_alt'] ) ? $in['header_image_alt'] : '' ),
			'email_image_id'   => isset( $in['email_image_id'] ) ? absint( $in['email_image_id'] ) : 0,
			'alert_emails'     => implode( ', ', $alerts ),
			'from_name'        => sanitize_text_field( isset( $in['from_name'] ) ? $in['from_name'] : '' ),
			'from_email'       => sanitize_email( isset( $in['from_email'] ) ? $in['from_email'] : '' ),
			'reply_to'         => sanitize_email( isset( $in['reply_to'] ) ? $in['reply_to'] : '' ),
			'bcc'              => implode( ', ', hkr_parse_emails( isset( $in['bcc'] ) ? $in['bcc'] : '' ) ),
			'confirm_note'     => sanitize_text_field( isset( $in['confirm_note'] ) ? $in['confirm_note'] : '' ),
		);
		$back = $id ? admin_url( 'admin.php?page=hkr-locations&id=' . $id ) : admin_url( 'admin.php?page=hkr-locations&new=1' );
		if ( '' === $data['name'] ) {
			HKR_Admin::redirect( $back, __( 'Please give the location a name.', 'hakka-reservations' ), 'error' );
		}
		if ( ! $alerts ) {
			HKR_Admin::redirect( $back, __( 'Please enter at least one valid staff booking alert email.', 'hakka-reservations' ), 'error' );
		}
		$clash = HKR_Locations::get_by_slug( $data['slug'] );
		if ( $clash && $clash['id'] !== $id ) {
			HKR_Admin::redirect( $back, __( 'Another location already uses that slug.', 'hakka-reservations' ), 'error' );
		}

		// Opening hours.
		$hours_in = isset( $_POST['hours'] ) ? (array) wp_unslash( $_POST['hours'] ) : array();
		$closed   = isset( $_POST['closed'] ) ? (array) wp_unslash( $_POST['closed'] ) : array();
		$hours    = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$hours[ $d ] = ! empty( $closed[ $d ] ) ? array() : HKR_Locations::normalize_ranges( isset( $hours_in[ $d ] ) ? array_values( (array) $hours_in[ $d ] ) : array() );
		}
		$data['hours'] = $hours;

		// Special dates.
		$special = array();
		foreach ( isset( $_POST['special'] ) ? (array) wp_unslash( $_POST['special'] ) : array() as $row ) {
			$from = isset( $row['from'] ) ? sanitize_text_field( $row['from'] ) : '';
			if ( ! HKR_Availability::valid_date( $from ) ) {
				continue;
			}
			$to = isset( $row['to'] ) && HKR_Availability::valid_date( $row['to'] ) && $row['to'] >= $from ? $row['to'] : $from;
			$special[] = array(
				'from'   => $from,
				'to'     => $to,
				'closed' => ! empty( $row['closed'] ),
				'ranges' => empty( $row['closed'] ) ? HKR_Locations::normalize_ranges( array( array( isset( $row['open'] ) ? $row['open'] : '', isset( $row['close'] ) ? $row['close'] : '' ) ) ) : array(),
				'note'   => sanitize_text_field( isset( $row['note'] ) ? $row['note'] : '' ),
			);
		}
		usort(
			$special,
			static function ( $a, $b ) {
				return strcmp( $a['from'], $b['from'] );
			}
		);
		$data['special_dates'] = $special;

		// Slots and capacity.
		$s_in     = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
		$settings = HKR_Locations::default_settings();
		foreach ( $settings as $key => $default ) {
			if ( in_array( $key, array( 'day_overrides', 'hero_frame', 'hero_mobile_id' ), true ) ) {
				continue;
			}
			$value = isset( $s_in[ $key ] ) ? trim( (string) $s_in[ $key ] ) : '';
			if ( in_array( $key, array( 'max_covers', 'max_bookings' ), true ) ) {
				$settings[ $key ] = '' === $value ? '' : max( 0, (int) $value );
			} elseif ( in_array( $key, array( 'notice_hours', 'cancel_cutoff' ), true ) ) {
				$settings[ $key ] = '' === $value ? $default : max( 0, (float) $value );
			} else {
				$settings[ $key ] = '' === $value ? $default : max( 0, (int) $value );
			}
		}
		$settings['slot_length'] = max( 15, (int) $settings['slot_length'] );
		$settings['step']        = max( 5, (int) $settings['step'] );
		$settings['min_party']   = max( 1, (int) $settings['min_party'] );
		$settings['max_party']   = max( $settings['min_party'], (int) $settings['max_party'] );
		$overrides               = array();
		foreach ( isset( $_POST['overrides'] ) ? (array) wp_unslash( $_POST['overrides'] ) : array() as $d => $row ) {
			$d = (int) $d;
			if ( $d < 1 || $d > 7 ) {
				continue;
			}
			foreach ( array( 'slot_length', 'last_before_close', 'max_covers', 'max_party' ) as $key ) {
				$value = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
				if ( '' !== $value ) {
					$overrides[ $d ][ $key ] = max( 0, (int) $value );
				}
			}
		}
		$settings['day_overrides'] = $overrides;
		$settings['hero_frame']    = hkr_sanitize_frame( isset( $_POST['hero_frame'] ) ? (array) wp_unslash( $_POST['hero_frame'] ) : array() );
		$settings['hero_mobile_id'] = isset( $_POST['hero_mobile_id'] ) ? absint( $_POST['hero_mobile_id'] ) : 0;
		$data['settings']          = $settings;

		$new_id = HKR_Locations::save( $data, $id );

		// Booking types.
		$areas = isset( $_POST['areas'] ) ? array_values( (array) wp_unslash( $_POST['areas'] ) ) : array();
		foreach ( $areas as &$area ) {
			$area['ask_celebration'] = ! empty( $area['ask_celebration'] );
			$area['active']          = ! empty( $area['active'] );
			$area['days']            = isset( $area['days'] ) ? array_map( 'intval', (array) $area['days'] ) : array();
		}
		unset( $area );
		HKR_Locations::save_areas( $new_id, $areas, true );
		// phpcs:enable

		HKR_Locations::flush_cache();
		hkr_purge_page_cache();
		hkr_log( 0, $id ? 'settings:location' : 'settings:location_added', $old ? $old['name'] : '', $data['name'] );
		HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-locations&id=' . $new_id ), __( 'Location saved.', 'hakka-reservations' ) );
	}

	public static function test_email() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		HKR_Admin::check( 'hkr_manage', 'hkr_test_location_' . $id );
		$kind    = isset( $_GET['kind'] ) && 'staff' === $_GET['kind'] ? 'staff' : 'guest';
		$sample  = HKR_Mailer::sample_booking( $id );
		$user    = wp_get_current_user();
		$extra   = 'guest' === $kind ? array( 'to' => $user->user_email, 'token' => 'test' ) : array();
		$sent    = HKR_Mailer::send( 'guest' === $kind ? 'guest_received' : 'staff_new', $sample, $extra );
		$loc     = HKR_Locations::get( $id );
		$to      = 'guest' === $kind ? $user->user_email : implode( ', ', hkr_parse_emails( $loc ? $loc['alert_emails'] : '' ) );
		HKR_Admin::redirect(
			admin_url( 'admin.php?page=hkr-locations&id=' . $id ),
			/* translators: %s: email address(es) */
			$sent ? sprintf( __( 'Test email sent to %s.', 'hakka-reservations' ), $to ) : __( 'The test email could not be sent. Check Post SMTP\'s email log.', 'hakka-reservations' ),
			$sent ? 'success' : 'error'
		);
	}
}
