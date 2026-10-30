<?php
/**
 * Admin: bookings list, add/edit screen, bulk actions and CSV export.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class HKR_Bookings_Table extends WP_List_Table {

	private $locations = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			)
		);
		foreach ( HKR_Locations::all() as $loc ) {
			$this->locations[ $loc['id'] ] = $loc['name'];
		}
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'ref'        => __( 'Reference', 'hakka-reservations' ),
			'start_at'   => __( 'Date & time', 'hakka-reservations' ),
			'location'   => __( 'Location', 'hakka-reservations' ),
			'guest_name' => __( 'Guest', 'hakka-reservations' ),
			'party_size' => __( 'Guests', 'hakka-reservations' ),
			'area'       => __( 'Booking type', 'hakka-reservations' ),
			'status'     => __( 'Status', 'hakka-reservations' ),
			'source'     => __( 'Source', 'hakka-reservations' ),
			'created_at' => __( 'Created', 'hakka-reservations' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'ref'        => array( 'id', false ),
			'start_at'   => array( 'start_at', false ),
			'guest_name' => array( 'guest_name', false ),
			'party_size' => array( 'party_size', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'confirmed' => __( 'Confirm (emails the guest)', 'hakka-reservations' ),
			'cancelled' => __( 'Cancel (emails the guest)', 'hakka-reservations' ),
			'no_show'   => __( 'Mark as no-show', 'hakka-reservations' ),
			'completed' => __( 'Mark as completed', 'hakka-reservations' ),
		);
	}

	public static function filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification
		$f = array(
			'location'  => isset( $_GET['location'] ) ? absint( $_GET['location'] ) : 0,
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
		);
		// phpcs:enable
		if ( ! $f['date_from'] && ! $f['date_to'] && ! $f['search'] && ! $f['status'] && ! isset( $_GET['all'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$f['date_from'] = hkr_now()->format( 'Y-m-d' ); // Default: upcoming bookings.
		}
		foreach ( array( 'date_from', 'date_to' ) as $k ) {
			if ( $f[ $k ] && ! HKR_Availability::valid_date( $f[ $k ] ) ) {
				$f[ $k ] = '';
			}
		}
		return $f;
	}

	public static function query_args( $f ) {
		$ids = hkr_user_location_ids();
		if ( $f['location'] ) {
			$ids = hkr_user_can_location( $f['location'] ) ? array( $f['location'] ) : array();
		}
		return array(
			'location_ids' => $ids,
			'status'       => $f['status'],
			'date_from'    => $f['date_from'],
			'date_to'      => $f['date_to'],
			'search'       => $f['search'],
			'orderby'      => $f['orderby'] ? $f['orderby'] : 'start_at',
			'order'        => $f['order'] ? $f['order'] : 'asc',
		);
	}

	public function prepare_items() {
		$per_page = 30;
		$f        = self::filters_from_request();
		$args     = self::query_args( $f );
		$args['limit']  = $per_page;
		$args['offset'] = ( $this->get_pagenum() - 1 ) * $per_page;
		$result         = HKR_Bookings::query( $args );
		$this->items    = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="booking[]" value="' . esc_attr( $item['id'] ) . '">';
	}

	protected function column_ref( $item ) {
		$edit = admin_url( 'admin.php?page=hkr-booking-edit&id=' . $item['id'] );
		return '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $item['ref'] ) . '</a></strong>' . $this->row_actions( array( 'edit' => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Open', 'hakka-reservations' ) . '</a>' ) );
	}

	protected function column_start_at( $item ) {
		return esc_html( hkr_format_date( $item['date'], 'D j M Y' ) ) . '<br><strong>' . esc_html( hkr_format_time( $item['time'] ) ) . '</strong>';
	}

	protected function column_location( $item ) {
		return esc_html( isset( $this->locations[ $item['location_id'] ] ) ? $this->locations[ $item['location_id'] ] : '—' );
	}

	protected function column_guest_name( $item ) {
		$out = '<strong>' . esc_html( $item['guest_name'] ) . '</strong><br><a href="tel:' . esc_attr( $item['guest_phone'] ) . '">' . esc_html( hkr_display_phone( $item['guest_phone'] ) ) . '</a>';
		$celebration = HKR_Mailer::celebration_label( $item );
		if ( $celebration ) {
			$out .= '<br><span class="hkr-tag">🎉 ' . esc_html( $celebration ) . '</span>';
		}
		if ( $item['dietary_notes'] ) {
			$out .= ' <span class="hkr-tag hkr-tag--warn">' . esc_html__( 'Allergy note', 'hakka-reservations' ) . '</span>';
		}
		return $out;
	}

	protected function column_status( $item ) {
		return '<span class="hkr-status hkr-status--' . esc_attr( $item['status'] ) . '">' . esc_html( hkr_status_label( $item['status'] ) ) . '</span>';
	}

	protected function column_default( $item, $column ) {
		switch ( $column ) {
			case 'party_size':
				return (int) $item['party_size'];
			case 'area':
				return esc_html( $item['area_name'] ? $item['area_name'] : '—' );
			case 'source':
				$sources = hkr_sources();
				return esc_html( isset( $sources[ $item['source'] ] ) ? $sources[ $item['source'] ] : $item['source'] );
			case 'created_at':
				return esc_html( $item['created_at'] ? wp_date( 'j M, g:ia', strtotime( $item['created_at'] . ' ' . hkr_tz()->getName() ) ) : '' );
		}
		return '';
	}

	public function no_items() {
		esc_html_e( 'No bookings match these filters.', 'hakka-reservations' );
	}
}

class HKR_Admin_Bookings {

	public static function init() {
		add_action( 'admin_post_hkr_save_booking', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_hkr_export_bookings', array( __CLASS__, 'export' ) );
		add_action( 'admin_init', array( __CLASS__, 'bulk' ) );
	}

	public static function page() {
		$table = new HKR_Bookings_Table();
		$table->prepare_items();
		$f = HKR_Bookings_Table::filters_from_request();
		$staff_page = (int) HKR_Settings::get( 'staff_page_id' );
		?>
		<div class="wrap hkr-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'hakka-reservations' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hkr-booking-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add booking', 'hakka-reservations' ); ?></a>
			<?php if ( $staff_page ) : ?>
				<a href="<?php echo esc_url( get_permalink( $staff_page ) ); ?>" class="page-title-action" target="_blank"><?php esc_html_e( 'Open staff screen', 'hakka-reservations' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<form method="get" class="hkr-filters">
				<input type="hidden" name="page" value="hkr-bookings">
				<select name="location">
					<option value=""><?php esc_html_e( 'All locations', 'hakka-reservations' ); ?></option>
					<?php foreach ( HKR_Locations::all() as $loc ) : ?>
						<?php if ( hkr_user_can_location( $loc['id'] ) ) : ?>
							<option value="<?php echo esc_attr( $loc['id'] ); ?>" <?php selected( $f['location'], $loc['id'] ); ?>><?php echo esc_html( $loc['name'] ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'hakka-reservations' ); ?></option>
					<?php foreach ( hkr_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'From', 'hakka-reservations' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>"></label>
				<label><?php esc_html_e( 'To', 'hakka-reservations' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>"></label>
				<input type="search" name="s" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, phone, email or reference', 'hakka-reservations' ); ?>">
				<button class="button"><?php esc_html_e( 'Filter', 'hakka-reservations' ); ?></button>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=hkr-bookings&all=1' ) ); ?>"><?php esc_html_e( 'Show all dates', 'hakka-reservations' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'hkr_export_bookings' ), array_filter( $f ) ), admin_url( 'admin-post.php' ) ), 'hkr_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'hakka-reservations' ); ?></a>
			</form>
			<form method="post">
				<?php wp_nonce_field( 'hkr_bulk', 'hkr_bulk_nonce' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function bulk() {
		if ( ! isset( $_POST['hkr_bulk_nonce'], $_POST['booking'] ) || ! isset( $_GET['page'] ) || 'hkr-bookings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( 'hkr_edit_bookings' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hkr_bulk_nonce'] ) ), 'hkr_bulk' ) ) {
			return;
		}
		$action = isset( $_POST['action'] ) && '-1' !== $_POST['action'] ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ( isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '' );
		if ( ! in_array( $action, array( 'confirmed', 'cancelled', 'no_show', 'completed' ), true ) ) {
			return;
		}
		$done = 0;
		foreach ( array_map( 'absint', (array) wp_unslash( $_POST['booking'] ) ) as $id ) {
			$b = HKR_Bookings::get( $id );
			if ( $b && hkr_user_can_location( $b['location_id'] ) && ! is_wp_error( HKR_Bookings::set_status( $id, $action, array( 'notify' => true ) ) ) ) {
				$done++;
			}
		}
		/* translators: %d: number of bookings */
		HKR_Admin::redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=hkr-bookings' ), sprintf( _n( '%d booking updated.', '%d bookings updated.', $done, 'hakka-reservations' ), $done ) );
	}

	/**
	 * Add or edit one booking.
	 */
	public static function edit_page() {
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$booking = $id ? HKR_Bookings::get( $id ) : null;
		if ( $id && ( ! $booking || ! hkr_user_can_location( $booking['location_id'] ) ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Booking not found.', 'hakka-reservations' ) . '</p></div>';
			return;
		}
		$locations = array_values(
			array_filter(
				HKR_Locations::all(),
				static function ( $l ) {
					return hkr_user_can_location( $l['id'] );
				}
			)
		);
		$b = $booking ? $booking : array(
			'location_id'         => $locations ? $locations[0]['id'] : 0,
			'area_id'             => 0,
			'date'                => hkr_now()->format( 'Y-m-d' ),
			'time'                => '',
			'party_size'          => 2,
			'status'              => 'confirmed',
			'source'              => 'phone',
			'guest_name'          => '',
			'guest_phone'         => '',
			'guest_email'         => '',
			'celebration_type'    => '',
			'celebration_text'    => '',
			'celebration_details' => '',
			'seating_pref'        => '',
			'high_chair'          => 0,
			'dietary_notes'       => '',
			'special_requests'    => '',
			'internal_notes'      => '',
		);
		$areas_by_loc = array();
		foreach ( $locations as $loc ) {
			foreach ( HKR_Locations::areas( $loc['id'], false ) as $a ) {
				$areas_by_loc[ $loc['id'] ][] = array( 'id' => $a['id'], 'name' => $a['name'] . ( $a['active'] ? '' : ' (' . __( 'inactive', 'hakka-reservations' ) . ')' ) );
			}
		}
		$celebrations = HKR_Settings::celebration_options();
		?>
		<div class="wrap hkr-wrap">
			<h1><?php echo $booking ? esc_html( sprintf( /* translators: %s: reference */ __( 'Booking %s', 'hakka-reservations' ), $booking['ref'] ) ) : esc_html__( 'Add booking', 'hakka-reservations' ); ?></h1>
			<?php if ( ! $booking ) : ?>
				<p><?php esc_html_e( 'For phone and walk-in bookings. Staff bookings can use a booking type\'s hard maximum and are not limited by the online notice period.', 'hakka-reservations' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hkr-booking-form" data-areas="<?php echo esc_attr( wp_json_encode( $areas_by_loc ) ); ?>" data-booking="<?php echo esc_attr( $booking ? $booking['id'] : 0 ); ?>">
				<input type="hidden" name="action" value="hkr_save_booking">
				<input type="hidden" name="id" value="<?php echo esc_attr( $booking ? $booking['id'] : 0 ); ?>">
				<?php wp_nonce_field( 'hkr_save_booking' ); ?>
				<div class="hkr-columns">
					<div class="hkr-panel">
						<h2><?php esc_html_e( 'When and where', 'hakka-reservations' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr><th><label for="hkr-location"><?php esc_html_e( 'Location', 'hakka-reservations' ); ?></label></th><td>
								<select name="location_id" id="hkr-location" <?php disabled( (bool) $booking ); ?>>
									<?php foreach ( $locations as $loc ) : ?>
										<option value="<?php echo esc_attr( $loc['id'] ); ?>" <?php selected( $b['location_id'], $loc['id'] ); ?>><?php echo esc_html( $loc['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $booking ) : ?><input type="hidden" name="location_id" value="<?php echo esc_attr( $b['location_id'] ); ?>"><?php endif; ?>
							</td></tr>
							<tr><th><label for="hkr-area"><?php esc_html_e( 'Booking type', 'hakka-reservations' ); ?></label></th><td>
								<select name="area_id" id="hkr-area" data-current="<?php echo esc_attr( $b['area_id'] ); ?>"></select>
							</td></tr>
							<tr><th><label for="hkr-date"><?php esc_html_e( 'Date', 'hakka-reservations' ); ?></label></th><td><input type="date" name="date" id="hkr-date" value="<?php echo esc_attr( $b['date'] ); ?>" required></td></tr>
							<tr><th><label for="hkr-party"><?php esc_html_e( 'Guests', 'hakka-reservations' ); ?></label></th><td><input type="number" name="party" id="hkr-party" min="1" max="500" value="<?php echo esc_attr( $b['party_size'] ); ?>" required></td></tr>
							<tr><th><label for="hkr-time"><?php esc_html_e( 'Time', 'hakka-reservations' ); ?></label></th><td>
								<select name="time" id="hkr-time" data-current="<?php echo esc_attr( $b['time'] ); ?>" required></select>
								<p class="description hkr-slot-help"></p>
							</td></tr>
							<tr><th><label for="hkr-status"><?php esc_html_e( 'Status', 'hakka-reservations' ); ?></label></th><td>
								<select name="status" id="hkr-status">
									<?php foreach ( hkr_statuses() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $b['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
							<tr><th><label for="hkr-source"><?php esc_html_e( 'Source', 'hakka-reservations' ); ?></label></th><td>
								<select name="source" id="hkr-source" <?php disabled( (bool) $booking ); ?>>
									<?php foreach ( hkr_sources() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $b['source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
						</table>
					</div>
					<div class="hkr-panel">
						<h2><?php esc_html_e( 'Guest', 'hakka-reservations' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr><th><label for="hkr-name"><?php esc_html_e( 'Name', 'hakka-reservations' ); ?></label></th><td><input type="text" class="regular-text" name="name" id="hkr-name" value="<?php echo esc_attr( $b['guest_name'] ); ?>" required></td></tr>
							<tr><th><label for="hkr-phone"><?php esc_html_e( 'Phone', 'hakka-reservations' ); ?></label></th><td><input type="tel" class="regular-text" name="phone" id="hkr-phone" value="<?php echo esc_attr( hkr_display_phone( $b['guest_phone'] ) ); ?>"></td></tr>
							<tr><th><label for="hkr-email"><?php esc_html_e( 'Email', 'hakka-reservations' ); ?></label></th><td><input type="email" class="regular-text" name="email" id="hkr-email" value="<?php echo esc_attr( $b['guest_email'] ); ?>"></td></tr>
							<tr><th><label for="hkr-celebration"><?php esc_html_e( 'Celebration', 'hakka-reservations' ); ?></label></th><td>
								<?php $current_celebration = $b['celebration_type'] ? $b['celebration_type'] : ''; ?>
								<select name="celebration" id="hkr-celebration">
									<option value=""><?php esc_html_e( 'None', 'hakka-reservations' ); ?></option>
									<?php foreach ( $celebrations as $c ) : ?>
										<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $current_celebration, $c ); ?>><?php echo esc_html( $c ); ?></option>
									<?php endforeach; ?>
									<option value="Other" <?php selected( $current_celebration, 'Other' ); ?>><?php esc_html_e( 'Other', 'hakka-reservations' ); ?></option>
								</select>
								<input type="text" name="celebration_text" class="regular-text" placeholder="<?php esc_attr_e( 'What are they celebrating?', 'hakka-reservations' ); ?>" value="<?php echo esc_attr( $b['celebration_text'] ); ?>">
								<textarea name="celebration_details" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Celebration details', 'hakka-reservations' ); ?>"><?php echo esc_textarea( $b['celebration_details'] ); ?></textarea>
							</td></tr>
							<?php if ( 'off' !== HKR_Settings::get( 'field_seating' ) ) : ?>
							<tr><th><label for="hkr-seating"><?php esc_html_e( 'Seating preference', 'hakka-reservations' ); ?></label></th><td>
								<select name="seating" id="hkr-seating">
									<option value=""><?php esc_html_e( 'None', 'hakka-reservations' ); ?></option>
									<?php foreach ( HKR_Settings::seating_options() as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $b['seating_pref'], $s ); ?>><?php echo esc_html( $s ); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
							<?php endif; ?>
							<tr><th><?php esc_html_e( 'High chair', 'hakka-reservations' ); ?></th><td><label><input type="checkbox" name="high_chair" value="1" <?php checked( (int) $b['high_chair'], 1 ); ?>> <?php esc_html_e( 'Needed', 'hakka-reservations' ); ?></label></td></tr>
							<tr><th><label for="hkr-dietary"><?php esc_html_e( 'Dietary / allergy notes', 'hakka-reservations' ); ?></label></th><td><textarea name="dietary" id="hkr-dietary" rows="2" class="large-text"><?php echo esc_textarea( $b['dietary_notes'] ); ?></textarea></td></tr>
							<tr><th><label for="hkr-requests"><?php esc_html_e( 'Special requests', 'hakka-reservations' ); ?></label></th><td><textarea name="requests" id="hkr-requests" rows="2" class="large-text"><?php echo esc_textarea( $b['special_requests'] ); ?></textarea></td></tr>
							<tr><th><label for="hkr-internal"><?php esc_html_e( 'Internal notes', 'hakka-reservations' ); ?></label></th><td><textarea name="internal_notes" id="hkr-internal" rows="3" class="large-text"><?php echo esc_textarea( $b['internal_notes'] ); ?></textarea><p class="description"><?php esc_html_e( 'Only staff see these.', 'hakka-reservations' ); ?></p></td></tr>
						</table>
					</div>
				</div>
				<div class="hkr-panel">
					<label><input type="checkbox" name="notify" value="1" <?php checked( ! $booking || 'pending' === $b['status'] ); ?>> <?php esc_html_e( 'Email the guest about this (confirmation, change or cancellation)', 'hakka-reservations' ); ?></label>
					<p><label for="hkr-message"><?php esc_html_e( 'Message to include in the guest\'s email (optional)', 'hakka-reservations' ); ?></label><br>
					<textarea name="message" id="hkr-message" rows="2" class="large-text"></textarea></p>
					<?php submit_button( $booking ? __( 'Save booking', 'hakka-reservations' ) : __( 'Add booking', 'hakka-reservations' ) ); ?>
				</div>
			</form>
			<?php if ( $booking ) : ?>
				<?php self::history( $booking ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function history( $booking ) {
		global $wpdb;
		$log  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'booking_log' ) . ' WHERE booking_id = %d ORDER BY id DESC LIMIT 100', $booking['id'] ) );
		$mail = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'message_log' ) . ' WHERE booking_id = %d ORDER BY id DESC LIMIT 50', $booking['id'] ) );
		$types = HKR_Settings::template_types();
		?>
		<div class="hkr-columns">
			<div class="hkr-panel">
				<h2><?php esc_html_e( 'History', 'hakka-reservations' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'When', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Who', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'What', 'hakka-reservations' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( (array) $log as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $row->actor ); ?></td>
							<td><strong><?php echo esc_html( $row->action ); ?></strong> <?php echo esc_html( trim( $row->old_value . ( '' !== $row->old_value ? ' → ' : '' ) . $row->new_value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="hkr-panel">
				<h2><?php esc_html_e( 'Emails sent', 'hakka-reservations' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'When', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Email', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'To', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Result', 'hakka-reservations' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( (array) $mail as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( isset( $types[ $row->template ] ) ? $types[ $row->template ]['label'] : $row->template ); ?></td>
							<td><?php echo esc_html( $row->recipient ); ?></td>
							<td><?php echo esc_html( $row->status . ( $row->error ? ' – ' . $row->error : '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public static function save() {
		HKR_Admin::check( 'hkr_edit_bookings', 'hkr_save_booking' );
		// phpcs:disable WordPress.Security.NonceVerification -- checked above.
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$fields = array(
			'location_id'         => isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0,
			'area_id'             => isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0,
			'date'                => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'time'                => isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '',
			'party'               => isset( $_POST['party'] ) ? absint( $_POST['party'] ) : 0,
			'name'                => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in clean_guest_fields().
			'phone'               => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'email'               => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'celebration'         => isset( $_POST['celebration'] ) ? wp_unslash( $_POST['celebration'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'celebration_text'    => isset( $_POST['celebration_text'] ) ? wp_unslash( $_POST['celebration_text'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'celebration_details' => isset( $_POST['celebration_details'] ) ? wp_unslash( $_POST['celebration_details'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'seating'             => isset( $_POST['seating'] ) ? wp_unslash( $_POST['seating'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'high_chair'          => ! empty( $_POST['high_chair'] ) ? 1 : 0,
			'dietary'             => isset( $_POST['dietary'] ) ? wp_unslash( $_POST['dietary'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'requests'            => isset( $_POST['requests'] ) ? wp_unslash( $_POST['requests'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'internal_notes'      => isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) : '',
		);
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'confirmed';
		$source  = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'phone';
		$notify  = ! empty( $_POST['notify'] );
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		// phpcs:enable

		if ( ! hkr_user_can_location( $fields['location_id'] ) ) {
			wp_die( esc_html__( 'You cannot manage bookings for this location.', 'hakka-reservations' ), 403 );
		}

		if ( ! $id ) {
			$booking = HKR_Bookings::create(
				$fields,
				array(
					'staff'          => true,
					'source'         => $source,
					'status'         => $status,
					'notify_guest'   => $notify,
					'notify_staff'   => false,
					'internal_notes' => $fields['internal_notes'],
				)
			);
			if ( is_wp_error( $booking ) ) {
				HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-booking-edit' ), $booking->get_error_message(), 'error' );
			}
			HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-booking-edit&id=' . $booking['id'] ), sprintf( /* translators: %s: reference */ __( 'Booking %s added.', 'hakka-reservations' ), $booking['ref'] ) );
		}

		$existing = HKR_Bookings::get( $id );
		if ( ! $existing || ! hkr_user_can_location( $existing['location_id'] ) ) {
			wp_die( esc_html__( 'Booking not found.', 'hakka-reservations' ), 404 );
		}
		unset( $fields['location_id'] );
		$status_changes = $status !== $existing['status'];
		$updated        = HKR_Bookings::update( $id, $fields, array( 'notify' => $notify && ! $status_changes, 'message' => $message ) );
		if ( is_wp_error( $updated ) ) {
			HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-booking-edit&id=' . $id ), $updated->get_error_message(), 'error' );
		}
		if ( $status_changes ) {
			$result = HKR_Bookings::set_status( $id, $status, array( 'notify' => $notify, 'message' => $message ) );
			if ( is_wp_error( $result ) ) {
				HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-booking-edit&id=' . $id ), $result->get_error_message(), 'error' );
			}
		}
		HKR_Admin::redirect( admin_url( 'admin.php?page=hkr-booking-edit&id=' . $id ), __( 'Booking saved.', 'hakka-reservations' ) );
	}

	public static function export() {
		if ( ! current_user_can( 'hkr_edit_bookings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'hakka-reservations' ), 403 );
		}
		check_admin_referer( 'hkr_export' );
		$f             = HKR_Bookings_Table::filters_from_request();
		$args          = HKR_Bookings_Table::query_args( $f );
		$args['limit'] = -1;
		$result        = HKR_Bookings::query( $args );
		$locs          = array();
		foreach ( HKR_Locations::all() as $loc ) {
			$locs[ $loc['id'] ] = $loc['name'];
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=hakka-bookings-' . hkr_now()->format( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Reference', 'Location', 'Date', 'Time', 'End', 'Guests', 'Booking type', 'Status', 'Source', 'Name', 'Phone', 'Email', 'Celebration', 'Celebration details', 'Seating', 'High chair', 'Dietary / allergies', 'Special requests', 'Internal notes', 'Created' ) );
		foreach ( $result['items'] as $b ) {
			$row = array( $b['ref'], isset( $locs[ $b['location_id'] ] ) ? $locs[ $b['location_id'] ] : '', $b['date'], $b['time'], $b['end_time'], $b['party_size'], $b['area_name'], hkr_status_label( $b['status'] ), $b['source'], $b['guest_name'], hkr_display_phone( $b['guest_phone'] ), $b['guest_email'], HKR_Mailer::celebration_label( $b ), $b['celebration_details'], $b['seating_pref'], $b['high_chair'] ? 'Yes' : '', $b['dietary_notes'], $b['special_requests'], $b['internal_notes'], $b['created_at'] );
			// Stop spreadsheet apps treating guest text as formulas.
			$row = array_map(
				static function ( $v ) {
					$v = (string) $v;
					return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! preg_match( '/^[+-]?\d/', $v ) ) ? "'" . $v : $v;
				},
				$row
			);
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
