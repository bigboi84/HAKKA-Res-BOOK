<?php
/**
 * Creating, reading and changing bookings.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Bookings {

	public static function get( $id ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'bookings' ) . ' WHERE id = %d', (int) $id ) ) );
	}

	public static function get_by_ref( $ref ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'bookings' ) . ' WHERE ref = %s', strtoupper( sanitize_text_field( $ref ) ) ) ) );
	}

	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$b                = (array) $row;
		$b['id']          = (int) $b['id'];
		$b['location_id'] = (int) $b['location_id'];
		$b['area_id']     = (int) $b['area_id'];
		$b['party_size']  = (int) $b['party_size'];
		$b['high_chair']  = (int) $b['high_chair'];
		$b['date']        = substr( $b['start_at'], 0, 10 );
		$b['time']        = substr( $b['start_at'], 11, 5 );
		$b['end_time']    = substr( $b['end_at'], 11, 5 );
		return $b;
	}

	/**
	 * Find bookings.
	 *
	 * @param array $args location_ids (array|null), status (string|array), date_from, date_to (Y-m-d), search, orderby, order,
	 *                    limit (0 = count only, -1 = no limit), offset, since_id.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'location_ids' => null,
				'status'       => '',
				'date_from'    => '',
				'date_to'      => '',
				'search'       => '',
				'orderby'      => 'start_at',
				'order'        => 'ASC',
				'limit'        => 50,
				'offset'       => 0,
				'since_id'     => 0,
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( is_array( $args['location_ids'] ) ) {
			if ( ! $args['location_ids'] ) {
				return array( 'items' => array(), 'total' => 0 );
			}
			$where[] = 'location_id IN (' . implode( ',', array_map( 'intval', $args['location_ids'] ) ) . ')';
		}
		if ( $args['status'] ) {
			$statuses = array_intersect( (array) $args['status'], array_keys( hkr_statuses() ) );
			if ( $statuses ) {
				$where[] = "status IN ('" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "')";
			}
		}
		if ( $args['date_from'] ) {
			$where[]  = 'start_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[]  = 'start_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		if ( $args['since_id'] ) {
			$where[]  = 'id > %d';
			$params[] = (int) $args['since_id'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(guest_name LIKE %s OR guest_phone LIKE %s OR guest_email LIKE %s OR ref LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$allowed = array( 'start_at', 'created_at', 'guest_name', 'party_size', 'status', 'ref', 'id' );
		$orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'start_at';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$sql_w   = implode( ' AND ', $where );
		$table   = hkr_table( 'bookings' );

		$count_sql = "SELECT COUNT(*) FROM $table WHERE $sql_w";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		if ( 0 === (int) $args['limit'] ) {
			return array( 'items' => array(), 'total' => $total ); // Count only.
		}

		$sql = "SELECT * FROM $table WHERE $sql_w ORDER BY $orderby $order, id ASC";
		if ( $args['limit'] > 0 ) {
			$sql .= ' LIMIT ' . (int) $args['limit'] . ' OFFSET ' . (int) $args['offset'];
		}
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Clean the guest-entered fields of a booking request.
	 *
	 * @return array|WP_Error
	 */
	public static function clean_guest_fields( array $in, $require_all = true ) {
		$out = array(
			'guest_name'          => isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '',
			'guest_phone'         => isset( $in['phone'] ) ? hkr_normalize_phone( $in['phone'] ) : '',
			'guest_email'         => isset( $in['email'] ) ? sanitize_email( $in['email'] ) : '',
			'celebration_type'    => '',
			'celebration_text'    => '',
			'celebration_details' => isset( $in['celebration_details'] ) ? sanitize_textarea_field( $in['celebration_details'] ) : '',
			'seating_pref'        => isset( $in['seating'] ) ? sanitize_text_field( $in['seating'] ) : '',
			'high_chair'          => ! empty( $in['high_chair'] ) ? 1 : 0,
			'dietary_notes'       => isset( $in['dietary'] ) ? sanitize_textarea_field( $in['dietary'] ) : '',
			'special_requests'    => isset( $in['requests'] ) ? sanitize_textarea_field( $in['requests'] ) : '',
		);
		$celebration = isset( $in['celebration'] ) ? sanitize_text_field( $in['celebration'] ) : '';
		if ( '' !== $celebration && 'none' !== strtolower( $celebration ) ) {
			if ( 'other' === strtolower( $celebration ) ) {
				$out['celebration_type'] = 'Other';
				$out['celebration_text'] = isset( $in['celebration_text'] ) ? mb_substr( sanitize_text_field( $in['celebration_text'] ), 0, 120 ) : '';
			} elseif ( in_array( $celebration, HKR_Settings::celebration_options(), true ) ) {
				$out['celebration_type'] = $celebration;
			}
		}
		if ( $out['seating_pref'] && ! in_array( $out['seating_pref'], HKR_Settings::seating_options(), true ) ) {
			$out['seating_pref'] = '';
		}
		foreach ( array( 'guest_name', 'celebration_details', 'dietary_notes', 'special_requests' ) as $k ) {
			$out[ $k ] = mb_substr( $out[ $k ], 0, 'guest_name' === $k ? 120 : 1000 );
		}

		if ( $require_all ) {
			if ( mb_strlen( $out['guest_name'] ) < 2 ) {
				return new WP_Error( 'hkr_name', __( 'Please enter your name.', 'hakka-reservations' ) );
			}
			if ( strlen( preg_replace( '/\D+/', '', $out['guest_phone'] ) ) < 7 ) {
				return new WP_Error( 'hkr_phone', __( 'Please enter a phone number we can call to confirm your booking.', 'hakka-reservations' ) );
			}
			if ( ! is_email( $out['guest_email'] ) ) {
				return new WP_Error( 'hkr_email', __( 'Please enter a valid email address.', 'hakka-reservations' ) );
			}
			if ( 'Other' === $out['celebration_type'] && '' === $out['celebration_text'] ) {
				return new WP_Error( 'hkr_celebration', __( 'Please tell us what you are celebrating.', 'hakka-reservations' ) );
			}
			foreach ( array( 'field_dietary' => 'dietary_notes', 'field_requests' => 'special_requests' ) as $setting => $field ) {
				if ( 'required' === HKR_Settings::get( $setting ) && '' === trim( $out[ $field ] ) ) {
					return new WP_Error( 'hkr_' . $field, __( 'Please fill in all required fields.', 'hakka-reservations' ) );
				}
			}
			if ( 'required' === HKR_Settings::get( 'field_seating' ) && '' === $out['seating_pref'] ) {
				return new WP_Error( 'hkr_seating', __( 'Please choose a seating preference.', 'hakka-reservations' ) );
			}
		} elseif ( $out['guest_email'] && ! is_email( $out['guest_email'] ) ) {
			$out['guest_email'] = '';
		}
		return $out;
	}

	/**
	 * Create a booking.
	 *
	 * @param array $req  location_id, area_id, date, time, party, plus guest fields (see clean_guest_fields).
	 * @param array $opts staff (bool: staff rules), source, status, notify_guest (bool), notify_staff (bool), internal_notes.
	 * @return array|WP_Error The booking.
	 */
	public static function create( array $req, array $opts = array() ) {
		global $wpdb;
		$opts = wp_parse_args(
			$opts,
			array(
				'staff'          => false,
				'source'         => 'web',
				'status'         => 'pending',
				'notify_guest'   => true,
				'notify_staff'   => true,
				'internal_notes' => '',
			)
		);

		$loc = HKR_Locations::get( isset( $req['location_id'] ) ? (int) $req['location_id'] : 0 );
		if ( ! $loc ) {
			return new WP_Error( 'hkr_location', __( 'Please choose a location.', 'hakka-reservations' ) );
		}
		$areas = HKR_Locations::areas( $loc['id'] );
		$area  = null;
		if ( ! empty( $req['area_id'] ) ) {
			$area = HKR_Locations::area( (int) $req['area_id'] );
			if ( ! $area || $area['location_id'] !== $loc['id'] || ( ! $area['active'] && ! $opts['staff'] ) ) {
				return new WP_Error( 'hkr_area', __( 'Please choose a booking type.', 'hakka-reservations' ) );
			}
		} elseif ( $areas && ! $opts['staff'] ) {
			if ( 1 === count( $areas ) ) {
				$area = $areas[0];
			} else {
				return new WP_Error( 'hkr_area', __( 'Please choose a booking type.', 'hakka-reservations' ) );
			}
		}

		$guest = self::clean_guest_fields( $req, ! $opts['staff'] );
		if ( is_wp_error( $guest ) ) {
			return $guest;
		}
		if ( $opts['staff'] && '' === $guest['guest_name'] ) {
			return new WP_Error( 'hkr_name', __( 'Please enter the guest\'s name.', 'hakka-reservations' ) );
		}

		$date  = isset( $req['date'] ) ? sanitize_text_field( $req['date'] ) : '';
		$time  = isset( $req['time'] ) ? HKR_Locations::clean_time( $req['time'] ) : '';
		$party = isset( $req['party'] ) ? (int) $req['party'] : 0;

		if ( ! self::lock( $loc['id'] ) ) {
			return new WP_Error( 'hkr_busy', __( 'We are busy taking another booking. Please try again in a moment.', 'hakka-reservations' ) );
		}
		$check = HKR_Availability::check(
			array(
				'location' => $loc,
				'area'     => $area,
				'date'     => $date,
				'time'     => $time,
				'party'    => $party,
				'staff'    => $opts['staff'],
			)
		);
		if ( is_wp_error( $check ) ) {
			self::unlock( $loc['id'] );
			return $check;
		}
		list( $start_at, $end_at ) = HKR_Availability::datetimes( $date, $check['slot'][0], $check['slot'][1] );

		$token = wp_generate_password( 32, false, false );
		$now   = hkr_now()->format( 'Y-m-d H:i:s' );
		$status = array_key_exists( $opts['status'], hkr_statuses() ) ? $opts['status'] : 'pending';
		$row   = array_merge(
			$guest,
			array(
				'ref'            => '',
				'location_id'    => $loc['id'],
				'area_id'        => $area ? $area['id'] : 0,
				'area_name'      => $area ? $area['name'] : '',
				'start_at'       => $start_at,
				'end_at'         => $end_at,
				'party_size'     => $party,
				'status'         => $status,
				'source'         => array_key_exists( $opts['source'], hkr_sources() ) ? $opts['source'] : 'web',
				'internal_notes' => sanitize_textarea_field( $opts['internal_notes'] ),
				'manage_token'   => self::hash_token( $token ),
				'created_by'     => get_current_user_id(),
				'ip_hash'        => self::ip_hash(),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		if ( 'confirmed' === $status ) {
			$row['confirmed_at'] = $now;
			$row['confirmed_by'] = get_current_user_id();
		}
		$ok = $wpdb->insert( hkr_table( 'bookings' ), $row );
		$id = (int) $wpdb->insert_id;
		if ( $ok && $id ) {
			$wpdb->update( hkr_table( 'bookings' ), array( 'ref' => self::make_ref( $id ) ), array( 'id' => $id ) );
		}
		self::unlock( $loc['id'] );
		if ( ! $ok || ! $id ) {
			return new WP_Error( 'hkr_db', __( 'Sorry, something went wrong saving your booking. Please try again or call us.', 'hakka-reservations' ) );
		}

		hkr_log( $id, 'created', '', hkr_sources()[ $row['source'] ] . ' · ' . hkr_status_label( $status ) );
		$booking = self::get( $id );

		if ( $opts['notify_guest'] && $booking['guest_email'] ) {
			HKR_Mailer::send( 'confirmed' === $status ? 'guest_confirmed' : 'guest_received', $booking, array( 'token' => $token ) );
		}
		if ( $opts['notify_staff'] ) {
			HKR_Mailer::send( 'staff_new', $booking );
		}
		do_action( 'hkr_booking_created', $booking );
		$booking['token'] = $token;
		return $booking;
	}

	/**
	 * Change a booking's status and email the guest where that makes sense.
	 *
	 * @param array $opts notify (bool), message (text for the guest).
	 * @return array|WP_Error
	 */
	public static function set_status( $id, $status, array $opts = array() ) {
		global $wpdb;
		$opts    = wp_parse_args( $opts, array( 'notify' => true, 'message' => '' ) );
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'hkr_missing', __( 'Booking not found.', 'hakka-reservations' ) );
		}
		if ( ! array_key_exists( $status, hkr_statuses() ) ) {
			return new WP_Error( 'hkr_status', __( 'Unknown status.', 'hakka-reservations' ) );
		}
		if ( $status === $booking['status'] ) {
			return $booking;
		}
		// Re-opening a released booking must still fit.
		if ( in_array( $booking['status'], hkr_released_statuses(), true ) && ! in_array( $status, hkr_released_statuses(), true ) ) {
			$loc   = HKR_Locations::get( $booking['location_id'] );
			$area  = $booking['area_id'] ? HKR_Locations::area( $booking['area_id'] ) : null;
			$rem   = HKR_Availability::remaining( $loc, $area, $booking['date'], $booking['time'], $booking['end_time'], true, $booking['id'] );
			if ( null !== $rem['left'] && $rem['left'] < $booking['party_size'] ) {
				return new WP_Error( 'hkr_full', __( 'That time slot is now full, so this booking cannot be re-opened. Edit the time first.', 'hakka-reservations' ) );
			}
		}
		$now  = hkr_now()->format( 'Y-m-d H:i:s' );
		$data = array(
			'status'     => $status,
			'updated_at' => $now,
		);
		if ( 'confirmed' === $status ) {
			$data['confirmed_at'] = $now;
			$data['confirmed_by'] = get_current_user_id();
		}
		$wpdb->update( hkr_table( 'bookings' ), $data, array( 'id' => $booking['id'] ) );
		hkr_log( $booking['id'], 'status', $booking['status'], $status . ( $opts['message'] ? ' · ' . $opts['message'] : '' ) );
		$updated = self::get( $booking['id'] );

		$guest_templates = array(
			'confirmed' => 'guest_confirmed',
			'declined'  => 'guest_declined',
			'cancelled' => 'guest_cancelled',
		);
		if ( $opts['notify'] && $updated['guest_email'] && isset( $guest_templates[ $status ] ) ) {
			$token = self::refresh_token( $updated['id'] );
			HKR_Mailer::send( $guest_templates[ $status ], $updated, array( 'token' => $token, 'staff_message' => $opts['message'] ) );
		}
		do_action( 'hkr_booking_status_changed', $updated, $booking['status'] );
		return $updated;
	}

	/**
	 * Edit a booking's details (staff). Date, time, party size and type are re-checked.
	 *
	 * @return array|WP_Error
	 */
	public static function update( $id, array $in, array $opts = array() ) {
		global $wpdb;
		$opts    = wp_parse_args( $opts, array( 'notify' => false, 'message' => '' ) );
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'hkr_missing', __( 'Booking not found.', 'hakka-reservations' ) );
		}
		$loc  = HKR_Locations::get( $booking['location_id'] );
		$data = array();

		$date    = isset( $in['date'] ) ? sanitize_text_field( $in['date'] ) : $booking['date'];
		$time    = isset( $in['time'] ) ? HKR_Locations::clean_time( $in['time'] ) : $booking['time'];
		$party   = isset( $in['party'] ) ? (int) $in['party'] : $booking['party_size'];
		$area_id = isset( $in['area_id'] ) ? (int) $in['area_id'] : $booking['area_id'];

		$moved = $date !== $booking['date'] || $time !== $booking['time'] || $party !== $booking['party_size'] || $area_id !== $booking['area_id'];
		if ( $moved ) {
			$area = $area_id ? HKR_Locations::area( $area_id ) : null;
			if ( $area_id && ( ! $area || $area['location_id'] !== $loc['id'] ) ) {
				return new WP_Error( 'hkr_area', __( 'Please choose a booking type for this location.', 'hakka-reservations' ) );
			}
			if ( ! self::lock( $loc['id'] ) ) {
				return new WP_Error( 'hkr_busy', __( 'Busy, please try again.', 'hakka-reservations' ) );
			}
			$check = HKR_Availability::check(
				array(
					'location' => $loc,
					'area'     => $area,
					'date'     => $date,
					'time'     => $time,
					'party'    => $party,
					'staff'    => true,
					'exclude'  => $booking['id'],
				)
			);
			if ( is_wp_error( $check ) && in_array( $booking['status'], hkr_released_statuses(), true ) && 'hkr_full' === $check->get_error_code() ) {
				$check = null; // A cancelled booking holds no seats, so it may be edited freely.
			}
			if ( is_wp_error( $check ) ) {
				self::unlock( $loc['id'] );
				return $check;
			}
			$slot = $check ? $check['slot'] : array( $time, hkr_hm( hkr_minutes( $time ) + (int) $loc['settings']['slot_length'] ) );
			list( $data['start_at'], $data['end_at'] ) = HKR_Availability::datetimes( $date, $slot[0], $slot[1] );
			$data['party_size'] = $party;
			$data['area_id']    = $area ? $area['id'] : 0;
			$data['area_name']  = $area ? $area['name'] : '';
		}

		if ( isset( $in['name'] ) || isset( $in['phone'] ) || isset( $in['email'] ) ) {
			$guest = self::clean_guest_fields(
				array_merge(
					array(
						'name'                => $booking['guest_name'],
						'phone'               => $booking['guest_phone'],
						'email'               => $booking['guest_email'],
						'celebration'         => $booking['celebration_type'],
						'celebration_text'    => $booking['celebration_text'],
						'celebration_details' => $booking['celebration_details'],
						'seating'             => $booking['seating_pref'],
						'high_chair'          => $booking['high_chair'],
						'dietary'             => $booking['dietary_notes'],
						'requests'            => $booking['special_requests'],
					),
					$in
				),
				false
			);
			$data = array_merge( $data, $guest );
		}
		if ( isset( $in['internal_notes'] ) ) {
			$data['internal_notes'] = sanitize_textarea_field( $in['internal_notes'] );
		}

		$changes = array();
		foreach ( $data as $key => $value ) {
			if ( (string) $booking[ $key ] !== (string) $value ) {
				$changes[ $key ] = array( $booking[ $key ], $value );
			}
		}
		if ( ! $changes ) {
			if ( $moved ) {
				self::unlock( $loc['id'] );
			}
			return $booking;
		}
		$data['updated_at'] = hkr_now()->format( 'Y-m-d H:i:s' );
		if ( isset( $changes['start_at'] ) ) {
			$data['reminder_sent_at'] = null;
		}
		$wpdb->update( hkr_table( 'bookings' ), $data, array( 'id' => $booking['id'] ) );
		if ( $moved ) {
			self::unlock( $loc['id'] );
		}
		foreach ( $changes as $key => $pair ) {
			hkr_log( $booking['id'], 'edit:' . $key, $pair[0], $pair[1] );
		}
		$updated = self::get( $booking['id'] );
		if ( $opts['notify'] && $updated['guest_email'] && array_intersect( array_keys( $changes ), array( 'start_at', 'party_size', 'area_id' ) ) ) {
			$token = self::refresh_token( $updated['id'] );
			HKR_Mailer::send( 'guest_changed', $updated, array( 'token' => $token, 'staff_message' => $opts['message'] ) );
		}
		return $updated;
	}

	/**
	 * Add a line to the internal notes, stamped with who wrote it.
	 */
	public static function add_note( $id, $note ) {
		global $wpdb;
		$booking = self::get( $id );
		$note    = trim( sanitize_textarea_field( $note ) );
		if ( ! $booking || '' === $note ) {
			return $booking;
		}
		$user  = wp_get_current_user();
		$stamp = hkr_now()->format( 'j M g:ia' ) . ' · ' . ( $user->exists() ? $user->display_name : 'Staff' );
		$notes = trim( $booking['internal_notes'] . "\n[" . $stamp . '] ' . $note );
		$wpdb->update( hkr_table( 'bookings' ), array( 'internal_notes' => $notes, 'updated_at' => hkr_now()->format( 'Y-m-d H:i:s' ) ), array( 'id' => $booking['id'] ) );
		hkr_log( $booking['id'], 'note', '', $note );
		return self::get( $booking['id'] );
	}

	/**
	 * Whether a guest may still cancel online.
	 */
	public static function guest_can_cancel( $booking ) {
		if ( ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
			return false;
		}
		$loc    = HKR_Locations::get( $booking['location_id'] );
		$cutoff = $loc ? (float) $loc['settings']['cancel_cutoff'] : 2;
		$start  = new DateTimeImmutable( $booking['start_at'], hkr_tz() );
		return hkr_now()->modify( '+' . (int) round( $cutoff * 60 ) . ' minutes' ) <= $start;
	}

	public static function guest_cancel( $booking ) {
		$updated = self::set_status( $booking['id'], 'cancelled', array( 'notify' => true ) );
		if ( ! is_wp_error( $updated ) ) {
			HKR_Mailer::send( 'staff_changed', $updated, array( 'change_summary' => __( 'The guest cancelled this booking online.', 'hakka-reservations' ) ) );
		}
		return $updated;
	}

	public static function guest_request_change( $booking, $message ) {
		$message = mb_substr( sanitize_textarea_field( $message ), 0, 1000 );
		hkr_log( $booking['id'], 'change_request', '', $message );
		HKR_Mailer::send(
			'staff_changed',
			$booking,
			array(
				'change_summary' => __( 'The guest asked to change this booking. Please call them.', 'hakka-reservations' ),
				'guest_message'  => $message,
			)
		);
	}

	/* ----- References, tokens and locking ----- */

	public static function make_ref( $id ) {
		return 'HK-' . ( 1000 + (int) $id );
	}

	public static function hash_token( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	public static function verify_token( $booking, $token ) {
		return $booking && $token && hash_equals( (string) $booking['manage_token'], self::hash_token( $token ) );
	}

	/**
	 * New manage-link token (the old link stops working). Used when emailing the guest again.
	 */
	public static function refresh_token( $id ) {
		global $wpdb;
		$token = wp_generate_password( 32, false, false );
		$wpdb->update( hkr_table( 'bookings' ), array( 'manage_token' => self::hash_token( $token ) ), array( 'id' => (int) $id ) );
		return $token;
	}

	public static function manage_url( $booking, $token ) {
		$page = (int) HKR_Settings::get( 'manage_page_id' );
		$base = $page ? get_permalink( $page ) : home_url( '/' );
		return add_query_arg(
			array(
				'hkr_ref'   => rawurlencode( $booking['ref'] ),
				'hkr_token' => rawurlencode( $token ),
			),
			$base
		);
	}

	private static function ip_hash() {
		$ip = hkr_client_ip();
		return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ) : '';
	}

	/** @var array<int,string> How each held lock was taken: 'mysql' or 'row'. */
	private static $locks = array();

	/**
	 * A short lock per location so two guests can't take the last seats at the same moment.
	 * MySQL/MariaDB: a named database lock. Other databases: a row in the options table,
	 * inserted with INSERT IGNORE so only one request can create it.
	 */
	private static function lock( $location_id ) {
		global $wpdb;
		$location_id = (int) $location_id;
		$name        = $wpdb->prefix . 'hkr_lock_' . $location_id;

		$suppress = $wpdb->suppress_errors( true );
		$got      = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) );
		$wpdb->suppress_errors( $suppress );
		if ( '1' === (string) $got ) {
			self::$locks[ $location_id ] = 'mysql';
			return true;
		}
		if ( '0' === (string) $got ) {
			return false; // Timed out waiting for another booking.
		}

		$key = 'hkr_lock_' . $location_id;
		for ( $tries = 0; $tries < 50; $tries++ ) {
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, (string) time() ) );
			if ( 1 === (int) $inserted ) {
				self::$locks[ $location_id ] = 'row';
				return true;
			}
			$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			if ( $since && time() - $since > 15 ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, (string) $since ) );
				continue;
			}
			usleep( 100000 );
		}
		return false;
	}

	private static function unlock( $location_id ) {
		global $wpdb;
		$location_id = (int) $location_id;
		$how         = isset( self::$locks[ $location_id ] ) ? self::$locks[ $location_id ] : '';
		unset( self::$locks[ $location_id ] );
		if ( 'mysql' === $how ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . 'hkr_lock_' . $location_id ) );
		} elseif ( 'row' === $how ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'hkr_lock_' . $location_id ) );
		}
	}
}
