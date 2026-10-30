<?php
/**
 * REST API (namespace hkr/v1) used by the booking form, the guest manage page and the staff screen.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Rest {

	const NS = 'hkr/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_cache_headers' ), 10, 3 );
	}

	/**
	 * Availability and bookings change all the time, so caches must never keep these answers.
	 */
	public static function no_cache_headers( $response, $server, $request ) {
		if ( $response instanceof WP_HTTP_Response && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/' ) ) {
			$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0' );
			$response->header( 'X-Cache-Enabled', 'False' );
		}
		return $response;
	}

	public static function routes() {
		$public = '__return_true';

		register_rest_route( self::NS, '/locations', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'locations' ), 'permission_callback' => $public ) );
		register_rest_route(
			self::NS,
			'/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'calendar' ),
				'permission_callback' => $public,
				'args'                => array(
					'location' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'area'     => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'slots' ),
				'permission_callback' => $public,
				'args'                => array(
					'location' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'area'     => array( 'sanitize_callback' => 'absint' ),
					'party'    => array( 'sanitize_callback' => 'absint' ),
					'date'     => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route( self::NS, '/bookings', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create_booking' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/manage', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'manage_get' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/manage/cancel', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'manage_cancel' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/manage/change', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'manage_change' ), 'permission_callback' => $public ) );

		$can_view = static function () {
			return current_user_can( 'hkr_view_bookings' );
		};
		$can_edit = static function () {
			return current_user_can( 'hkr_edit_bookings' );
		};
		register_rest_route( self::NS, '/staff/bookings', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'staff_list' ), 'permission_callback' => $can_view ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_create' ), 'permission_callback' => $can_edit ),
		) );
		register_rest_route( self::NS, '/staff/poll', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'staff_poll' ), 'permission_callback' => $can_view ) );
		register_rest_route( self::NS, '/staff/slots', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'staff_slots' ), 'permission_callback' => $can_view ) );
		register_rest_route( self::NS, '/staff/bookings/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_update' ), 'permission_callback' => $can_edit ) );
		register_rest_route( self::NS, '/staff/bookings/(?P<id>\d+)/status', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_status' ), 'permission_callback' => $can_edit ) );
		register_rest_route( self::NS, '/staff/bookings/(?P<id>\d+)/note', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_note' ), 'permission_callback' => $can_edit ) );
		register_rest_route( self::NS, '/staff/blocks', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'staff_blocks' ), 'permission_callback' => $can_view ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_block_add' ), 'permission_callback' => $can_edit ),
		) );
		register_rest_route( self::NS, '/staff/blocks/(?P<id>\d+)/delete', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'staff_block_delete' ), 'permission_callback' => $can_edit ) );
	}

	private static function error( WP_Error $e, $status = 400 ) {
		return new WP_REST_Response(
			array(
				'code'    => $e->get_error_code(),
				'message' => $e->get_error_message(),
			),
			$status
		);
	}

	private static function bookable_location( $id ) {
		$loc = HKR_Locations::get( (int) $id );
		return ( $loc && $loc['active'] && $loc['accepts_bookings'] ) ? $loc : null;
	}

	private static function area_for( $loc, $area_id ) {
		if ( ! $area_id ) {
			return null;
		}
		$area = HKR_Locations::area( (int) $area_id );
		return ( $area && $area['location_id'] === $loc['id'] ) ? $area : null;
	}

	/* ---------------- Public: booking form ---------------- */

	public static function locations() {
		$out = array();
		foreach ( HKR_Locations::all( true ) as $loc ) {
			$out[] = HKR_Locations::public_data( $loc );
		}
		return rest_ensure_response( $out );
	}

	public static function calendar( WP_REST_Request $req ) {
		$loc = self::bookable_location( $req['location'] );
		if ( ! $loc ) {
			return self::error( new WP_Error( 'hkr_location', __( 'Please choose a location.', 'hakka-reservations' ) ), 404 );
		}
		$area = self::area_for( $loc, $req['area'] );
		return rest_ensure_response( array( 'dates' => HKR_Availability::calendar( $loc, $area ) ) );
	}

	public static function slots( WP_REST_Request $req ) {
		$loc = self::bookable_location( $req['location'] );
		if ( ! $loc ) {
			return self::error( new WP_Error( 'hkr_location', __( 'Please choose a location.', 'hakka-reservations' ) ), 404 );
		}
		$area  = self::area_for( $loc, $req['area'] );
		$party = max( 1, (int) $req['party'] );
		$slots = HKR_Availability::slots( $loc, $area, (string) $req['date'], $party );
		$show  = (bool) HKR_Settings::get( 'show_spots_left' );
		foreach ( $slots as &$slot ) {
			if ( ! $show ) {
				$slot['left'] = null;
			}
		}
		unset( $slot );
		$day = HKR_Availability::ranges_for_date( $loc, (string) $req['date'] );
		return rest_ensure_response(
			array(
				'slots' => $slots,
				'note'  => $day['note'],
			)
		);
	}

	public static function create_booking( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) {
			$p = $req->get_body_params();
		}

		// Honeypot: real people never fill this hidden field.
		if ( ! empty( $p['website'] ) ) {
			return self::error( new WP_Error( 'hkr_spam', __( 'Sorry, we could not take this booking.', 'hakka-reservations' ) ) );
		}
		if ( empty( $p['terms'] ) ) {
			return self::error( new WP_Error( 'hkr_terms', __( 'Please accept the booking terms.', 'hakka-reservations' ) ) );
		}
		$limited = self::rate_limited();
		if ( $limited ) {
			return self::error( $limited, 429 );
		}
		$turnstile = self::verify_turnstile( isset( $p['turnstile'] ) ? (string) $p['turnstile'] : '' );
		if ( is_wp_error( $turnstile ) ) {
			return self::error( $turnstile );
		}

		$booking = HKR_Bookings::create(
			array(
				'location_id'         => isset( $p['location'] ) ? (int) $p['location'] : 0,
				'area_id'             => isset( $p['area'] ) ? (int) $p['area'] : 0,
				'date'                => isset( $p['date'] ) ? (string) $p['date'] : '',
				'time'                => isset( $p['time'] ) ? (string) $p['time'] : '',
				'party'               => isset( $p['party'] ) ? (int) $p['party'] : 0,
				'name'                => isset( $p['name'] ) ? $p['name'] : '',
				'phone'               => isset( $p['phone'] ) ? $p['phone'] : '',
				'email'               => isset( $p['email'] ) ? $p['email'] : '',
				'celebration'         => isset( $p['celebration'] ) ? $p['celebration'] : '',
				'celebration_text'    => isset( $p['celebration_text'] ) ? $p['celebration_text'] : '',
				'celebration_details' => isset( $p['celebration_details'] ) ? $p['celebration_details'] : '',
				'seating'             => isset( $p['seating'] ) ? $p['seating'] : '',
				'high_chair'          => ! empty( $p['high_chair'] ),
				'dietary'             => isset( $p['dietary'] ) ? $p['dietary'] : '',
				'requests'            => isset( $p['requests'] ) ? $p['requests'] : '',
			),
			array( 'source' => 'web' )
		);
		if ( is_wp_error( $booking ) ) {
			return self::error( $booking, 'hkr_full' === $booking->get_error_code() ? 409 : 400 );
		}
		self::count_attempt();
		$loc = HKR_Locations::get( $booking['location_id'] );
		return rest_ensure_response(
			array(
				'ref'         => $booking['ref'],
				'location'    => $loc['name'],
				'date'        => hkr_format_date( $booking['date'] ),
				'time'        => hkr_format_time( $booking['time'] ),
				'party'       => $booking['party_size'],
				'area'        => $booking['area_name'],
				'email'       => $booking['guest_email'],
				'confirmNote' => $loc['confirm_note'] ? $loc['confirm_note'] : __( 'We will call you to confirm your booking.', 'hakka-reservations' ),
			)
		);
	}

	private static function rate_key() {
		$ip = hkr_client_ip();
		return 'hkr_rl_' . md5( ( $ip ? $ip : 'unknown' ) . wp_salt( 'nonce' ) );
	}

	private static function rate_limited() {
		$limit = (int) HKR_Settings::get( 'rate_limit' );
		if ( $limit <= 0 || current_user_can( 'hkr_edit_bookings' ) ) {
			return null;
		}
		$count = (int) get_transient( self::rate_key() );
		if ( $count >= $limit ) {
			return new WP_Error( 'hkr_rate', __( 'Too many bookings from this connection. Please call us to book.', 'hakka-reservations' ) );
		}
		return null;
	}

	private static function count_attempt() {
		$key = self::rate_key();
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	}

	private static function verify_turnstile( $token ) {
		$secret = (string) HKR_Settings::get( 'turnstile_secret' );
		if ( '' === $secret || '' === (string) HKR_Settings::get( 'turnstile_site' ) ) {
			return true;
		}
		if ( '' === $token ) {
			return new WP_Error( 'hkr_turnstile', __( 'Please complete the security check.', 'hakka-reservations' ) );
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return true; // Don't lose a booking because the check service is unreachable.
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $body['success'] ) ? true : new WP_Error( 'hkr_turnstile', __( 'The security check failed. Please try again.', 'hakka-reservations' ) );
	}

	/* ---------------- Public: guest manage link ---------------- */

	private static function manage_booking( WP_REST_Request $req ) {
		$ref     = (string) $req->get_param( 'ref' );
		$token   = (string) $req->get_param( 'token' );
		$booking = HKR_Bookings::get_by_ref( $ref );
		if ( ! $booking || ! HKR_Bookings::verify_token( $booking, $token ) ) {
			return null;
		}
		return $booking;
	}

	public static function manage_public( $booking ) {
		$loc = HKR_Locations::get( $booking['location_id'] );
		return array(
			'ref'         => $booking['ref'],
			'status'      => $booking['status'],
			'statusLabel' => 'pending' === $booking['status'] ? __( 'Pending confirmation', 'hakka-reservations' ) : hkr_status_label( $booking['status'] ),
			'location'    => $loc ? $loc['name'] : '',
			'address'     => $loc ? $loc['address'] : '',
			'phone'       => $loc ? hkr_display_phone( $loc['phone'] ) : '',
			'phoneRaw'    => $loc ? $loc['phone'] : '',
			'date'        => hkr_format_date( $booking['date'] ),
			'time'        => hkr_format_time( $booking['time'] ) . ' – ' . hkr_format_time( $booking['end_time'] ),
			'party'       => $booking['party_size'],
			'area'        => $booking['area_name'],
			'name'        => $booking['guest_name'],
			'canCancel'   => HKR_Bookings::guest_can_cancel( $booking ),
		);
	}

	public static function manage_get( WP_REST_Request $req ) {
		$booking = self::manage_booking( $req );
		if ( ! $booking ) {
			return self::error( new WP_Error( 'hkr_link', __( 'This link is not valid any more. Please use the link in your latest email, or call us.', 'hakka-reservations' ) ), 404 );
		}
		return rest_ensure_response( self::manage_public( $booking ) );
	}

	public static function manage_cancel( WP_REST_Request $req ) {
		$booking = self::manage_booking( $req );
		if ( ! $booking ) {
			return self::error( new WP_Error( 'hkr_link', __( 'This link is not valid any more.', 'hakka-reservations' ) ), 404 );
		}
		if ( ! HKR_Bookings::guest_can_cancel( $booking ) ) {
			return self::error( new WP_Error( 'hkr_cutoff', __( 'This booking can no longer be cancelled online. Please call us.', 'hakka-reservations' ) ) );
		}
		$updated = HKR_Bookings::guest_cancel( $booking );
		if ( is_wp_error( $updated ) ) {
			return self::error( $updated );
		}
		return rest_ensure_response( self::manage_public( $updated ) );
	}

	public static function manage_change( WP_REST_Request $req ) {
		$booking = self::manage_booking( $req );
		if ( ! $booking ) {
			return self::error( new WP_Error( 'hkr_link', __( 'This link is not valid any more.', 'hakka-reservations' ) ), 404 );
		}
		$message = trim( (string) $req->get_param( 'message' ) );
		if ( mb_strlen( $message ) < 3 ) {
			return self::error( new WP_Error( 'hkr_message', __( 'Please tell us what you would like to change.', 'hakka-reservations' ) ) );
		}
		$key = 'hkr_chg_' . $booking['id'];
		if ( (int) get_transient( $key ) >= 3 ) {
			return self::error( new WP_Error( 'hkr_rate', __( 'We have your request. Please call us if it is urgent.', 'hakka-reservations' ) ), 429 );
		}
		set_transient( $key, (int) get_transient( $key ) + 1, DAY_IN_SECONDS );
		HKR_Bookings::guest_request_change( $booking, $message );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ---------------- Staff screen ---------------- */

	public static function staff_booking( $b ) {
		$loc = HKR_Locations::get( $b['location_id'] );
		return array(
			'id'                 => $b['id'],
			'ref'                => $b['ref'],
			'date'               => $b['date'],
			'dateLabel'          => hkr_format_date( $b['date'], 'D j M' ),
			'time'               => $b['time'],
			'timeLabel'          => hkr_format_time( $b['time'] ),
			'endLabel'           => hkr_format_time( $b['end_time'] ),
			'party'              => $b['party_size'],
			'status'             => $b['status'],
			'statusLabel'        => hkr_status_label( $b['status'] ),
			'source'             => $b['source'],
			'name'               => $b['guest_name'],
			'phone'              => $b['guest_phone'],
			'phoneDisplay'       => hkr_display_phone( $b['guest_phone'] ),
			'email'              => $b['guest_email'],
			'areaId'             => $b['area_id'],
			'area'               => $b['area_name'],
			'locationId'         => $b['location_id'],
			'location'           => $loc ? $loc['name'] : '',
			'celebration'        => HKR_Mailer::celebration_label( $b ),
			'celebrationDetails' => (string) $b['celebration_details'],
			'seating'            => $b['seating_pref'],
			'highChair'          => (bool) $b['high_chair'],
			'dietary'            => (string) $b['dietary_notes'],
			'requests'           => (string) $b['special_requests'],
			'internalNotes'      => (string) $b['internal_notes'],
			'largeParty'         => $loc && (int) $loc['settings']['large_party'] > 0 && $b['party_size'] >= (int) $loc['settings']['large_party'],
			'createdAt'          => $b['created_at'],
		);
	}

	private static function staff_location_ids( $requested = 0 ) {
		$allowed = hkr_user_location_ids();
		if ( $requested ) {
			return hkr_user_can_location( $requested ) ? array( (int) $requested ) : array();
		}
		if ( null === $allowed ) {
			return wp_list_pluck( HKR_Locations::all(), 'id' );
		}
		return $allowed;
	}

	private static function staff_locations_payload() {
		$out = array();
		foreach ( HKR_Locations::all() as $loc ) {
			if ( ! hkr_user_can_location( $loc['id'] ) ) {
				continue;
			}
			$areas = array();
			foreach ( HKR_Locations::areas( $loc['id'], false ) as $a ) {
				$areas[] = array( 'id' => $a['id'], 'name' => $a['name'], 'active' => (bool) $a['active'] );
			}
			$out[] = array(
				'id'    => $loc['id'],
				'name'  => $loc['name'],
				'areas' => $areas,
			);
		}
		return $out;
	}

	public static function staff_list( WP_REST_Request $req ) {
		$date = (string) $req->get_param( 'date' );
		if ( ! HKR_Availability::valid_date( $date ) ) {
			$date = hkr_now()->format( 'Y-m-d' );
		}
		$ids    = self::staff_location_ids( (int) $req->get_param( 'location' ) );
		$status = (string) $req->get_param( 'status' );
		$search = trim( (string) $req->get_param( 'search' ) );
		$view   = 'upcoming' === $req->get_param( 'view' ) ? 'upcoming' : 'day';
		if ( 'upcoming_pending' === $status ) { // Older screens.
			$view   = 'upcoming';
			$status = 'pending';
		}
		$args = array(
			'location_ids' => $ids,
			'limit'        => 300,
		);
		if ( '' !== $search ) {
			$args['search']    = $search;
			$args['date_from'] = hkr_now()->modify( '-30 days' )->format( 'Y-m-d' );
		} elseif ( 'upcoming' === $view ) {
			$args['date_from'] = hkr_now()->format( 'Y-m-d' );
		} else {
			$args['date_from'] = $date;
			$args['date_to']   = $date;
		}
		if ( $status ) {
			$args['status'] = $status;
		}
		$result = HKR_Bookings::query( $args );

		$pending = HKR_Bookings::query(
			array(
				'location_ids' => $ids,
				'status'       => 'pending',
				'date_from'    => hkr_now()->format( 'Y-m-d' ),
				'limit'        => 0,
			)
		);
		$latest = self::latest_id( $ids );

		$covers = 0;
		foreach ( $result['items'] as $b ) {
			if ( ! in_array( $b['status'], hkr_released_statuses(), true ) ) {
				$covers += $b['party_size'];
			}
		}
		return rest_ensure_response(
			array(
				'view'      => $view,
				'date'      => $date,
				'dateLabel' => hkr_format_date( $date ),
				'today'     => hkr_now()->format( 'Y-m-d' ),
				'bookings'  => array_map( array( __CLASS__, 'staff_booking' ), $result['items'] ),
				'covers'    => $covers,
				'pending'   => $pending['total'],
				'stats'     => self::staff_stats( $ids ),
				'blocks'    => array_map( array( __CLASS__, 'block_payload' ), HKR_Blocks::between( $ids, hkr_now()->format( 'Y-m-d' ), hkr_now()->modify( '+366 days' )->format( 'Y-m-d' ) ) ),
				'latestId'  => $latest,
				'locations' => self::staff_locations_payload(),
			)
		);
	}

	/**
	 * Numbers for the tiles at the top of the staff screen.
	 */
	private static function staff_stats( $ids ) {
		$today  = hkr_now()->format( 'Y-m-d' );
		$week   = hkr_now()->modify( '+6 days' )->format( 'Y-m-d' );
		$active = array_values( array_diff( array_keys( hkr_statuses() ), hkr_released_statuses(), array( 'no_show' ) ) );
		$out    = array();
		foreach ( array( 'today' => $today, 'week' => $week ) as $key => $to ) {
			$res      = HKR_Bookings::query(
				array(
					'location_ids' => $ids,
					'status'       => $active,
					'date_from'    => $today,
					'date_to'      => $to,
					'limit'        => -1,
				)
			);
			$guests = 0;
			foreach ( $res['items'] as $b ) {
				$guests += (int) $b['party_size'];
			}
			$out[ $key ] = array(
				'bookings' => count( $res['items'] ),
				'guests'   => $guests,
			);
		}
		return $out;
	}

	private static function latest_id( $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return 0;
		}
		return (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . hkr_table( 'bookings' ) . ' WHERE location_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' );
	}

	public static function staff_poll( WP_REST_Request $req ) {
		$ids   = self::staff_location_ids( (int) $req->get_param( 'location' ) );
		$since = (int) $req->get_param( 'since' );
		$new   = $since ? HKR_Bookings::query( array( 'location_ids' => $ids, 'since_id' => $since, 'limit' => 20, 'orderby' => 'id' ) ) : array( 'items' => array() );
		$pending = HKR_Bookings::query( array( 'location_ids' => $ids, 'status' => 'pending', 'date_from' => hkr_now()->format( 'Y-m-d' ), 'limit' => 0 ) );
		return rest_ensure_response(
			array(
				'new'      => array_map( array( __CLASS__, 'staff_booking' ), $new['items'] ),
				'pending'  => $pending['total'],
				'latestId' => max( $since, self::latest_id( $ids ) ),
			)
		);
	}

	public static function staff_slots( WP_REST_Request $req ) {
		$loc = HKR_Locations::get( (int) $req->get_param( 'location' ) );
		if ( ! $loc || ! hkr_user_can_location( $loc['id'] ) ) {
			return self::error( new WP_Error( 'hkr_forbidden', __( 'You cannot see this location.', 'hakka-reservations' ) ), 403 );
		}
		$area    = self::area_for( $loc, (int) $req->get_param( 'area' ) );
		$date    = (string) $req->get_param( 'date' );
		$exclude = (int) $req->get_param( 'exclude' );
		$party   = max( 1, (int) $req->get_param( 'party' ) );
		$out     = array();
		if ( HKR_Availability::valid_date( $date ) ) {
			foreach ( HKR_Availability::slot_times( $loc, $date ) as $slot ) {
				$rem   = HKR_Availability::remaining( $loc, $area, $date, $slot[0], $slot[1], true, $exclude );
				$out[] = array(
					'time'      => $slot[0],
					'end'       => $slot[1],
					'label'     => hkr_format_time( $slot[0] ),
					'left'      => $rem['left'],
					'available' => ! $rem['full'] && ( null === $rem['left'] || $rem['left'] >= $party ),
					'blocked'   => (bool) HKR_Blocks::find( $loc['id'], $area ? $area['id'] : 0, $date, $slot[0] ),
				);
			}
		}
		return rest_ensure_response( array( 'slots' => $out ) );
	}

	/* ----- Online booking blocks ----- */

	private static function block_payload( $b ) {
		static $locs = array(), $areas = array(), $users = array();
		if ( ! isset( $locs[ $b['location_id'] ] ) ) {
			$loc                        = HKR_Locations::get( $b['location_id'] );
			$locs[ $b['location_id'] ] = $loc ? $loc['name'] : '';
		}
		if ( $b['area_id'] && ! isset( $areas[ $b['area_id'] ] ) ) {
			$area                   = HKR_Locations::area( $b['area_id'] );
			$areas[ $b['area_id'] ] = $area ? $area['name'] : '';
		}
		if ( $b['created_by'] && ! isset( $users[ $b['created_by'] ] ) ) {
			$user                        = get_userdata( $b['created_by'] );
			$users[ $b['created_by'] ] = $user ? $user->display_name : '';
		}
		return array(
			'id'         => $b['id'],
			'locationId' => $b['location_id'],
			'location'   => $locs[ $b['location_id'] ],
			'areaId'     => $b['area_id'],
			'area'       => $b['area_id'] ? $areas[ $b['area_id'] ] : '',
			'date'       => $b['date'],
			'dateLabel'  => hkr_format_date( $b['date'], 'D j M' ),
			'allDay'     => $b['all_day'],
			'from'       => $b['time_from'],
			'to'         => $b['time_to'],
			'timeLabel'  => $b['all_day'] ? __( 'All day', 'hakka-reservations' ) : hkr_format_time( $b['time_from'] ),
			'note'       => $b['note'],
			'by'         => $b['created_by'] ? $users[ $b['created_by'] ] : '',
		);
	}

	public static function staff_blocks( WP_REST_Request $req ) {
		$ids  = self::staff_location_ids( (int) $req->get_param( 'location' ) );
		$from = hkr_now()->format( 'Y-m-d' );
		$to   = hkr_now()->modify( '+366 days' )->format( 'Y-m-d' );
		return rest_ensure_response( array( 'blocks' => array_map( array( __CLASS__, 'block_payload' ), HKR_Blocks::between( $ids, $from, $to ) ) ) );
	}

	/**
	 * Body: location, area (0 = every type), from, to (dates, to optional), times (array of slot starts; empty = whole day), note.
	 */
	public static function staff_block_add( WP_REST_Request $req ) {
		$loc = HKR_Locations::get( (int) $req->get_param( 'location' ) );
		if ( ! $loc || ! hkr_user_can_location( $loc['id'] ) ) {
			return self::error( new WP_Error( 'hkr_forbidden', __( 'You cannot change this location.', 'hakka-reservations' ) ), 403 );
		}
		$area_id = (int) $req->get_param( 'area' );
		if ( $area_id && ! self::area_for( $loc, $area_id ) ) {
			$area_id = 0;
		}
		$from = (string) $req->get_param( 'from' );
		$to   = (string) $req->get_param( 'to' );
		$to   = '' === $to ? $from : $to;
		if ( ! HKR_Availability::valid_date( $from ) || ! HKR_Availability::valid_date( $to ) || $to < $from ) {
			return self::error( new WP_Error( 'hkr_date', __( 'Please choose a valid date (and an end date on or after it).', 'hakka-reservations' ) ) );
		}
		if ( $from < hkr_now()->format( 'Y-m-d' ) ) {
			return self::error( new WP_Error( 'hkr_date', __( 'That date has already passed.', 'hakka-reservations' ) ) );
		}
		$days = (int) ( new DateTimeImmutable( $from ) )->diff( new DateTimeImmutable( $to ) )->days;
		if ( $days > 92 ) {
			return self::error( new WP_Error( 'hkr_date', __( 'Please block up to three months at a time.', 'hakka-reservations' ) ) );
		}
		$times = array_values( array_unique( array_filter( array_map( 'strval', (array) $req->get_param( 'times' ) ), static function ( $t ) {
			return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t );
		} ) ) );
		if ( $req->get_param( 'times' ) && ! $times ) {
			return self::error( new WP_Error( 'hkr_time', __( 'Please choose at least one time.', 'hakka-reservations' ) ) );
		}
		$note  = mb_substr( sanitize_text_field( (string) $req->get_param( 'note' ) ), 0, 190 );
		$added = 0;
		$day   = new DateTimeImmutable( $from, hkr_tz() );
		for ( $i = 0; $i <= $days; $i++, $day = $day->modify( '+1 day' ) ) {
			$ymd = $day->format( 'Y-m-d' );
			if ( ! $times ) {
				HKR_Blocks::add( $loc['id'], $area_id, $ymd, '', '', $note, get_current_user_id() );
				++$added;
				continue;
			}
			// Only block times that are real slots on that day.
			foreach ( HKR_Availability::slot_times( $loc, $ymd ) as $slot ) {
				if ( in_array( $slot[0], $times, true ) ) {
					HKR_Blocks::add( $loc['id'], $area_id, $ymd, $slot[0], $slot[1], $note, get_current_user_id() );
					++$added;
				}
			}
		}
		if ( ! $added ) {
			return self::error( new WP_Error( 'hkr_time', __( 'Those times are not open on the chosen day(s), so nothing was blocked.', 'hakka-reservations' ) ) );
		}
		hkr_log( 0, 'blocks_added', '', sprintf( '%s: %s – %s, %s (%d)', $loc['name'], $from, $to, $times ? implode( ' ', $times ) : 'all day', $added ) );
		return self::staff_blocks( new WP_REST_Request( 'GET' ) );
	}

	public static function staff_block_delete( WP_REST_Request $req ) {
		$b = HKR_Blocks::get( (int) $req['id'] );
		if ( ! $b || ! hkr_user_can_location( $b['location_id'] ) ) {
			return self::error( new WP_Error( 'hkr_forbidden', __( 'You cannot change this block.', 'hakka-reservations' ) ), 403 );
		}
		HKR_Blocks::delete( $b['id'] );
		return self::staff_blocks( new WP_REST_Request( 'GET' ) );
	}

	private static function staff_guard( $id ) {
		$booking = HKR_Bookings::get( (int) $id );
		if ( ! $booking ) {
			return new WP_Error( 'hkr_missing', __( 'Booking not found.', 'hakka-reservations' ) );
		}
		if ( ! hkr_user_can_location( $booking['location_id'] ) ) {
			return new WP_Error( 'hkr_forbidden', __( 'You cannot change bookings for this location.', 'hakka-reservations' ) );
		}
		return $booking;
	}

	public static function staff_status( WP_REST_Request $req ) {
		$booking = self::staff_guard( $req['id'] );
		if ( is_wp_error( $booking ) ) {
			return self::error( $booking, 403 );
		}
		$updated = HKR_Bookings::set_status(
			$booking['id'],
			sanitize_key( (string) $req->get_param( 'status' ) ),
			array(
				'notify'  => false !== $req->get_param( 'notify' ) && 'false' !== $req->get_param( 'notify' ),
				'message' => sanitize_textarea_field( (string) $req->get_param( 'message' ) ),
			)
		);
		if ( is_wp_error( $updated ) ) {
			return self::error( $updated );
		}
		return rest_ensure_response( self::staff_booking( $updated ) );
	}

	public static function staff_note( WP_REST_Request $req ) {
		$booking = self::staff_guard( $req['id'] );
		if ( is_wp_error( $booking ) ) {
			return self::error( $booking, 403 );
		}
		return rest_ensure_response( self::staff_booking( HKR_Bookings::add_note( $booking['id'], (string) $req->get_param( 'note' ) ) ) );
	}

	private static function staff_fields( WP_REST_Request $req ) {
		$fields = array();
		$map    = array(
			'date'                => 'date',
			'time'                => 'time',
			'party'               => 'party',
			'area'                => 'area_id',
			'name'                => 'name',
			'phone'               => 'phone',
			'email'               => 'email',
			'celebration'         => 'celebration',
			'celebration_text'    => 'celebration_text',
			'celebration_details' => 'celebration_details',
			'seating'             => 'seating',
			'high_chair'          => 'high_chair',
			'dietary'             => 'dietary',
			'requests'            => 'requests',
			'internal_notes'      => 'internal_notes',
		);
		foreach ( $map as $param => $field ) {
			$value = $req->get_param( $param );
			if ( null !== $value ) {
				$fields[ $field ] = is_bool( $value ) ? (int) $value : $value;
			}
		}
		return $fields;
	}

	public static function staff_update( WP_REST_Request $req ) {
		$booking = self::staff_guard( $req['id'] );
		if ( is_wp_error( $booking ) ) {
			return self::error( $booking, 403 );
		}
		$updated = HKR_Bookings::update(
			$booking['id'],
			self::staff_fields( $req ),
			array(
				'notify'  => (bool) $req->get_param( 'notify' ),
				'message' => sanitize_textarea_field( (string) $req->get_param( 'message' ) ),
			)
		);
		if ( is_wp_error( $updated ) ) {
			return self::error( $updated );
		}
		return rest_ensure_response( self::staff_booking( $updated ) );
	}

	public static function staff_create( WP_REST_Request $req ) {
		$location_id = (int) $req->get_param( 'location' );
		if ( ! hkr_user_can_location( $location_id ) ) {
			return self::error( new WP_Error( 'hkr_forbidden', __( 'You cannot add bookings for this location.', 'hakka-reservations' ) ), 403 );
		}
		$fields                = self::staff_fields( $req );
		$fields['location_id'] = $location_id;
		$source                = sanitize_key( (string) $req->get_param( 'source' ) );
		$status                = sanitize_key( (string) $req->get_param( 'status' ) );
		$booking               = HKR_Bookings::create(
			$fields,
			array(
				'staff'          => true,
				'source'         => $source ? $source : 'phone',
				'status'         => in_array( $status, array( 'pending', 'confirmed', 'seated' ), true ) ? $status : 'confirmed',
				'notify_guest'   => (bool) $req->get_param( 'notify' ),
				'notify_staff'   => false,
				'internal_notes' => (string) $req->get_param( 'internal_notes' ),
			)
		);
		if ( is_wp_error( $booking ) ) {
			return self::error( $booking );
		}
		return rest_ensure_response( self::staff_booking( $booking ) );
	}
}
