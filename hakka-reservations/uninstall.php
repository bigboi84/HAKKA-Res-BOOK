<?php
/**
 * Runs when the plugin is deleted. Data is only removed if "Also delete all bookings,
 * locations and settings" is ticked in Reservations → Settings.
 *
 * @package HakkaReservations
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'hkr_hourly' );

$hkr_settings = get_option( 'hkr_settings', array() );
if ( empty( $hkr_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'locations', 'areas', 'blocks', 'bookings', 'booking_log', 'message_log' ) as $hkr_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'hkr_' . $hkr_table ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'staff_page_id', 'manage_page_id', 'test_page_id' ) as $hkr_key ) {
	if ( ! empty( $hkr_settings[ $hkr_key ] ) ) {
		wp_delete_post( (int) $hkr_settings[ $hkr_key ], true );
	}
}
delete_option( 'hkr_settings' );
delete_option( 'hkr_templates' );
delete_option( 'hkr_db_version' );
delete_metadata( 'user', 0, 'hkr_locations', '', true );
delete_metadata( 'user', 0, 'hkr_disabled', '', true );
remove_role( 'hkr_staff' );
remove_role( 'hkr_manager' );
$hkr_admin = get_role( 'administrator' );
if ( $hkr_admin ) {
	foreach ( array( 'hkr_view_bookings', 'hkr_edit_bookings', 'hkr_manage' ) as $hkr_cap ) {
		$hkr_admin->remove_cap( $hkr_cap );
	}
}
