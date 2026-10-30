<?php
/**
 * Admin: reports – bookings and guests per location, no-shows, cancellations, busiest times.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Admin_Reports {

	public static function init() {
		add_action( 'admin_post_hkr_export_report', array( __CLASS__, 'export' ) );
	}

	private static function range() {
		// phpcs:disable WordPress.Security.NonceVerification
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$loc  = isset( $_GET['location'] ) ? absint( $_GET['location'] ) : 0;
		// phpcs:enable
		if ( ! HKR_Availability::valid_date( $from ) ) {
			$from = hkr_now()->modify( '-29 days' )->format( 'Y-m-d' );
		}
		if ( ! HKR_Availability::valid_date( $to ) ) {
			$to = hkr_now()->format( 'Y-m-d' );
		}
		return array( $from, $to, $loc );
	}

	private static function data( $from, $to, $location_id ) {
		global $wpdb;
		$where  = 'start_at >= %s AND start_at <= %s';
		$params = array( $from . ' 00:00:00', $to . ' 23:59:59' );
		if ( $location_id ) {
			$where   .= ' AND location_id = %d';
			$params[] = $location_id;
		}
		$t = hkr_table( 'bookings' );

		$by_location = $wpdb->get_results( $wpdb->prepare( "SELECT location_id, status, COUNT(*) AS bookings, SUM(party_size) AS guests FROM $t WHERE $where GROUP BY location_id, status", $params ) );
		$by_day      = $wpdb->get_results( $wpdb->prepare( "SELECT substr(start_at,1,10) AS day, location_id, COUNT(*) AS bookings, SUM(party_size) AS guests FROM $t WHERE $where AND status NOT IN ('cancelled','declined') GROUP BY substr(start_at,1,10), location_id ORDER BY day ASC", $params ) );
		$busiest     = $wpdb->get_results( $wpdb->prepare( "SELECT substr(start_at,12,5) AS slot, COUNT(*) AS bookings, SUM(party_size) AS guests FROM $t WHERE $where AND status NOT IN ('cancelled','declined') GROUP BY substr(start_at,12,5) ORDER BY guests DESC LIMIT 10", $params ) );
		$by_type     = $wpdb->get_results( $wpdb->prepare( "SELECT location_id, area_name, COUNT(*) AS bookings, SUM(party_size) AS guests FROM $t WHERE $where AND status NOT IN ('cancelled','declined') GROUP BY location_id, area_name ORDER BY guests DESC", $params ) );

		$summary = array();
		foreach ( (array) $by_location as $row ) {
			$id = (int) $row->location_id;
			if ( ! isset( $summary[ $id ] ) ) {
				$summary[ $id ] = array( 'bookings' => 0, 'guests' => 0, 'no_show' => 0, 'cancelled' => 0, 'declined' => 0, 'pending' => 0 );
			}
			$summary[ $id ]['bookings'] += (int) $row->bookings;
			if ( ! in_array( $row->status, array( 'cancelled', 'declined' ), true ) ) {
				$summary[ $id ]['guests'] += (int) $row->guests;
			}
			if ( isset( $summary[ $id ][ $row->status ] ) ) {
				$summary[ $id ][ $row->status ] += (int) $row->bookings;
			}
		}
		return compact( 'summary', 'by_day', 'busiest', 'by_type' );
	}

	public static function page() {
		list( $from, $to, $location_id ) = self::range();
		$data  = self::data( $from, $to, $location_id );
		$names = array();
		foreach ( HKR_Locations::all() as $loc ) {
			$names[ $loc['id'] ] = $loc['name'];
		}
		$pct = static function ( $part, $whole ) {
			return $whole ? round( 100 * $part / $whole ) . '%' : '–';
		};
		?>
		<div class="wrap hkr-wrap">
			<h1><?php esc_html_e( 'Reports', 'hakka-reservations' ); ?></h1>
			<form method="get" class="hkr-filters">
				<input type="hidden" name="page" value="hkr-reports">
				<select name="location">
					<option value="0"><?php esc_html_e( 'All locations', 'hakka-reservations' ); ?></option>
					<?php foreach ( $names as $id => $name ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $location_id, $id ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'From', 'hakka-reservations' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
				<label><?php esc_html_e( 'To', 'hakka-reservations' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
				<button class="button"><?php esc_html_e( 'Show', 'hakka-reservations' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hkr_export_report&from=' . $from . '&to=' . $to . '&location=' . $location_id ), 'hkr_export_report' ) ); ?>"><?php esc_html_e( 'Export CSV (by day)', 'hakka-reservations' ); ?></a>
			</form>

			<div class="hkr-cards">
				<?php foreach ( $data['summary'] as $id => $s ) : ?>
					<div class="hkr-panel hkr-card-stat">
						<h2><?php echo esc_html( isset( $names[ $id ] ) ? $names[ $id ] : '#' . $id ); ?></h2>
						<p class="hkr-big"><?php echo esc_html( number_format_i18n( $s['guests'] ) ); ?> <span><?php esc_html_e( 'guests', 'hakka-reservations' ); ?></span></p>
						<p><?php echo esc_html( sprintf( /* translators: %d: bookings */ _n( '%d booking', '%d bookings', $s['bookings'], 'hakka-reservations' ), $s['bookings'] ) ); ?></p>
						<p><?php echo esc_html( sprintf( /* translators: 1: count, 2: percent */ __( 'No-shows: %1$d (%2$s)', 'hakka-reservations' ), $s['no_show'], $pct( $s['no_show'], $s['bookings'] ) ) ); ?><br>
						<?php echo esc_html( sprintf( /* translators: 1: count, 2: percent */ __( 'Cancelled: %1$d (%2$s)', 'hakka-reservations' ), $s['cancelled'], $pct( $s['cancelled'], $s['bookings'] ) ) ); ?><br>
						<?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Declined: %d', 'hakka-reservations' ), $s['declined'] ) ); ?></p>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $data['summary'] ) : ?>
					<p><?php esc_html_e( 'No bookings in this period.', 'hakka-reservations' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="hkr-columns">
				<section class="hkr-panel">
					<h2><?php esc_html_e( 'Busiest times', 'hakka-reservations' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Bookings', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Guests', 'hakka-reservations' ); ?></th></tr></thead><tbody>
					<?php foreach ( (array) $data['busiest'] as $row ) : ?>
						<tr><td><?php echo esc_html( hkr_format_time( $row->slot ) ); ?></td><td><?php echo (int) $row->bookings; ?></td><td><?php echo (int) $row->guests; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</section>
				<section class="hkr-panel">
					<h2><?php esc_html_e( 'By booking type', 'hakka-reservations' ); ?></h2>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Location', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Type', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Bookings', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Guests', 'hakka-reservations' ); ?></th></tr></thead><tbody>
					<?php foreach ( (array) $data['by_type'] as $row ) : ?>
						<tr><td><?php echo esc_html( isset( $names[ (int) $row->location_id ] ) ? $names[ (int) $row->location_id ] : '' ); ?></td><td><?php echo esc_html( $row->area_name ? $row->area_name : '—' ); ?></td><td><?php echo (int) $row->bookings; ?></td><td><?php echo (int) $row->guests; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</section>
			</div>

			<section class="hkr-panel">
				<h2><?php esc_html_e( 'By day', 'hakka-reservations' ); ?></h2>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Location', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Bookings', 'hakka-reservations' ); ?></th><th><?php esc_html_e( 'Guests', 'hakka-reservations' ); ?></th></tr></thead><tbody>
				<?php foreach ( (array) $data['by_day'] as $row ) : ?>
					<tr><td><?php echo esc_html( hkr_format_date( $row->day, 'D j M Y' ) ); ?></td><td><?php echo esc_html( isset( $names[ (int) $row->location_id ] ) ? $names[ (int) $row->location_id ] : '' ); ?></td><td><?php echo (int) $row->bookings; ?></td><td><?php echo (int) $row->guests; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</section>
		</div>
		<?php
	}

	public static function export() {
		HKR_Admin::check( 'hkr_manage', 'hkr_export_report' );
		list( $from, $to, $location_id ) = self::range();
		$data  = self::data( $from, $to, $location_id );
		$names = array();
		foreach ( HKR_Locations::all() as $loc ) {
			$names[ $loc['id'] ] = $loc['name'];
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=hakka-report-' . $from . '-to-' . $to . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date', 'Location', 'Bookings', 'Guests' ) );
		foreach ( (array) $data['by_day'] as $row ) {
			fputcsv( $out, array( $row->day, isset( $names[ (int) $row->location_id ] ) ? $names[ (int) $row->location_id ] : '', (int) $row->bookings, (int) $row->guests ) );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
