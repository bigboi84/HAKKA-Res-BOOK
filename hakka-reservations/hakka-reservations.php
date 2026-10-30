<?php
/**
 * Plugin Name:       HAKKA Reservations
 * Description:       Table bookings for HAKKA Port of Spain and HAKKA Brentwood: per-location hours, time slots, booking types with capacity, staff confirmation screen and branded emails routed to each location.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            HAKKA Restaurant
 * Text Domain:       hakka-reservations
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'HKR_VERSION', '1.0.6' );
define( 'HKR_DB_VERSION', '1.0.2' );
define( 'HKR_FILE', __FILE__ );
define( 'HKR_DIR', plugin_dir_path( __FILE__ ) );
define( 'HKR_URL', plugin_dir_url( __FILE__ ) );

require_once HKR_DIR . 'includes/functions.php';
require_once HKR_DIR . 'includes/class-hkr-settings.php';
require_once HKR_DIR . 'includes/class-hkr-install.php';
require_once HKR_DIR . 'includes/class-hkr-locations.php';
require_once HKR_DIR . 'includes/class-hkr-availability.php';
require_once HKR_DIR . 'includes/class-hkr-blocks.php';
require_once HKR_DIR . 'includes/class-hkr-bookings.php';
require_once HKR_DIR . 'includes/class-hkr-mailer.php';
require_once HKR_DIR . 'includes/class-hkr-roles.php';
require_once HKR_DIR . 'includes/class-hkr-rest.php';
require_once HKR_DIR . 'includes/class-hkr-frontend.php';
require_once HKR_DIR . 'includes/class-hkr-cron.php';

if ( is_admin() ) {
	require_once HKR_DIR . 'admin/class-hkr-admin.php';
	require_once HKR_DIR . 'admin/class-hkr-admin-bookings.php';
	require_once HKR_DIR . 'admin/class-hkr-admin-locations.php';
	require_once HKR_DIR . 'admin/class-hkr-admin-settings.php';
	require_once HKR_DIR . 'admin/class-hkr-admin-staff.php';
	require_once HKR_DIR . 'admin/class-hkr-admin-reports.php';
}

register_activation_hook( __FILE__, array( 'HKR_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HKR_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'hakka-reservations', false, dirname( plugin_basename( HKR_FILE ) ) . '/languages' );
		HKR_Install::maybe_upgrade();
		HKR_Roles::init();
		HKR_Rest::init();
		HKR_Frontend::init();
		HKR_Cron::init();
		if ( is_admin() ) {
			HKR_Admin::init();
		}
	}
);
