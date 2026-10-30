<?php
/**
 * Small shared helpers.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restaurant timezone. Both locations are in Trinidad (UTC-4, no daylight saving).
 */
function hkr_tz() {
	static $tz = null;
	if ( null === $tz ) {
		$tz = new DateTimeZone( apply_filters( 'hkr_timezone', 'America/Port_of_Spain' ) );
	}
	return $tz;
}

/**
 * Current time in the restaurant timezone.
 *
 * @return DateTimeImmutable
 */
function hkr_now() {
	return new DateTimeImmutable( 'now', hkr_tz() );
}

/**
 * Full table name for one of the plugin's tables.
 *
 * @param string $name locations|areas|bookings|booking_log|message_log.
 */
function hkr_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'hkr_' . $name;
}

/**
 * Booking statuses and their labels.
 */
/**
 * How a photo sits in the booking form header: focus point (percent) and zoom, for desktop and phones.
 */
function hkr_default_frame() {
	return array(
		'x'  => 50,
		'y'  => 50,
		'z'  => 100,
		'mx' => 50,
		'my' => 50,
		'mz' => 100,
	);
}

function hkr_sanitize_frame( $in ) {
	$in  = is_array( $in ) ? $in : hkr_json( $in );
	$out = hkr_default_frame();
	foreach ( $out as $key => $default ) {
		if ( ! isset( $in[ $key ] ) || '' === $in[ $key ] ) {
			continue;
		}
		$value       = (float) $in[ $key ];
		$out[ $key ] = in_array( $key, array( 'z', 'mz' ), true ) ? (int) min( 250, max( 100, round( $value ) ) ) : round( min( 100, max( 0, $value ) ), 1 );
	}
	return $out;
}

/**
 * Frame values for the booking form script.
 */
function hkr_frame_public( $frame ) {
	$f = hkr_sanitize_frame( $frame );
	return array(
		'pos'  => $f['x'] . '% ' . $f['y'] . '%',
		'zoom' => $f['z'] / 100,
		'posM' => $f['mx'] . '% ' . $f['my'] . '%',
		'zoomM' => $f['mz'] / 100,
	);
}

function hkr_statuses() {
	return array(
		'pending'   => __( 'Pending', 'hakka-reservations' ),
		'confirmed' => __( 'Confirmed', 'hakka-reservations' ),
		'seated'    => __( 'Seated', 'hakka-reservations' ),
		'completed' => __( 'Completed', 'hakka-reservations' ),
		'no_show'   => __( 'No-show', 'hakka-reservations' ),
		'cancelled' => __( 'Cancelled', 'hakka-reservations' ),
		'declined'  => __( 'Declined', 'hakka-reservations' ),
	);
}

/**
 * Statuses that no longer hold a place in a time slot.
 */
function hkr_released_statuses() {
	return array( 'cancelled', 'declined' );
}

function hkr_status_label( $status ) {
	$all = hkr_statuses();
	return isset( $all[ $status ] ) ? $all[ $status ] : ucfirst( (string) $status );
}

/**
 * Booking sources and their labels.
 */
function hkr_sources() {
	return array(
		'web'    => __( 'Website', 'hakka-reservations' ),
		'phone'  => __( 'Phone', 'hakka-reservations' ),
		'walkin' => __( 'Walk-in', 'hakka-reservations' ),
		'staff'  => __( 'Staff', 'hakka-reservations' ),
	);
}

/**
 * Decode a JSON column into an array.
 */
function hkr_json( $value, $default = array() ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return $default;
	}
	$decoded = json_decode( $value, true );
	return is_array( $decoded ) ? $decoded : $default;
}

/**
 * Format a Y-m-d date for people, e.g. "Friday, 6 November 2026".
 */
function hkr_format_date( $ymd, $format = 'l, j F Y' ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', substr( (string) $ymd, 0, 10 ), hkr_tz() );
	return $d ? wp_date( $format, $d->getTimestamp(), hkr_tz() ) : (string) $ymd;
}

/**
 * Format a time for people, e.g. "6:00pm". Accepts "H:i", "H:i:s" or a full datetime.
 */
function hkr_format_time( $value ) {
	$value = (string) $value;
	if ( strlen( $value ) > 8 ) {
		$value = substr( $value, 11, 5 );
	}
	$value = substr( $value, 0, 5 );
	if ( '24:00' === $value ) {
		return __( 'midnight', 'hakka-reservations' );
	}
	$d = DateTimeImmutable::createFromFormat( '!H:i', $value, hkr_tz() );
	return $d ? $d->format( 'g:ia' ) : $value;
}

/**
 * Minutes since midnight for "H:i". "24:00" is 1440.
 */
function hkr_minutes( $hm ) {
	$parts = explode( ':', (string) $hm );
	return ( (int) $parts[0] ) * 60 + ( isset( $parts[1] ) ? (int) $parts[1] : 0 );
}

/**
 * "H:i" for minutes since midnight. 1440 becomes "24:00".
 */
function hkr_hm( $minutes ) {
	$minutes = (int) $minutes;
	return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
}

/**
 * Store phone numbers in international form so WhatsApp can use them later.
 * Seven-digit Trinidad numbers get the +1 868 prefix.
 */
function hkr_normalize_phone( $phone ) {
	$phone  = trim( (string) $phone );
	$plus   = ( 0 === strpos( $phone, '+' ) );
	$digits = preg_replace( '/\D+/', '', $phone );
	if ( '' === $digits ) {
		return '';
	}
	if ( $plus ) {
		return '+' . $digits;
	}
	$country = preg_replace( '/\D+/', '', (string) HKR_Settings::get( 'country_code' ) );
	if ( 7 === strlen( $digits ) && $country ) {
		return '+' . $country . $digits;
	}
	// North American numbering: country code "1868" → a 10-digit "868…" number gets "+1".
	if ( 10 === strlen( $digits ) && strlen( $country ) > 1 && '1' === $country[0] && 0 === strpos( $digits, substr( $country, 1 ) ) ) {
		return '+1' . $digits;
	}
	if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
		return '+' . $digits;
	}
	return $digits;
}

/**
 * Pretty phone for display: +18683342552 -> +1 868-334-2552.
 */
function hkr_display_phone( $phone ) {
	$phone = (string) $phone;
	if ( preg_match( '/^\+1(\d{3})(\d{3})(\d{4})$/', $phone, $m ) ) {
		return '+1 ' . $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	return $phone;
}

/**
 * Location IDs the current user may see. Null means all locations.
 *
 * @param int|null $user_id User, defaults to the current user.
 * @return int[]|null
 */
function hkr_user_location_ids( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	if ( user_can( $user_id, 'hkr_manage' ) || user_can( $user_id, 'manage_options' ) ) {
		return null;
	}
	$ids = get_user_meta( $user_id, 'hkr_locations', true );
	return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
}

/**
 * Whether the current user may see bookings for a location.
 */
function hkr_user_can_location( $location_id, $user_id = null ) {
	$ids = hkr_user_location_ids( $user_id );
	return null === $ids || in_array( (int) $location_id, $ids, true );
}

/**
 * Email addresses from a comma or newline separated string.
 *
 * @return string[]
 */
function hkr_parse_emails( $value ) {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $value ) as $email ) {
		$email = sanitize_email( $email );
		if ( $email && is_email( $email ) ) {
			$out[] = $email;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Lines of a textarea as a clean list.
 *
 * @return string[]
 */
function hkr_lines( $value ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
		$line = sanitize_text_field( $line );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}
	return $out;
}

/**
 * Record a change in the booking log. Booking 0 is used for settings changes.
 */
function hkr_log( $booking_id, $action, $old = '', $new = '' ) {
	global $wpdb;
	$user  = wp_get_current_user();
	$actor = $user && $user->exists() ? $user->display_name . ' (#' . $user->ID . ')' : __( 'Guest', 'hakka-reservations' );
	$wpdb->insert(
		hkr_table( 'booking_log' ),
		array(
			'booking_id' => (int) $booking_id,
			'action'     => substr( (string) $action, 0, 60 ),
			'old_value'  => is_scalar( $old ) ? (string) $old : wp_json_encode( $old ),
			'new_value'  => is_scalar( $new ) ? (string) $new : wp_json_encode( $new ),
			'actor'      => $actor,
			'created_at' => hkr_now()->format( 'Y-m-d H:i:s' ),
		)
	);
}

/**
 * The visitor's IP address. Behind Cloudflare every request comes from a Cloudflare address,
 * so prefer the header Cloudflare adds. Only used for spam limits, never for access control.
 */
function hkr_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '';
}

/**
 * Clear the page cache after locations or settings change, so the booking page shows them at once.
 */
function hkr_purge_page_cache() {
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	do_action( 'hkr_purge_page_cache' );
}

/**
 * Prevent full-page caching of a response (SiteGround and most cache plugins honour this).
 */
function hkr_no_cache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-Cache-Enabled: False' );
	}
}
