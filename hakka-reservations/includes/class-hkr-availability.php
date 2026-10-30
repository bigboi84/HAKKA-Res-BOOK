<?php
/**
 * Which dates and time slots can be booked.
 *
 * Rules:
 *  - Opening hours per weekday, replaced by a special date when one covers the day.
 *  - Slots start every "step" minutes from opening time. The last slot starts
 *    "last_before_close" minutes before closing (60 = no bookings in the final hour).
 *  - A slot is offered only after the minimum notice and within the booking window.
 *  - Guests in overlapping bookings are added up per booking type (if the type has
 *    a capacity) and for the whole location (if the location has a covers limit).
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Availability {

	/**
	 * Opening ranges for a date, from a special date if one applies, otherwise the weekday.
	 *
	 * @return array{ranges: array, note: string, special: bool}
	 */
	public static function ranges_for_date( $loc, $ymd ) {
		foreach ( (array) $loc['special_dates'] as $sd ) {
			$from = isset( $sd['from'] ) ? $sd['from'] : '';
			$to   = ! empty( $sd['to'] ) ? $sd['to'] : $from;
			if ( $from && $ymd >= $from && $ymd <= $to ) {
				$closed = ! empty( $sd['closed'] );
				return array(
					'ranges'  => $closed ? array() : HKR_Locations::normalize_ranges( isset( $sd['ranges'] ) ? $sd['ranges'] : array() ),
					'note'    => isset( $sd['note'] ) ? (string) $sd['note'] : '',
					'special' => true,
				);
			}
		}
		$weekday = (int) self::date( $ymd )->format( 'N' );
		return array(
			'ranges'  => $loc['hours'][ $weekday ],
			'note'    => '',
			'special' => false,
		);
	}

	/**
	 * @return DateTimeImmutable Midnight of a Y-m-d date in the restaurant timezone.
	 */
	public static function date( $ymd ) {
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, hkr_tz() );
		return $d ? $d : hkr_now()->setTime( 0, 0 );
	}

	public static function valid_date( $ymd ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return false;
		}
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, hkr_tz() );
		return $d && $d->format( 'Y-m-d' ) === $ymd;
	}

	/**
	 * Slot start times for a date, ignoring capacity and notice. Each item: [start "H:i", end "H:i"].
	 */
	public static function slot_times( $loc, $ymd ) {
		$day      = self::ranges_for_date( $loc, $ymd );
		$weekday  = (int) self::date( $ymd )->format( 'N' );
		$settings = HKR_Locations::settings_for_day( $loc, $weekday );
		$length   = max( 15, (int) $settings['slot_length'] );
		$step     = max( 5, (int) ( $settings['step'] ? $settings['step'] : $length ) );
		$cutoff   = max( 0, (int) $settings['last_before_close'] );

		$slots = array();
		foreach ( $day['ranges'] as $range ) {
			$open  = hkr_minutes( $range[0] );
			$close = hkr_minutes( $range[1] );
			$last  = $close - $cutoff;
			for ( $t = $open; $t <= $last; $t += $step ) {
				$slots[ hkr_hm( $t ) ] = array( hkr_hm( $t ), hkr_hm( $t + $length ) );
			}
		}
		return array_values( $slots );
	}

	/**
	 * Earliest and latest date-times a guest may book online.
	 */
	public static function window( $loc ) {
		$now    = hkr_now();
		$notice = max( 0, (float) $loc['settings']['notice_hours'] );
		$days   = max( 1, (int) $loc['settings']['advance_days'] );
		return array(
			'earliest' => $now->modify( '+' . (int) round( $notice * 60 ) . ' minutes' ),
			'last_day' => $now->setTime( 0, 0 )->modify( '+' . $days . ' days' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Guests already booked that overlap [start, end), for the location and per area.
	 *
	 * @return array{location: int, areas: array<int,int>, bookings: int}
	 */
	public static function booked( $location_id, $start, $end, $exclude_booking = 0 ) {
		global $wpdb;
		$released = "'" . implode( "','", array_map( 'esc_sql', hkr_released_statuses() ) ) . "'";
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT area_id, party_size FROM ' . hkr_table( 'bookings' ) . " WHERE location_id = %d AND status NOT IN ($released) AND start_at < %s AND end_at > %s AND id <> %d",
				(int) $location_id,
				$end,
				$start,
				(int) $exclude_booking
			)
		);
		$out = array(
			'location' => 0,
			'areas'    => array(),
			'bookings' => 0,
		);
		foreach ( (array) $rows as $r ) {
			$out['location'] += (int) $r->party_size;
			$out['bookings']++;
			$aid = (int) $r->area_id;
			$out['areas'][ $aid ] = ( isset( $out['areas'][ $aid ] ) ? $out['areas'][ $aid ] : 0 ) + (int) $r->party_size;
		}
		return $out;
	}

	/**
	 * Seats left for a slot, or null when nothing limits it.
	 *
	 * @param array      $loc      Location.
	 * @param array|null $area     Booking type, or null.
	 * @param string     $ymd      Date.
	 * @param string     $start    "H:i".
	 * @param string     $end      "H:i" (may pass midnight, e.g. "25:00").
	 * @param bool       $staff    Staff may fill up to a type's hard maximum.
	 * @param int        $exclude  Booking to leave out (when editing it).
	 * @return array{left: int|null, full: bool, booked_area: int, booked_location: int, capacity_area: int|null, capacity_location: int|null}
	 */
	public static function remaining( $loc, $area, $ymd, $start, $end, $staff = false, $exclude = 0 ) {
		list( $start_dt, $end_dt ) = self::datetimes( $ymd, $start, $end );
		$booked   = self::booked( $loc['id'], $start_dt, $end_dt, $exclude );
		$weekday  = (int) self::date( $ymd )->format( 'N' );
		$settings = HKR_Locations::settings_for_day( $loc, $weekday );

		$loc_cap  = '' === $settings['max_covers'] || null === $settings['max_covers'] ? null : (int) $settings['max_covers'];
		$area_cap = null;
		if ( $area ) {
			$area_cap = $area['capacity'];
			if ( $staff && null !== $area['hard_max'] ) {
				$area_cap = max( (int) $area_cap, (int) $area['hard_max'] );
			}
		}
		$booked_area = $area && isset( $booked['areas'][ $area['id'] ] ) ? $booked['areas'][ $area['id'] ] : 0;

		$left = null;
		if ( null !== $loc_cap ) {
			$left = $loc_cap - $booked['location'];
		}
		if ( null !== $area_cap ) {
			$area_left = $area_cap - $booked_area;
			$left      = null === $left ? $area_left : min( $left, $area_left );
		}
		$max_bookings = '' === $settings['max_bookings'] || null === $settings['max_bookings'] ? null : (int) $settings['max_bookings'];
		$full_by_count = null !== $max_bookings && $booked['bookings'] >= $max_bookings;

		return array(
			'left'              => null === $left ? null : max( 0, $left ),
			'full'              => $full_by_count || ( null !== $left && $left <= 0 ),
			'booked_area'       => $booked_area,
			'booked_location'   => $booked['location'],
			'capacity_area'     => $area_cap,
			'capacity_location' => $loc_cap,
		);
	}

	/**
	 * Full date-times for a slot. End times past midnight roll over to the next day.
	 */
	public static function datetimes( $ymd, $start, $end ) {
		$day   = self::date( $ymd );
		$s_min = hkr_minutes( $start );
		$e_min = hkr_minutes( $end );
		if ( $e_min <= $s_min ) {
			$e_min += 1440;
		}
		return array(
			$day->modify( '+' . $s_min . ' minutes' )->format( 'Y-m-d H:i:s' ),
			$day->modify( '+' . $e_min . ' minutes' )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Party-size limits for a location (and day, and type).
	 */
	public static function party_limits( $loc, $area, $ymd = null ) {
		$weekday  = $ymd ? (int) self::date( $ymd )->format( 'N' ) : 0;
		$settings = $weekday ? HKR_Locations::settings_for_day( $loc, $weekday ) : $loc['settings'];
		$min      = max( 1, (int) $settings['min_party'] );
		$max      = max( $min, (int) $settings['max_party'] );
		if ( $area ) {
			$min = max( $min, (int) $area['min_party'] );
			if ( null !== $area['max_party'] ) {
				$max = (int) $area['max_party'];
			}
			if ( null !== $area['capacity'] ) {
				$max = min( $max, (int) $area['capacity'] );
			}
		}
		return array( $min, max( $min, $max ) );
	}

	/**
	 * Whether a booking type is offered on a weekday.
	 */
	public static function area_open_on( $area, $ymd ) {
		if ( ! $area || empty( $area['days'] ) ) {
			return true;
		}
		return in_array( (int) self::date( $ymd )->format( 'N' ), $area['days'], true );
	}

	/**
	 * Bookable slots for guests on a date.
	 *
	 * @return array List of [time, end, label, left, available].
	 */
	public static function slots( $loc, $area, $ymd, $party, $staff = false ) {
		if ( ! self::valid_date( $ymd ) || ! self::area_open_on( $area, $ymd ) ) {
			return array();
		}
		$window = self::window( $loc );
		if ( ! $staff && $ymd > $window['last_day'] ) {
			return array();
		}
		$out = array();
		foreach ( self::slot_times( $loc, $ymd ) as $slot ) {
			list( $start_dt ) = self::datetimes( $ymd, $slot[0], $slot[1] );
			$start_obj        = new DateTimeImmutable( $start_dt, hkr_tz() );
			if ( ! $staff && $start_obj < $window['earliest'] ) {
				continue;
			}
			$rem     = self::remaining( $loc, $area, $ymd, $slot[0], $slot[1], $staff );
			$fits    = ! $rem['full'] && ( null === $rem['left'] || $rem['left'] >= (int) $party );
			$blocked = (bool) HKR_Blocks::find( $loc['id'], $area ? $area['id'] : 0, $ymd, $slot[0] );
			$out[]   = array(
				'time'      => $slot[0],
				'end'       => $slot[1],
				'label'     => hkr_format_time( $slot[0] ),
				// Guests just see a blocked time as full.
				'left'      => $blocked && ! $staff ? 0 : $rem['left'],
				'available' => $fits && ( $staff || ! $blocked ),
				'blocked'   => $staff ? $blocked : null,
			);
		}
		return $out;
	}

	/**
	 * Dates in the booking window with whether the location is open, for the date picker.
	 */
	public static function calendar( $loc, $area = null ) {
		$window = self::window( $loc );
		$day    = hkr_now()->setTime( 0, 0 );
		$out    = array();
		HKR_Blocks::prime( $loc['id'], $day->format( 'Y-m-d' ), $window['last_day'] );
		while ( $day->format( 'Y-m-d' ) <= $window['last_day'] ) {
			$ymd   = $day->format( 'Y-m-d' );
			$open    = false;
			$area_id = $area ? $area['id'] : 0;
			if ( self::area_open_on( $area, $ymd ) && ! HKR_Blocks::find( $loc['id'], $area_id, $ymd ) ) {
				// Open if at least one slot is not blocked by staff and (today) is still ahead of the minimum notice.
				foreach ( self::slot_times( $loc, $ymd ) as $slot ) {
					if ( HKR_Blocks::find( $loc['id'], $area_id, $ymd, $slot[0] ) ) {
						continue;
					}
					list( $start_dt ) = self::datetimes( $ymd, $slot[0], $slot[1] );
					if ( new DateTimeImmutable( $start_dt, hkr_tz() ) >= $window['earliest'] ) {
						$open = true;
						break;
					}
				}
			}
			$out[ $ymd ] = $open;
			$day         = $day->modify( '+1 day' );
		}
		return $out;
	}

	/**
	 * Check a requested booking against every rule.
	 * Returns array{slot, remaining} when it fits, or a WP_Error explaining why not.
	 *
	 * @param array $args location, area (or null), date, time, party, staff (bool), exclude (booking id).
	 */
	public static function check( array $args ) {
		$loc   = $args['location'];
		$area  = isset( $args['area'] ) ? $args['area'] : null;
		$ymd   = $args['date'];
		$time  = $args['time'];
		$party = (int) $args['party'];
		$staff = ! empty( $args['staff'] );

		if ( ! self::valid_date( $ymd ) ) {
			return new WP_Error( 'hkr_date', __( 'Please choose a valid date.', 'hakka-reservations' ) );
		}
		if ( ! $staff && ( ! $loc['active'] || ! $loc['accepts_bookings'] ) ) {
			return new WP_Error( 'hkr_location', __( 'This location is not taking online bookings right now.', 'hakka-reservations' ) );
		}
		if ( ! self::area_open_on( $area, $ymd ) ) {
			return new WP_Error( 'hkr_area_day', __( 'That booking type is not available on this day.', 'hakka-reservations' ) );
		}
		list( $min, $max ) = self::party_limits( $loc, $area, $ymd );
		if ( $party < 1 || ( ! $staff && ( $party < $min || $party > $max ) ) ) {
			/* translators: 1: minimum guests, 2: maximum guests, 3: phone number */
			return new WP_Error( 'hkr_party', sprintf( __( 'Online bookings are for %1$d to %2$d guests. For other group sizes please call us on %3$s.', 'hakka-reservations' ), $min, $max, hkr_display_phone( $loc['phone'] ) ) );
		}
		$slot = null;
		foreach ( self::slot_times( $loc, $ymd ) as $s ) {
			if ( $s[0] === $time ) {
				$slot = $s;
				break;
			}
		}
		if ( ! $slot ) {
			return new WP_Error( 'hkr_time', __( 'That time is not available. Please choose another time.', 'hakka-reservations' ) );
		}
		if ( ! $staff ) {
			$window           = self::window( $loc );
			list( $start_dt ) = self::datetimes( $ymd, $slot[0], $slot[1] );
			if ( new DateTimeImmutable( $start_dt, hkr_tz() ) < $window['earliest'] || $ymd > $window['last_day'] ) {
				return new WP_Error( 'hkr_window', __( 'That time can no longer be booked online. Please choose another time or call us.', 'hakka-reservations' ) );
			}
		}
		if ( ! $staff && HKR_Blocks::find( $loc['id'], $area ? $area['id'] : 0, $ymd, $slot[0] ) ) {
			return new WP_Error( 'hkr_full', __( 'Sorry, that time is fully booked online. Please choose another time or call us.', 'hakka-reservations' ) );
		}
		$rem = self::remaining( $loc, $area, $ymd, $slot[0], $slot[1], $staff, isset( $args['exclude'] ) ? (int) $args['exclude'] : 0 );
		if ( $rem['full'] || ( null !== $rem['left'] && $rem['left'] < $party ) ) {
			return new WP_Error( 'hkr_full', __( 'Sorry, that time has just filled up. Please choose another time.', 'hakka-reservations' ), $rem );
		}
		return array(
			'slot'      => $slot,
			'remaining' => $rem,
		);
	}
}
