<?php
/**
 * wp-admin menu and shared admin helpers.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		HKR_Admin_Bookings::init();
		HKR_Admin_Locations::init();
		HKR_Admin_Settings::init();
		HKR_Admin_Staff::init();
		HKR_Admin_Reports::init();
	}

	public static function menu() {
		$pending = HKR_Bookings::query(
			array(
				'status'    => 'pending',
				'date_from' => hkr_now()->format( 'Y-m-d' ),
				'limit'     => 0,
			)
		);
		$badge = $pending['total'] ? ' <span class="awaiting-mod">' . (int) $pending['total'] . '</span>' : '';

		add_menu_page( __( 'Reservations', 'hakka-reservations' ), __( 'Reservations', 'hakka-reservations' ) . $badge, 'hkr_edit_bookings', 'hkr-bookings', array( 'HKR_Admin_Bookings', 'page' ), 'dashicons-food', 26 );
		add_submenu_page( 'hkr-bookings', __( 'Bookings', 'hakka-reservations' ), __( 'Bookings', 'hakka-reservations' ), 'hkr_edit_bookings', 'hkr-bookings', array( 'HKR_Admin_Bookings', 'page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Add booking', 'hakka-reservations' ), __( 'Add booking', 'hakka-reservations' ), 'hkr_edit_bookings', 'hkr-booking-edit', array( 'HKR_Admin_Bookings', 'edit_page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Locations', 'hakka-reservations' ), __( 'Locations', 'hakka-reservations' ), 'hkr_manage', 'hkr-locations', array( 'HKR_Admin_Locations', 'page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Booking staff', 'hakka-reservations' ), __( 'Booking staff', 'hakka-reservations' ), 'hkr_manage', 'hkr-staff', array( 'HKR_Admin_Staff', 'page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Messages & Email', 'hakka-reservations' ), __( 'Messages & Email', 'hakka-reservations' ), 'hkr_manage', 'hkr-messages', array( 'HKR_Admin_Settings', 'messages_page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Settings', 'hakka-reservations' ), __( 'Settings', 'hakka-reservations' ), 'hkr_manage', 'hkr-settings', array( 'HKR_Admin_Settings', 'page' ) );
		add_submenu_page( 'hkr-bookings', __( 'Reports', 'hakka-reservations' ), __( 'Reports', 'hakka-reservations' ), 'hkr_manage', 'hkr-reports', array( 'HKR_Admin_Reports', 'page' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'hkr-' ) ) {
			return;
		}
		wp_enqueue_style( 'hkr-admin', HKR_URL . 'assets/css/admin.css', array(), HKR_VERSION );
		wp_enqueue_script( 'hkr-admin', HKR_URL . 'assets/js/admin.js', array( 'jquery' ), HKR_VERSION, true );
		wp_localize_script(
			'hkr-admin',
			'hkrAdmin',
			array(
				'api'          => esc_url_raw( rest_url( HKR_Rest::NS ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'chooseImage'  => __( 'Choose image', 'hakka-reservations' ),
				'useImage'     => __( 'Use this image', 'hakka-reservations' ),
				'removeConfirm' => __( 'Remove this row?', 'hakka-reservations' ),
				'noSlots'      => __( 'No times on this day.', 'hakka-reservations' ),
			)
		);
		if ( false !== strpos( (string) $hook, 'hkr-locations' ) || false !== strpos( (string) $hook, 'hkr-settings' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Redirect back to a screen with a message (shown by notices()).
	 */
	public static function redirect( $url, $message = '', $type = 'success' ) {
		if ( $message ) {
			set_transient( 'hkr_notice_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 60 );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'hkr-' ) ) {
			return;
		}
		$key    = 'hkr_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ), esc_html( $notice['message'] ) );
		}
	}

	public static function check( $cap, $nonce_action ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'hakka-reservations' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Media-library picker for an image ID field.
	 */
	public static function image_field( $name, $attachment_id, $help = '', $field_id = '' ) {
		$url   = $attachment_id ? wp_get_attachment_image_url( (int) $attachment_id, 'medium' ) : '';
		$large = $attachment_id ? wp_get_attachment_image_url( (int) $attachment_id, 'large' ) : '';
		?>
		<div class="hkr-image-field"<?php echo $field_id ? ' id="' . esc_attr( $field_id ) . '"' : ''; ?> data-large="<?php echo esc_url( $large ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (int) $attachment_id ); ?>" class="hkr-image-id">
			<div class="hkr-image-preview"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></div>
			<button type="button" class="button hkr-image-choose"><?php esc_html_e( 'Choose image', 'hakka-reservations' ); ?></button>
			<button type="button" class="button-link hkr-image-remove" <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'hakka-reservations' ); ?></button>
			<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Drag-to-position previews (desktop and phone) for a booking form header photo.
	 *
	 * @param string $name       Input name prefix, e.g. "hero_frame" or "areas[3][frame]".
	 * @param array  $frame      Saved frame (see hkr_default_frame()).
	 * @param string $source     ID of the image field that holds the photo.
	 * @param string $mob_source ID of an optional separate phone photo field.
	 * @param array  $sample     Text drawn over the preview: array( title, line ).
	 */
	public static function frame_editor( $name, $frame, $source, $mob_source = '', $sample = array() ) {
		$f      = hkr_sanitize_frame( $frame );
		$sample = array_values( (array) $sample ) + array( '', '' );
		$views  = array(
			'd' => array( __( 'Desktop and tablet', 'hakka-reservations' ), 'x', 'y', 'z' ),
			'm' => array( __( 'Phone', 'hakka-reservations' ), 'mx', 'my', 'mz' ),
		);
		?>
		<div class="hkr-framer" data-source="<?php echo esc_attr( $source ); ?>" data-mob-source="<?php echo esc_attr( $mob_source ); ?>">
			<p class="hkr-framer__empty"><?php esc_html_e( 'Choose a photo first, then drag it here to choose which part shows.', 'hakka-reservations' ); ?></p>
			<div class="hkr-framer__views">
				<?php foreach ( $views as $mode => $v ) : ?>
					<div class="hkr-framer__view hkr-framer__view--<?php echo esc_attr( $mode ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>">
						<p class="hkr-framer__label"><?php echo esc_html( $v[0] ); ?></p>
						<div class="hkr-framer__frame" tabindex="0" role="application" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Desktop or Phone */ __( '%s preview. Drag the photo, or use the arrow keys, to move it.', 'hakka-reservations' ), $v[0] ) ); ?>">
							<img alt="" draggable="false">
							<span class="hkr-framer__shade"></span>
							<span class="hkr-framer__sample"><strong><?php echo esc_html( $sample[0] ); ?></strong><span><?php echo esc_html( $sample[1] ); ?></span></span>
							<span class="hkr-framer__hint"><?php esc_html_e( 'Drag to move', 'hakka-reservations' ); ?></span>
						</div>
						<div class="hkr-framer__tools">
							<label><?php esc_html_e( 'Zoom', 'hakka-reservations' ); ?> <input type="range" min="100" max="250" step="1" class="hkr-framer__zoom" value="<?php echo esc_attr( $f[ $v[3] ] ); ?>"></label>
							<button type="button" class="button-link hkr-framer__reset"><?php esc_html_e( 'Reset', 'hakka-reservations' ); ?></button>
						</div>
						<input type="hidden" class="hkr-framer__x" name="<?php echo esc_attr( $name . '[' . $v[1] . ']' ); ?>" value="<?php echo esc_attr( $f[ $v[1] ] ); ?>">
						<input type="hidden" class="hkr-framer__y" name="<?php echo esc_attr( $name . '[' . $v[2] . ']' ); ?>" value="<?php echo esc_attr( $f[ $v[2] ] ); ?>">
						<input type="hidden" class="hkr-framer__z" name="<?php echo esc_attr( $name . '[' . $v[3] . ']' ); ?>" value="<?php echo esc_attr( $f[ $v[3] ] ); ?>">
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
