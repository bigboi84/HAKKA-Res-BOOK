<?php
/**
 * Online booking blocks set by staff: a whole day, or single time slots, closed to online bookings
 * (e.g. Mother's Day kept for walk-ins and phone bookings). Staff can still add bookings into blocked times.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Blocks {

	private static $cache = array();

	/**
	 * Blocks for one location and day.
	 */
	public static function for_date( $location_id, $ymd ) {
		global $wpdb;
		$key = (int) $location_id . '|' . $ymd;
		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$cache[ $key ] = array_map(
				array( __CLASS__, 'hydrate' ),
				(array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'blocks' ) . ' WHERE location_id = %d AND block_date = %s ORDER BY time_from ASC', (int) $location_id, $ymd ), ARRAY_A )
			);
		}
		return self::$cache[ $key ];
	}

	/**
	 * Load a location's blocks for a date range in one query (used by the calendar).
	 */
	public static function prime( $location_id, $from, $to ) {
		$by_date = array();
		foreach ( self::between( array( (int) $location_id ), $from, $to ) as $b ) {
			$by_date[ $b['date'] ][] = $b;
		}
		$day = new DateTimeImmutable( $from, hkr_tz() );
		while ( $day->format( 'Y-m-d' ) <= $to ) {
			$ymd = $day->format( 'Y-m-d' );
			self::$cache[ (int) $location_id . '|' . $ymd ] = isset( $by_date[ $ymd ] ) ? $by_date[ $ymd ] : array();
			$day = $day->modify( '+1 day' );
		}
	}

	/**
	 * The block that stops online bookings for this slot, or null. A block with no booking type covers every type.
	 *
	 * @param string $time Slot start, H:i. Empty to ask about the whole day only.
	 */
	public static function find( $location_id, $area_id, $ymd, $time = '' ) {
		foreach ( self::for_date( $location_id, $ymd ) as $b ) {
			if ( $b['area_id'] && (int) $area_id !== $b['area_id'] ) {
				continue;
			}
			if ( $b['all_day'] ) {
				return $b;
			}
			if ( '' !== $time && $time >= $b['time_from'] && $time < $b['time_to'] ) {
				return $b;
			}
		}
		return null;
	}

	/**
	 * Blocks for a set of locations between two dates (staff screen).
	 *
	 * @param int[]|null $location_ids Null means every location.
	 */
	public static function between( $location_ids, $from, $to ) {
		global $wpdb;
		if ( is_array( $location_ids ) && ! $location_ids ) {
			return array();
		}
		$where = $wpdb->prepare( 'block_date BETWEEN %s AND %s', $from, $to );
		if ( is_array( $location_ids ) ) {
			$where .= ' AND location_id IN (' . implode( ',', array_map( 'intval', $location_ids ) ) . ')';
		}
		return array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( 'SELECT * FROM ' . hkr_table( 'blocks' ) . " WHERE $where ORDER BY block_date ASC, time_from ASC, id ASC", ARRAY_A ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . hkr_table( 'blocks' ) . ' WHERE id = %d', (int) $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Add one block. Returns its ID. Skips an exact duplicate.
	 */
	public static function add( $location_id, $area_id, $ymd, $time_from, $time_to, $note, $user_id ) {
		global $wpdb;
		foreach ( self::for_date( $location_id, $ymd ) as $b ) {
			if ( $b['area_id'] === (int) $area_id && $b['time_from'] === $time_from && $b['time_to'] === $time_to ) {
				return $b['id'];
			}
		}
		$wpdb->insert(
			hkr_table( 'blocks' ),
			array(
				'location_id' => (int) $location_id,
				'area_id'     => (int) $area_id,
				'block_date'  => $ymd,
				'time_from'   => $time_from,
				'time_to'     => $time_to,
				'note'        => $note,
				'created_by'  => (int) $user_id,
				'created_at'  => hkr_now()->format( 'Y-m-d H:i:s' ),
			)
		);
		unset( self::$cache[ (int) $location_id . '|' . $ymd ] );
		hkr_purge_page_cache();
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$b = self::get( $id );
		if ( ! $b ) {
			return false;
		}
		$wpdb->delete( hkr_table( 'blocks' ), array( 'id' => (int) $id ) );
		unset( self::$cache[ $b['location_id'] . '|' . $b['date'] ] );
		hkr_purge_page_cache();
		return true;
	}

	/** Remove blocks for days that have passed (daily clean-up). */
	public static function cleanup() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . hkr_table( 'blocks' ) . ' WHERE block_date < %s', hkr_now()->modify( '-7 days' )->format( 'Y-m-d' ) ) );
	}

	private static function hydrate( $row ) {
		return array(
			'id'          => (int) $row['id'],
			'location_id' => (int) $row['location_id'],
			'area_id'     => (int) $row['area_id'],
			'date'        => $row['block_date'],
			'time_from'   => (string) $row['time_from'],
			'time_to'     => (string) $row['time_to'],
			'all_day'     => '' === (string) $row['time_from'],
			'note'        => (string) $row['note'],
			'created_by'  => (int) $row['created_by'],
		);
	}
}
