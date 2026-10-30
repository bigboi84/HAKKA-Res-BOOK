<?php
/**
 * Locations and their booking types (areas).
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Locations {

	private static $cache = array();

	/**
	 * Slot and capacity defaults for a new location.
	 */
	public static function default_settings() {
		return array(
			'slot_length'       => 60,
			'step'              => 60,
			'last_before_close' => 60,
			'max_covers'        => '',
			'max_bookings'      => '',
			'min_party'         => 1,
			'max_party'         => 20,
			'large_party'       => 10,
			'notice_hours'      => 2,
			'advance_days'      => 60,
			'cancel_cutoff'     => 2,
			'reminder_hours'    => 24,
			'day_overrides'     => array(),
			'hero_frame'        => hkr_default_frame(),
			'hero_mobile_id'    => 0,
		);
	}

	public static function weekdays() {
		return array(
			1 => __( 'Monday', 'hakka-reservations' ),
			2 => __( 'Tuesday', 'hakka-reservations' ),
			3 => __( 'Wednesday', 'hakka-reservations' ),
			4 => __( 'Thursday', 'hakka-reservations' ),
			5 => __( 'Friday', 'hakka-reservations' ),
			6 => __( 'Saturday', 'hakka-reservations' ),
			7 => __( 'Sunday', 'hakka-reservations' ),
		);
	}

	/**
	 * Turn a database row into a location array with decoded JSON.
	 */
	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$loc                     = (array) $row;
		$loc['id']               = (int) $loc['id'];
		$loc['active']           = (int) $loc['active'];
		$loc['accepts_bookings'] = (int) $loc['accepts_bookings'];
		$loc['settings']         = wp_parse_args( hkr_json( $loc['settings'] ), self::default_settings() );
		$loc['settings']['hero_frame'] = hkr_sanitize_frame( $loc['settings']['hero_frame'] );
		$loc['hours']            = self::normalize_hours( hkr_json( $loc['hours'] ) );
		$loc['special_dates']    = hkr_json( $loc['special_dates'] );
		return $loc;
	}

	/**
	 * Make sure every weekday 1-7 exists and every range is a valid [open, close] pair.
	 */
	public static function normalize_hours( $hours ) {
		$out = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$ranges = isset( $hours[ $d ] ) ? $hours[ $d ] : ( isset( $hours[ (string) $d ] ) ? $hours[ (string) $d ] : array() );
			$out[ $d ] = self::normalize_ranges( $ranges );
		}
		return $out;
	}

	/**
	 * Keep valid ranges only, sorted by opening time. Close may be "24:00" (midnight).
	 */
	public static function normalize_ranges( $ranges ) {
		$clean = array();
		foreach ( (array) $ranges as $range ) {
			if ( ! is_array( $range ) || count( $range ) < 2 ) {
				continue;
			}
			$open  = self::clean_time( $range[0] );
			$close = self::clean_time( $range[1], true );
			if ( null === $open || null === $close ) {
				continue;
			}
			if ( '00:00' === $close ) {
				$close = '24:00';
			}
			if ( hkr_minutes( $close ) <= hkr_minutes( $open ) ) {
				continue;
			}
			$clean[] = array( $open, $close );
		}
		usort(
			$clean,
			static function ( $a, $b ) {
				return hkr_minutes( $a[0] ) - hkr_minutes( $b[0] );
			}
		);
		return $clean;
	}

	/**
	 * "9:30" / "09:30" / "21:00" → "HH:MM". Returns null if not a time.
	 */
	public static function clean_time( $value, $allow_midnight = false ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $value, $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ( $allow_midnight && 24 === $h && 0 === $i ) {
			return '24:00';
		}
		if ( $h > 23 || $i > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d', $h, $i );
	}

	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! isset( self::$cache[ $id ] ) ) {
			self::$cache[ $id ] = self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'locations' ) . ' WHERE id = %d', $id ) ) );
		}
		return self::$cache[ $id ];
	}

	public static function get_by_slug( $slug ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'locations' ) . ' WHERE slug = %s', sanitize_title( $slug ) ) );
		return self::hydrate( $row );
	}

	/**
	 * @param bool $bookable Only active locations that accept online bookings.
	 */
	public static function all( $bookable = false ) {
		global $wpdb;
		$where = $bookable ? ' WHERE active = 1 AND accepts_bookings = 1' : '';
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . hkr_table( 'locations' ) . $where . ' ORDER BY sort_order ASC, id ASC' );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Insert or update a location. Returns its ID.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$now  = hkr_now()->format( 'Y-m-d H:i:s' );
		$cols = array( 'name', 'slug', 'active', 'accepts_bookings', 'address', 'phone', 'map_url', 'sort_order', 'header_image_id', 'header_image_alt', 'email_image_id', 'alert_emails', 'from_name', 'from_email', 'reply_to', 'bcc', 'confirm_note', 'whatsapp_phone_id' );
		$row  = array();
		foreach ( $cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$row[ $col ] = $data[ $col ];
			}
		}
		if ( isset( $row['slug'] ) ) {
			$row['slug'] = sanitize_title( $row['slug'] ? $row['slug'] : ( isset( $row['name'] ) ? $row['name'] : '' ) );
		}
		foreach ( array( 'settings', 'hours', 'special_dates' ) as $json_col ) {
			if ( array_key_exists( $json_col, $data ) ) {
				$row[ $json_col ] = wp_json_encode( $data[ $json_col ] );
			}
		}
		$row['updated_at'] = $now;
		if ( $id ) {
			$wpdb->update( hkr_table( 'locations' ), $row, array( 'id' => (int) $id ) );
		} else {
			$row = array_merge(
				array(
					'active'           => 1,
					'accepts_bookings' => 1,
					'settings'         => wp_json_encode( self::default_settings() ),
					'hours'            => wp_json_encode( self::normalize_hours( array() ) ),
					'special_dates'    => '[]',
					'confirm_note'     => __( 'We will call you to confirm your booking.', 'hakka-reservations' ),
				),
				$row
			);
			$row['created_at'] = $now;
			$wpdb->insert( hkr_table( 'locations' ), $row );
			$id = (int) $wpdb->insert_id;
		}
		unset( self::$cache[ (int) $id ] );
		return (int) $id;
	}

	public static function areas( $location_id, $active_only = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . hkr_table( 'areas' ) . ' WHERE location_id = %d' . ( $active_only ? ' AND active = 1' : '' ) . ' ORDER BY sort_order ASC, id ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, (int) $location_id ) );
		return array_map( array( __CLASS__, 'hydrate_area' ), (array) $rows );
	}

	public static function area( $id ) {
		global $wpdb;
		return self::hydrate_area( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'areas' ) . ' WHERE id = %d', (int) $id ) ) );
	}

	private static function hydrate_area( $row ) {
		if ( ! $row ) {
			return null;
		}
		$a                    = (array) $row;
		$a['id']              = (int) $a['id'];
		$a['location_id']     = (int) $a['location_id'];
		$a['capacity']        = null === $a['capacity'] || '' === $a['capacity'] ? null : (int) $a['capacity'];
		$a['hard_max']        = null === $a['hard_max'] || '' === $a['hard_max'] ? null : (int) $a['hard_max'];
		$a['min_party']       = max( 1, (int) $a['min_party'] );
		$a['max_party']       = null === $a['max_party'] || '' === $a['max_party'] ? null : (int) $a['max_party'];
		$a['ask_celebration'] = (int) $a['ask_celebration'];
		$a['active']          = (int) $a['active'];
		$a['days']            = array_values( array_filter( array_map( 'intval', explode( ',', (string) $a['available_days'] ) ) ) );
		$a['image_id']        = isset( $a['image_id'] ) ? (int) $a['image_id'] : 0;
		$a['image_frame']     = hkr_sanitize_frame( isset( $a['image_frame'] ) ? $a['image_frame'] : '' );
		return $a;
	}

	/**
	 * Save the booking types for a location. Rows with an id are updated, rows without one are added.
	 * With $remove_missing, types left out are deleted, or switched off if they already have bookings.
	 */
	public static function save_areas( $location_id, array $rows, $remove_missing = false ) {
		global $wpdb;
		$table = hkr_table( 'areas' );
		$kept  = array();
		foreach ( $rows as $i => $r ) {
			$name = isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$data = array(
				'location_id'     => (int) $location_id,
				'name'            => $name,
				'description'     => isset( $r['description'] ) ? sanitize_text_field( $r['description'] ) : '',
				'capacity'        => self::nullable_int( isset( $r['capacity'] ) ? $r['capacity'] : null ),
				'hard_max'        => self::nullable_int( isset( $r['hard_max'] ) ? $r['hard_max'] : null ),
				'min_party'       => max( 1, isset( $r['min_party'] ) ? (int) $r['min_party'] : 1 ),
				'max_party'       => self::nullable_int( isset( $r['max_party'] ) ? $r['max_party'] : null ),
				'ask_celebration' => isset( $r['ask_celebration'] ) ? ( $r['ask_celebration'] ? 1 : 0 ) : 1,
				'available_days'  => isset( $r['days'] ) ? implode( ',', array_filter( array_map( 'intval', (array) $r['days'] ) ) ) : '',
				'active'          => isset( $r['active'] ) ? ( $r['active'] ? 1 : 0 ) : 1,
				'sort_order'      => isset( $r['sort_order'] ) ? (int) $r['sort_order'] : $i + 1,
			);
			if ( array_key_exists( 'image_id', $r ) ) {
				$data['image_id']    = absint( $r['image_id'] );
				$data['image_frame'] = wp_json_encode( hkr_sanitize_frame( isset( $r['frame'] ) ? $r['frame'] : array() ) );
			}
			$id = isset( $r['id'] ) ? (int) $r['id'] : 0;
			if ( $id ) {
				$wpdb->update( $table, $data, array( 'id' => $id, 'location_id' => (int) $location_id ) );
			} else {
				$wpdb->insert( $table, $data );
				$id = (int) $wpdb->insert_id;
			}
			$kept[] = $id;
		}
		if ( $remove_missing ) {
			foreach ( self::areas( $location_id, false ) as $area ) {
				if ( in_array( $area['id'], $kept, true ) ) {
					continue;
				}
				$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . hkr_table( 'bookings' ) . ' WHERE area_id = %d', $area['id'] ) );
				if ( $used ) {
					$wpdb->update( $table, array( 'active' => 0 ), array( 'id' => $area['id'] ) );
				} else {
					$wpdb->delete( $table, array( 'id' => $area['id'] ) );
				}
			}
		}
		return $kept;
	}

	private static function nullable_int( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return max( 0, (int) $value );
	}

	/**
	 * Settings for a given weekday, with that day's overrides applied.
	 */
	public static function settings_for_day( $loc, $weekday ) {
		$s         = $loc['settings'];
		$overrides = isset( $s['day_overrides'][ $weekday ] ) ? (array) $s['day_overrides'][ $weekday ] : array();
		foreach ( array( 'slot_length', 'last_before_close', 'max_covers', 'max_party' ) as $key ) {
			if ( isset( $overrides[ $key ] ) && '' !== $overrides[ $key ] && null !== $overrides[ $key ] ) {
				$s[ $key ] = $overrides[ $key ];
			}
		}
		return $s;
	}

	/**
	 * The guest-facing sender for a location's emails.
	 */
	public static function sender( $loc ) {
		$from_email = $loc && ! empty( $loc['from_email'] ) ? $loc['from_email'] : HKR_Settings::get( 'from_email' );
		$from_name  = $loc && ! empty( $loc['from_name'] ) ? $loc['from_name'] : HKR_Settings::get( 'from_name' );
		$reply_to   = $loc && ! empty( $loc['reply_to'] ) ? $loc['reply_to'] : ( HKR_Settings::get( 'reply_to' ) ? HKR_Settings::get( 'reply_to' ) : $from_email );
		return array(
			'from_email' => $from_email,
			'from_name'  => $from_name,
			'reply_to'   => $reply_to,
			'bcc'        => $loc && ! empty( $loc['bcc'] ) ? hkr_parse_emails( $loc['bcc'] ) : array(),
		);
	}

	public static function header_image_url( $loc, $size = 'large' ) {
		$id = $loc && ! empty( $loc['header_image_id'] ) ? (int) $loc['header_image_id'] : (int) HKR_Settings::get( 'default_header_id' );
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $id, $size );
		return $src ? $src[0] : '';
	}

	/**
	 * Optional separate header photo for phones. Falls back to the main header photo.
	 */
	public static function mobile_image_url( $loc ) {
		$id = $loc ? (int) $loc['settings']['hero_mobile_id'] : 0;
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			if ( $src ) {
				return $src[0];
			}
		}
		return '';
	}

	public static function email_image_url( $loc ) {
		if ( $loc && ! empty( $loc['email_image_id'] ) ) {
			$src = wp_get_attachment_image_src( (int) $loc['email_image_id'], 'large' );
			if ( $src ) {
				return $src[0];
			}
		}
		return self::header_image_url( $loc, 'large' );
	}

	/**
	 * Opening hours as short lines, e.g. "Mon – Fri: 10:00am – 9:00pm".
	 */
	public static function hours_summary( $loc ) {
		$short = array(
			1 => __( 'Mon', 'hakka-reservations' ),
			2 => __( 'Tue', 'hakka-reservations' ),
			3 => __( 'Wed', 'hakka-reservations' ),
			4 => __( 'Thu', 'hakka-reservations' ),
			5 => __( 'Fri', 'hakka-reservations' ),
			6 => __( 'Sat', 'hakka-reservations' ),
			7 => __( 'Sun', 'hakka-reservations' ),
		);
		$labels = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$ranges = $loc['hours'][ $d ];
			if ( ! $ranges ) {
				$labels[ $d ] = __( 'Closed', 'hakka-reservations' );
				continue;
			}
			$parts = array();
			foreach ( $ranges as $r ) {
				$parts[] = hkr_format_time( $r[0] ) . ' – ' . hkr_format_time( $r[1] );
			}
			$labels[ $d ] = implode( ', ', $parts );
		}
		$lines = array();
		$start = 1;
		for ( $d = 2; $d <= 8; $d++ ) {
			if ( $d <= 7 && $labels[ $d ] === $labels[ $start ] ) {
				continue;
			}
			$end     = $d - 1;
			$days    = $start === $end ? $short[ $start ] : $short[ $start ] . ' – ' . $short[ $end ];
			$lines[] = $days . ': ' . $labels[ $start ];
			$start   = $d;
		}
		return $lines;
	}

	/**
	 * Public data for the booking form.
	 */
	public static function public_data( $loc ) {
		$areas = array();
		foreach ( self::areas( $loc['id'] ) as $a ) {
			$photo   = $a['image_id'] ? wp_get_attachment_image_src( $a['image_id'], 'full' ) : false;
			$thumb   = $a['image_id'] ? wp_get_attachment_image_src( $a['image_id'], 'medium_large' ) : false;
			$areas[] = array(
				'id'             => $a['id'],
				'name'           => $a['name'],
				'description'    => $a['description'],
				'image'          => $photo ? $photo[0] : '',
				'thumb'          => $thumb ? $thumb[0] : ( $photo ? $photo[0] : '' ),
				'imageAlt'       => $a['image_id'] ? (string) get_post_meta( $a['image_id'], '_wp_attachment_image_alt', true ) : '',
				'frame'          => hkr_frame_public( $a['image_frame'] ),
				'minParty'       => $a['min_party'],
				'maxParty'       => $a['max_party'],
				'askCelebration' => (bool) $a['ask_celebration'],
			);
		}
		$s = $loc['settings'];
		return array(
			'id'          => $loc['id'],
			'slug'        => $loc['slug'],
			'name'        => $loc['name'],
			'address'     => (string) $loc['address'],
			'phone'       => hkr_display_phone( $loc['phone'] ),
			'phoneRaw'    => $loc['phone'],
			'mapUrl'      => (string) $loc['map_url'],
			'image'       => self::header_image_url( $loc, 'full' ),
			'imageMobile' => self::mobile_image_url( $loc ),
			'frame'       => hkr_frame_public( $s['hero_frame'] ),
			'imageAlt'    => $loc['header_image_alt'] ? $loc['header_image_alt'] : $loc['name'],
			'hours'       => self::hours_summary( $loc ),
			'minParty'    => max( 1, (int) $s['min_party'] ),
			'maxParty'    => max( 1, (int) $s['max_party'] ),
			'confirmNote' => $loc['confirm_note'] ? $loc['confirm_note'] : __( 'We will call you to confirm your booking.', 'hakka-reservations' ),
			'areas'       => $areas,
		);
	}

	public static function flush_cache() {
		self::$cache = array();
	}
}
