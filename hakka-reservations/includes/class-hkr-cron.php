<?php
/**
 * Hourly jobs: guest reminders and optional clean-up of old guest details.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Cron {

	const HOOK = 'hkr_hourly';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run() {
		self::send_reminders();
		self::apply_retention();
		HKR_Blocks::cleanup();
		self::clean_tmp();
	}

	/**
	 * Remind confirmed guests before their booking (each location sets how many hours before).
	 */
	public static function send_reminders() {
		global $wpdb;
		$now = hkr_now();
		foreach ( HKR_Locations::all() as $loc ) {
			$hours = (int) $loc['settings']['reminder_hours'];
			if ( $hours <= 0 ) {
				continue;
			}
			$until = $now->modify( '+' . $hours . ' hours' )->format( 'Y-m-d H:i:s' );
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id FROM ' . hkr_table( 'bookings' ) . " WHERE location_id = %d AND status = 'confirmed' AND reminder_sent_at IS NULL AND guest_email <> '' AND start_at > %s AND start_at <= %s LIMIT 100",
					$loc['id'],
					$now->format( 'Y-m-d H:i:s' ),
					$until
				)
			);
			foreach ( (array) $rows as $row ) {
				$booking = HKR_Bookings::get( $row->id );
				$token   = HKR_Bookings::refresh_token( $booking['id'] );
				HKR_Mailer::send( 'guest_reminder', HKR_Bookings::get( $booking['id'] ), array( 'token' => $token ) );
				$wpdb->update( hkr_table( 'bookings' ), array( 'reminder_sent_at' => $now->format( 'Y-m-d H:i:s' ) ), array( 'id' => $booking['id'] ) );
			}
		}
	}

	/**
	 * Remove guest contact details from bookings older than the retention period (0 = keep).
	 */
	public static function apply_retention() {
		global $wpdb;
		$months = (int) HKR_Settings::get( 'retention_months' );
		if ( $months <= 0 ) {
			return;
		}
		$before = hkr_now()->modify( '-' . $months . ' months' )->format( 'Y-m-d H:i:s' );
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . hkr_table( 'bookings' ) . " SET guest_name = 'Removed', guest_phone = '', guest_email = '', celebration_details = '', dietary_notes = '', special_requests = '', ip_hash = '' WHERE end_at < %s AND guest_name <> 'Removed'",
				$before
			)
		);
	}

	/**
	 * Remove calendar files left behind if a send failed part way.
	 */
	private static function clean_tmp() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'hkr-tmp';
		foreach ( (array) glob( $dir . '/*.ics' ) as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < time() - HOUR_IN_SECONDS ) {
				wp_delete_file( $file );
			}
		}
	}
}
