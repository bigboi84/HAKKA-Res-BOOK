<?php
/**
 * Activation: tables, roles, pages and the two HAKKA locations.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Install {

	public static function activate() {
		self::create_tables();
		HKR_Roles::add_roles();
		self::seed_locations();
		self::create_pages();
		HKR_Cron::schedule();
		update_option( 'hkr_db_version', HKR_DB_VERSION, false );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		HKR_Cron::unschedule();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hkr_db_version' ) !== HKR_DB_VERSION ) {
			self::create_tables();
			HKR_Roles::add_roles();
			update_option( 'hkr_db_version', HKR_DB_VERSION, false );
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = 'CREATE TABLE ' . hkr_table( 'locations' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  slug varchar(100) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  accepts_bookings tinyint(1) NOT NULL DEFAULT 1,
  address text NULL,
  phone varchar(50) NOT NULL DEFAULT '',
  map_url text NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  header_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  header_image_alt varchar(255) NOT NULL DEFAULT '',
  email_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  alert_emails text NULL,
  from_name varchar(190) NOT NULL DEFAULT '',
  from_email varchar(190) NOT NULL DEFAULT '',
  reply_to varchar(190) NOT NULL DEFAULT '',
  bcc varchar(190) NOT NULL DEFAULT '',
  confirm_note text NULL,
  settings longtext NULL,
  hours longtext NULL,
  special_dates longtext NULL,
  whatsapp_phone_id varchar(100) NOT NULL DEFAULT '',
  created_at datetime NULL,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;";

		$sql[] = 'CREATE TABLE ' . hkr_table( 'areas' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  location_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(190) NOT NULL DEFAULT '',
  description text NULL,
  capacity int(11) NULL,
  hard_max int(11) NULL,
  min_party int(11) NOT NULL DEFAULT 1,
  max_party int(11) NULL,
  ask_celebration tinyint(1) NOT NULL DEFAULT 1,
  available_days varchar(20) NOT NULL DEFAULT '',
  image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  image_frame varchar(255) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY location_id (location_id)
) $charset;";

		$sql[] = 'CREATE TABLE ' . hkr_table( 'blocks' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  location_id bigint(20) unsigned NOT NULL DEFAULT 0,
  area_id bigint(20) unsigned NOT NULL DEFAULT 0,
  block_date date NOT NULL,
  time_from varchar(5) NOT NULL DEFAULT '',
  time_to varchar(5) NOT NULL DEFAULT '',
  note varchar(190) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY location_date (location_id,block_date)
) $charset;";

		$sql[] = 'CREATE TABLE ' . hkr_table( 'bookings' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(20) NOT NULL DEFAULT '',
  location_id bigint(20) unsigned NOT NULL DEFAULT 0,
  area_id bigint(20) unsigned NOT NULL DEFAULT 0,
  area_name varchar(190) NOT NULL DEFAULT '',
  start_at datetime NOT NULL,
  end_at datetime NOT NULL,
  party_size int(11) NOT NULL DEFAULT 1,
  status varchar(20) NOT NULL DEFAULT 'pending',
  source varchar(20) NOT NULL DEFAULT 'web',
  guest_name varchar(190) NOT NULL DEFAULT '',
  guest_phone varchar(50) NOT NULL DEFAULT '',
  guest_email varchar(190) NOT NULL DEFAULT '',
  celebration_type varchar(100) NOT NULL DEFAULT '',
  celebration_text varchar(190) NOT NULL DEFAULT '',
  celebration_details text NULL,
  seating_pref varchar(100) NOT NULL DEFAULT '',
  high_chair tinyint(1) NOT NULL DEFAULT 0,
  dietary_notes text NULL,
  special_requests text NULL,
  internal_notes text NULL,
  whatsapp_optin tinyint(1) NOT NULL DEFAULT 0,
  manage_token varchar(64) NOT NULL DEFAULT '',
  reminder_sent_at datetime NULL,
  confirmed_at datetime NULL,
  confirmed_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  ip_hash varchar(64) NOT NULL DEFAULT '',
  created_at datetime NULL,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY ref (ref),
  KEY location_start (location_id,start_at),
  KEY area_start (area_id,start_at),
  KEY status (status)
) $charset;";

		$sql[] = 'CREATE TABLE ' . hkr_table( 'booking_log' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(60) NOT NULL DEFAULT '',
  old_value longtext NULL,
  new_value longtext NULL,
  actor varchar(190) NOT NULL DEFAULT '',
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY booking_id (booking_id)
) $charset;";

		$sql[] = 'CREATE TABLE ' . hkr_table( 'message_log' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
  channel varchar(20) NOT NULL DEFAULT 'email',
  template varchar(40) NOT NULL DEFAULT '',
  recipient text NULL,
  status varchar(20) NOT NULL DEFAULT '',
  error text NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY booking_id (booking_id)
) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Create HAKKA Port of Spain and HAKKA Brentwood the first time the plugin runs.
	 * Everything here can be changed afterwards in Reservations → Locations.
	 */
	public static function seed_locations() {
		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . hkr_table( 'locations' ) );
		if ( $count > 0 ) {
			return;
		}

		$base_settings = HKR_Locations::default_settings();

		// Weekdays use ISO numbering: 1 = Monday … 7 = Sunday.
		$pos_id = HKR_Locations::save(
			array(
				'name'         => 'HAKKA Port of Spain',
				'slug'         => 'port-of-spain',
				'address'      => '101 Queen Street, Port of Spain',
				'phone'        => '+18682547426',
				'map_url'      => 'https://maps.app.goo.gl/hPLKozLHLcosXHbn9',
				'sort_order'   => 1,
				'alert_emails' => 'queenstreet@hakkarestaurant.com',
				'settings'     => array_merge( $base_settings, array( 'max_covers' => '' ) ),
				'hours'        => array(
					1 => array( array( '10:00', '21:00' ) ),
					2 => array( array( '10:00', '21:00' ) ),
					3 => array( array( '10:00', '21:00' ) ),
					4 => array( array( '10:00', '21:00' ) ),
					5 => array( array( '10:00', '21:00' ) ),
					6 => array( array( '11:00', '21:00' ) ),
					7 => array(),
				),
			)
		);
		HKR_Locations::save_areas(
			$pos_id,
			array(
				array( 'name' => 'Private Room', 'description' => 'Private dining room', 'capacity' => 30, 'max_party' => 30, 'sort_order' => 1 ),
				array( 'name' => 'Bar', 'description' => '', 'capacity' => 12, 'max_party' => 12, 'sort_order' => 2 ),
				array( 'name' => 'Dining area', 'description' => '', 'capacity' => 48, 'sort_order' => 3 ),
			)
		);

		$brentwood_id = HKR_Locations::save(
			array(
				'name'         => 'HAKKA Brentwood',
				'slug'         => 'brentwood',
				'address'      => '200 South Brentwood Avenue, Chaguanas',
				'phone'        => '+18683342552',
				'map_url'      => 'https://maps.app.goo.gl/bJTW3FgporBSnryh9',
				'sort_order'   => 2,
				'alert_emails' => 'booking@hakkarestaurant.com',
				'settings'     => array_merge( $base_settings, array( 'max_covers' => 30 ) ),
				'hours'        => array(
					1 => array( array( '11:00', '23:00' ) ),
					2 => array( array( '11:00', '23:00' ) ),
					3 => array( array( '11:00', '23:00' ) ),
					4 => array( array( '11:00', '23:00' ) ),
					5 => array( array( '11:00', '24:00' ) ),
					6 => array( array( '11:00', '24:00' ) ),
					7 => array( array( '11:00', '23:00' ) ),
				),
			)
		);
		HKR_Locations::save_areas(
			$brentwood_id,
			array(
				array( 'name' => 'Dining', 'description' => '', 'sort_order' => 1 ),
				array( 'name' => 'Event', 'description' => '', 'sort_order' => 2 ),
				array( 'name' => 'Corporate Event', 'description' => '', 'sort_order' => 3 ),
			)
		);
	}

	/**
	 * The staff screen, the guest "manage booking" page and the hidden test page.
	 */
	public static function create_pages() {
		$pages = array(
			'staff_page_id'  => array(
				'title'   => __( 'Staff Bookings', 'hakka-reservations' ),
				'slug'    => 'staff-bookings',
				'content' => '[hakka_staff_bookings]',
			),
			'manage_page_id' => array(
				'title'   => __( 'Manage Your Booking', 'hakka-reservations' ),
				'slug'    => 'manage-booking',
				'content' => '[hakka_manage_booking]',
			),
			'test_page_id'   => array(
				'title'   => __( 'Reservations (Test)', 'hakka-reservations' ),
				'slug'    => 'reservations-test',
				'content' => '[hakka_booking]',
			),
		);
		$updates = array();
		foreach ( $pages as $key => $page ) {
			$existing = (int) HKR_Settings::get( $key );
			if ( $existing && get_post( $existing ) && 'trash' !== get_post_status( $existing ) ) {
				continue;
			}
			$found = get_page_by_path( $page['slug'] );
			if ( $found ) {
				$updates[ $key ] = (int) $found->ID;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $page['title'],
					'post_name'      => $page['slug'],
					'post_content'   => $page['content'],
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$updates[ $key ] = (int) $id;
			}
		}
		if ( $updates ) {
			HKR_Settings::update( $updates );
		}
	}
}
