<?php
/**
 * Global settings and email templates, stored as two options.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Settings {

	const OPTION    = 'hkr_settings';
	const TEMPLATES = 'hkr_templates';

	private static $cache = null;

	public static function defaults() {
		return array(
			'business_name'       => 'HAKKA Restaurant',
			'from_name'           => 'HAKKA Restaurant',
			'from_email'          => 'booking@hakkarestaurant.com',
			'reply_to'            => 'booking@hakkarestaurant.com',
			'country_code'        => '1868',
			'logo_id'             => 0,
			'default_header_id'   => 0,
			'color_primary'       => '#dd3333',
			'color_dark'          => '#111111',
			'terms_text'          => 'Please arrive on time. Tables are held for 15 minutes. To cancel or change your booking, use the link in your email or call us.',
			'terms_page_id'       => 0,
			'celebration_options' => "Birthday\nAnniversary",
			'field_seating'       => 'off',
			'seating_options'     => "Indoor\nBar\nNo preference",
			'field_high_chair'    => 'optional',
			'field_dietary'       => 'optional',
			'field_requests'      => 'optional',
			'show_spots_left'     => 1,
			'rate_limit'          => 5,
			'turnstile_site'      => '',
			'turnstile_secret'    => '',
			'staff_page_id'       => 0,
			'manage_page_id'      => 0,
			'test_page_id'        => 0,
			'staff_session_hours' => 12,
			'staff_poll_seconds'  => 30,
			'hide_usernames'      => 1,
			'retention_months'    => 0,
			'delete_on_uninstall' => 0,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$merged = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged, false );
		self::$cache = null;
	}

	/**
	 * Celebration choices shown to guests (None and Other are added by the form).
	 */
	public static function celebration_options() {
		return hkr_lines( self::get( 'celebration_options' ) );
	}

	public static function seating_options() {
		return hkr_lines( self::get( 'seating_options' ) );
	}

	/**
	 * Email template definitions: who receives it and its editable text.
	 */
	public static function template_types() {
		return array(
			'guest_received'  => array(
				'label'    => __( 'Guest: request received', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'guest_confirmed' => array(
				'label'    => __( 'Guest: booking confirmed', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'guest_declined'  => array(
				'label'    => __( 'Guest: declined / new time suggested', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'guest_cancelled' => array(
				'label'    => __( 'Guest: booking cancelled', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'guest_changed'   => array(
				'label'    => __( 'Guest: booking changed', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'guest_reminder'  => array(
				'label'    => __( 'Guest: reminder', 'hakka-reservations' ),
				'audience' => 'guest',
			),
			'staff_new'       => array(
				'label'    => __( 'Staff: new booking', 'hakka-reservations' ),
				'audience' => 'staff',
			),
			'staff_changed'   => array(
				'label'    => __( 'Staff: guest cancelled or asked for a change', 'hakka-reservations' ),
				'audience' => 'staff',
			),
		);
	}

	public static function template_defaults() {
		return array(
			'guest_received'  => array(
				'subject' => 'We have your booking request – {location_name}, {date_short} at {time}',
				'heading' => 'Booking request received',
				'banner'  => 'We will call you to confirm your booking.',
				'body'    => '<p>Hi {guest_first_name},</p><p>Thank you for choosing {location_name}. We have received your booking request. Our team will call you on <strong>{guest_phone}</strong> to confirm it. Your table isn\'t guaranteed until we do.</p>',
			),
			'guest_confirmed' => array(
				'subject' => 'Confirmed: {location_name}, {date_short} at {time}',
				'heading' => 'Your booking is confirmed',
				'banner'  => 'See you soon!',
				'body'    => '<p>Hi {guest_first_name},</p><p>Great news: your table at {location_name} is confirmed. We look forward to welcoming you.</p>',
			),
			'guest_declined'  => array(
				'subject' => 'About your booking at {location_name} on {date_short}',
				'heading' => 'We couldn\'t confirm your booking',
				'banner'  => 'Please get in touch to choose another time.',
				'body'    => '<p>Hi {guest_first_name},</p><p>We\'re sorry, we couldn\'t confirm your booking for {date} at {time}.</p>{staff_message}<p>Please call us on {location_phone} or book another time on our website.</p>',
			),
			'guest_cancelled' => array(
				'subject' => 'Cancelled: {location_name}, {date_short} at {time}',
				'heading' => 'Your booking has been cancelled',
				'banner'  => 'We hope to see you another time.',
				'body'    => '<p>Hi {guest_first_name},</p><p>Your booking at {location_name} for {date} at {time} has been cancelled.</p>{staff_message}',
			),
			'guest_changed'   => array(
				'subject' => 'Updated: {location_name}, {date_short} at {time}',
				'heading' => 'Your booking has been updated',
				'banner'  => 'Here are your new booking details.',
				'body'    => '<p>Hi {guest_first_name},</p><p>Your booking at {location_name} has been updated. The new details are below.</p>{staff_message}',
			),
			'guest_reminder'  => array(
				'subject' => 'Reminder: {location_name}, {date_short} at {time}',
				'heading' => 'See you soon',
				'banner'  => 'This is a reminder of your booking.',
				'body'    => '<p>Hi {guest_first_name},</p><p>Just a reminder that we look forward to welcoming you at {location_name} on {date} at {time}.</p>',
			),
			'staff_new'       => array(
				'subject' => 'New booking: {date_short}, {time} · {party_size} guests · {booking_type} · {guest_name} ({booking_ref})',
				'heading' => 'New booking request',
				'banner'  => 'Call the guest, then confirm the booking.',
				'body'    => '<p>A new booking request has come in for <strong>{location_name}</strong>. It is <strong>pending</strong> until you confirm it.</p>',
			),
			'staff_changed'   => array(
				'subject' => 'Booking update: {guest_name} ({booking_ref}) · {date_short}, {time}',
				'heading' => 'A guest updated their booking',
				'banner'  => '{change_summary}',
				'body'    => '<p>{change_summary}</p>{guest_message}',
			),
		);
	}

	public static function templates() {
		$saved    = get_option( self::TEMPLATES, array() );
		$defaults = self::template_defaults();
		$out      = array();
		foreach ( $defaults as $type => $tpl ) {
			$out[ $type ] = wp_parse_args( isset( $saved[ $type ] ) && is_array( $saved[ $type ] ) ? $saved[ $type ] : array(), $tpl );
		}
		return $out;
	}

	public static function template( $type ) {
		$all = self::templates();
		return isset( $all[ $type ] ) ? $all[ $type ] : null;
	}
}
