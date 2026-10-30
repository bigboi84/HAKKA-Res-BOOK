<?php
/**
 * Booking emails: branded HTML layout, routing by location, calendar file and logging.
 *
 * Guest emails go to the guest, sent from the global sender (booking@…) unless the
 * location overrides it. Staff emails go only to the booking location's alert address.
 *
 * @package HakkaReservations
 */

defined( 'ABSPATH' ) || exit;

class HKR_Mailer {

	private static $alt_body = '';

	/**
	 * Send one template for a booking.
	 *
	 * @param string $type    Template key (see HKR_Settings::template_types()).
	 * @param array  $booking Booking.
	 * @param array  $extra   token, staff_message, change_summary, guest_message, to (override recipients, for tests).
	 * @return bool
	 */
	public static function send( $type, $booking, array $extra = array() ) {
		$types = HKR_Settings::template_types();
		if ( ! isset( $types[ $type ] ) || ! $booking ) {
			return false;
		}
		$loc      = HKR_Locations::get( $booking['location_id'] );
		$audience = $types[ $type ]['audience'];

		if ( isset( $extra['to'] ) ) {
			$to = hkr_parse_emails( $extra['to'] );
		} elseif ( 'guest' === $audience ) {
			$to = $booking['guest_email'] ? array( $booking['guest_email'] ) : array();
		} else {
			$to = $loc ? hkr_parse_emails( $loc['alert_emails'] ) : array();
			if ( ! $to ) {
				$to = array( HKR_Settings::get( 'from_email' ) );
			}
		}
		if ( ! $to ) {
			return false;
		}

		$rendered = self::render( $type, $booking, $extra );
		$sender   = HKR_Locations::sender( $loc );
		$headers  = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::header_safe( $sender['from_name'] ), $sender['from_email'] ),
		);
		if ( 'guest' === $audience ) {
			$headers[] = 'Reply-To: ' . $sender['reply_to'];
			foreach ( $sender['bcc'] as $bcc ) {
				$headers[] = 'Bcc: ' . $bcc;
			}
		} elseif ( $booking['guest_email'] ) {
			// Staff can reply straight to the guest.
			$headers[] = sprintf( 'Reply-To: %s <%s>', self::header_safe( $booking['guest_name'] ), $booking['guest_email'] );
		}

		$attachments = array();
		if ( 'guest' === $audience && in_array( $type, array( 'guest_confirmed', 'guest_changed', 'guest_reminder' ), true ) && 'confirmed' === $booking['status'] ) {
			$ics = self::write_ics( $booking, $loc );
			if ( $ics ) {
				$attachments[] = $ics;
			}
		}

		self::$alt_body = $rendered['text'];
		add_action( 'phpmailer_init', array( __CLASS__, 'set_alt_body' ) );
		$sent  = wp_mail( $to, $rendered['subject'], $rendered['html'], $headers, $attachments );
		remove_action( 'phpmailer_init', array( __CLASS__, 'set_alt_body' ) );
		self::$alt_body = '';

		foreach ( $attachments as $file ) {
			wp_delete_file( $file );
		}

		global $wpdb;
		$wpdb->insert(
			hkr_table( 'message_log' ),
			array(
				'booking_id' => (int) $booking['id'],
				'channel'    => 'email',
				'template'   => $type,
				'recipient'  => implode( ', ', $to ),
				'status'     => $sent ? 'sent' : 'failed',
				'error'      => $sent ? '' : self::last_error(),
				'created_at' => hkr_now()->format( 'Y-m-d H:i:s' ),
			)
		);
		return (bool) $sent;
	}

	public static function set_alt_body( $phpmailer ) {
		if ( self::$alt_body && property_exists( $phpmailer, 'AltBody' ) ) {
			$phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
	}

	private static function last_error() {
		global $phpmailer;
		if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			return (string) $phpmailer->ErrorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
		return 'wp_mail returned false';
	}

	private static function header_safe( $text ) {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $text ) );
	}

	/**
	 * Values for the {placeholders}. Text values are plain; HTML-ready ones are built in render().
	 */
	public static function placeholders( $booking, $loc, array $extra = array() ) {
		$first       = trim( strtok( (string) $booking['guest_name'], ' ' ) );
		$manage      = ! empty( $extra['token'] ) ? HKR_Bookings::manage_url( $booking, $extra['token'] ) : '';
		$celebration = self::celebration_label( $booking );
		return array(
			'{guest_name}'        => $booking['guest_name'],
			'{guest_first_name}'  => $first ? $first : $booking['guest_name'],
			'{guest_phone}'       => hkr_display_phone( $booking['guest_phone'] ),
			'{guest_email}'       => $booking['guest_email'],
			'{party_size}'        => (string) $booking['party_size'],
			'{date}'              => hkr_format_date( $booking['date'] ),
			'{date_short}'        => hkr_format_date( $booking['date'], 'D j M' ),
			'{time}'              => hkr_format_time( $booking['time'] ),
			'{end_time}'          => hkr_format_time( $booking['end_time'] ),
			'{location_name}'     => $loc ? $loc['name'] : '',
			'{location_address}'  => $loc ? (string) $loc['address'] : '',
			'{location_phone}'    => $loc ? hkr_display_phone( $loc['phone'] ) : '',
			'{location_map_link}' => $loc ? (string) $loc['map_url'] : '',
			'{booking_ref}'       => $booking['ref'],
			'{booking_type}'      => $booking['area_name'] ? $booking['area_name'] : __( 'Table', 'hakka-reservations' ),
			'{manage_link}'       => $manage,
			'{notes}'             => trim( $booking['dietary_notes'] . ' ' . $booking['special_requests'] ),
			'{occasion}'          => $celebration,
			'{celebration}'       => $celebration,
			'{status}'            => hkr_status_label( $booking['status'] ),
			'{change_summary}'    => isset( $extra['change_summary'] ) ? (string) $extra['change_summary'] : '',
		);
	}

	public static function celebration_label( $booking ) {
		if ( 'Other' === $booking['celebration_type'] ) {
			return (string) $booking['celebration_text'];
		}
		return (string) $booking['celebration_type'];
	}

	/**
	 * Build subject, HTML and plain text for a template.
	 *
	 * @return array{subject: string, html: string, text: string}
	 */
	public static function render( $type, $booking, array $extra = array() ) {
		$tpl      = HKR_Settings::template( $type );
		$types    = HKR_Settings::template_types();
		$audience = $types[ $type ]['audience'];
		$loc      = HKR_Locations::get( $booking['location_id'] );
		$ph       = self::placeholders( $booking, $loc, $extra );

		$subject = wp_strip_all_tags( strtr( $tpl['subject'], $ph ) );

		$html_ph = array_map( 'esc_html', $ph );
		$html_ph['{staff_message}'] = ! empty( $extra['staff_message'] )
			? '<p style="margin:16px 0;padding:12px 16px;background:#f6f6f6;border-left:4px solid ' . esc_attr( self::color( 'primary' ) ) . ';">' . nl2br( esc_html( $extra['staff_message'] ) ) . '</p>'
			: '';
		$html_ph['{guest_message}'] = ! empty( $extra['guest_message'] )
			? '<p style="margin:16px 0;padding:12px 16px;background:#f6f6f6;border-left:4px solid ' . esc_attr( self::color( 'primary' ) ) . ';"><strong>' . esc_html__( 'Guest\'s message:', 'hakka-reservations' ) . '</strong><br>' . nl2br( esc_html( $extra['guest_message'] ) ) . '</p>'
			: '';
		if ( $ph['{manage_link}'] ) {
			$html_ph['{manage_link}'] = esc_url( $ph['{manage_link}'] );
		}

		$heading = esc_html( strtr( $tpl['heading'], $ph ) );
		$banner  = esc_html( strtr( $tpl['banner'], $ph ) );
		$body    = strtr( wp_kses_post( $tpl['body'] ), $html_ph );

		$html = self::layout(
			array(
				'type'     => $type,
				'audience' => $audience,
				'booking'  => $booking,
				'location' => $loc,
				'heading'  => $heading,
				'banner'   => $banner,
				'body'     => $body,
				'extra'    => $extra,
				'ph'       => $ph,
			)
		);

		$text   = array();
		$text[] = strtr( $tpl['heading'], $ph );
		$text[] = strtr( $tpl['banner'], $ph );
		$text[] = '';
		$text[] = trim( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), "\n", strtr( wp_kses_post( $tpl['body'] ), array_merge( $ph, array( '{staff_message}' => isset( $extra['staff_message'] ) ? $extra['staff_message'] : '', '{guest_message}' => isset( $extra['guest_message'] ) ? $extra['guest_message'] : '' ) ) ) ) ) );
		$text[] = '';
		foreach ( self::summary_rows( $booking, $loc, $audience ) as $row ) {
			$text[] = $row[0] . ': ' . wp_strip_all_tags( $row[1] );
		}
		if ( $ph['{manage_link}'] ) {
			$text[] = '';
			$text[] = __( 'Manage or cancel your booking:', 'hakka-reservations' ) . ' ' . $ph['{manage_link}'];
		}
		if ( $loc ) {
			$text[] = '';
			$text[] = $loc['name'] . ' · ' . $loc['address'] . ' · ' . hkr_display_phone( $loc['phone'] );
		}

		return array(
			'subject' => $subject,
			'html'    => $html,
			'text'    => implode( "\n", $text ),
		);
	}

	private static function color( $which ) {
		$value = HKR_Settings::get( 'primary' === $which ? 'color_primary' : 'color_dark' );
		$value = sanitize_hex_color( $value );
		return $value ? $value : ( 'primary' === $which ? '#dd3333' : '#111111' );
	}

	/**
	 * Label/value pairs for the summary card. Values are already escaped HTML.
	 */
	public static function summary_rows( $booking, $loc, $audience ) {
		$rows   = array();
		$rows[] = array( __( 'Reference', 'hakka-reservations' ), '<strong>' . esc_html( $booking['ref'] ) . '</strong>' );
		if ( $loc ) {
			$rows[] = array( __( 'Location', 'hakka-reservations' ), '<strong>' . esc_html( $loc['name'] ) . '</strong><br>' . esc_html( $loc['address'] ) );
		}
		$rows[] = array( __( 'Date', 'hakka-reservations' ), '<strong>' . esc_html( hkr_format_date( $booking['date'] ) ) . '</strong>' );
		$rows[] = array( __( 'Time', 'hakka-reservations' ), '<strong>' . esc_html( hkr_format_time( $booking['time'] ) . ' – ' . hkr_format_time( $booking['end_time'] ) ) . '</strong>' );
		/* translators: %d: number of guests */
		$rows[] = array( __( 'Party size', 'hakka-reservations' ), '<strong>' . esc_html( sprintf( _n( '%d guest', '%d guests', $booking['party_size'], 'hakka-reservations' ), $booking['party_size'] ) ) . '</strong>' );
		if ( $booking['area_name'] ) {
			$rows[] = array( __( 'Booking type', 'hakka-reservations' ), esc_html( $booking['area_name'] ) );
		}
		$rows[] = array( __( 'Name', 'hakka-reservations' ), esc_html( $booking['guest_name'] ) );
		$phone  = hkr_display_phone( $booking['guest_phone'] );
		$rows[] = array( __( 'Phone', 'hakka-reservations' ), 'staff' === $audience && $booking['guest_phone'] ? '<a href="tel:' . esc_attr( $booking['guest_phone'] ) . '" style="color:' . esc_attr( self::color( 'primary' ) ) . ';font-weight:bold;">' . esc_html( $phone ) . '</a>' : esc_html( $phone ) );
		if ( $booking['guest_email'] ) {
			$rows[] = array( __( 'Email', 'hakka-reservations' ), esc_html( $booking['guest_email'] ) );
		}
		$celebration = self::celebration_label( $booking );
		if ( $celebration ) {
			$value = '🎉 ' . esc_html( $celebration );
			if ( $booking['celebration_details'] ) {
				$value .= '<br><span style="color:#666;">' . esc_html( $booking['celebration_details'] ) . '</span>';
			}
			$rows[] = array( __( 'Celebration', 'hakka-reservations' ), $value );
		}
		if ( $booking['seating_pref'] ) {
			$rows[] = array( __( 'Seating preference', 'hakka-reservations' ), esc_html( $booking['seating_pref'] ) );
		}
		if ( $booking['high_chair'] ) {
			$rows[] = array( __( 'High chair', 'hakka-reservations' ), esc_html__( 'Yes, please', 'hakka-reservations' ) );
		}
		if ( $booking['dietary_notes'] ) {
			$rows[] = array( __( 'Dietary / allergy notes', 'hakka-reservations' ), nl2br( esc_html( $booking['dietary_notes'] ) ) );
		}
		if ( $booking['special_requests'] ) {
			$rows[] = array( __( 'Special requests', 'hakka-reservations' ), nl2br( esc_html( $booking['special_requests'] ) ) );
		}
		$status_label = 'pending' === $booking['status'] ? __( 'Pending confirmation', 'hakka-reservations' ) : hkr_status_label( $booking['status'] );
		$rows[]       = array( __( 'Status', 'hakka-reservations' ), '<strong>' . esc_html( $status_label ) . '</strong>' );
		return $rows;
	}

	private static function button( $url, $label, $primary = true ) {
		$bg     = $primary ? self::color( 'primary' ) : '#ffffff';
		$fg     = $primary ? '#ffffff' : self::color( 'dark' );
		$border = $primary ? self::color( 'primary' ) : '#cccccc';
		return '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin:4px 4px 4px 0;padding:12px 20px;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';border:1px solid ' . esc_attr( $border ) . ';border-radius:24px;font-weight:bold;font-size:14px;text-decoration:none;">' . esc_html( $label ) . '</a>';
	}

	public static function google_calendar_url( $booking, $loc ) {
		$start = ( new DateTimeImmutable( $booking['start_at'], hkr_tz() ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$end   = ( new DateTimeImmutable( $booking['end_at'], hkr_tz() ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => rawurlencode( ( $loc ? $loc['name'] : 'HAKKA' ) . ' – ' . $booking['ref'] ),
				'dates'    => $start->format( 'Ymd\THis\Z' ) . '/' . $end->format( 'Ymd\THis\Z' ),
				'location' => rawurlencode( $loc ? $loc['address'] : '' ),
				/* translators: 1: party size, 2: booking reference */
				'details'  => rawurlencode( sprintf( __( 'Table for %1$d. Booking %2$s.', 'hakka-reservations' ), $booking['party_size'], $booking['ref'] ) ),
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * The branded email. Table-based with inline styles so it holds up in Gmail, Outlook and Apple Mail.
	 */
	private static function layout( array $a ) {
		$booking  = $a['booking'];
		$loc      = $a['location'];
		$audience = $a['audience'];
		$primary  = self::color( 'primary' );
		$dark     = self::color( 'dark' );
		$business = HKR_Settings::get( 'business_name' );
		$logo_id  = (int) HKR_Settings::get( 'logo_id' );
		$logo     = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;
		if ( ! $logo ) {
			$site_logo = self::site_logo_url();
			$logo      = $site_logo ? array( $site_logo ) : false;
		}
		$hero     = $loc ? HKR_Locations::email_image_url( $loc ) : '';

		$banner_bg = '#fff4e0';
		$banner_fg = '#7a4b00';
		if ( in_array( $a['type'], array( 'guest_confirmed', 'guest_reminder' ), true ) ) {
			$banner_bg = '#e7f6ec';
			$banner_fg = '#1d6b36';
		} elseif ( in_array( $a['type'], array( 'guest_declined', 'guest_cancelled' ), true ) ) {
			$banner_bg = '#fdecec';
			$banner_fg = '#9b1c1c';
		} elseif ( 'staff' === $audience ) {
			$banner_bg = $dark;
			$banner_fg = '#ffffff';
		}

		$font = "font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;";
		ob_start();
		?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html( $business ); ?></title></head>
<body style="margin:0;padding:0;background:#efefef;">
<div style="display:none;max-height:0;overflow:hidden;"><?php echo esc_html( $a['banner'] ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#efefef;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;<?php echo $font; // phpcs:ignore ?>color:#222222;">
	<tr><td style="background:<?php echo esc_attr( $dark ); ?>;padding:20px 28px;border-bottom:4px solid <?php echo esc_attr( $primary ); ?>;">
		<?php if ( $logo ) : ?>
			<img src="<?php echo esc_url( $logo[0] ); ?>" alt="<?php echo esc_attr( $business ); ?>" width="280" style="display:block;width:280px;max-width:100%;height:auto;border:0;">
		<?php else : ?>
			<span style="color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:2px;"><?php echo esc_html( strtoupper( $business ) ); ?></span>
		<?php endif; ?>
		<?php if ( $loc ) : ?>
			<div style="color:#bbbbbb;font-size:13px;margin-top:6px;"><?php echo esc_html( $loc['name'] ); ?></div>
		<?php endif; ?>
	</td></tr>
	<?php if ( $hero && 'guest' === $audience ) : ?>
	<tr><td><img src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( $loc ? $loc['name'] : $business ); ?>" width="600" style="width:100%;max-width:600px;height:auto;display:block;border:0;"></td></tr>
	<?php endif; ?>
	<tr><td style="background:<?php echo esc_attr( $banner_bg ); ?>;color:<?php echo esc_attr( $banner_fg ); ?>;padding:14px 28px;font-size:15px;font-weight:bold;text-align:center;"><?php echo esc_html( $a['banner'] ); ?></td></tr>
	<tr><td style="padding:28px 28px 8px;">
		<h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;color:<?php echo esc_attr( $dark ); ?>;"><?php echo esc_html( $a['heading'] ); ?></h1>
		<div style="font-size:15px;line-height:1.6;color:#333333;"><?php echo $a['body']; // phpcs:ignore -- built from kses'd template and escaped values. ?></div>
	</td></tr>
	<?php if ( 'staff' === $audience ) : ?>
	<tr><td style="padding:4px 28px 0;"><?php echo self::staff_flags( $booking, $loc ); // phpcs:ignore -- escaped inside. ?></td></tr>
	<?php endif; ?>
	<tr><td style="padding:16px 28px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6e6e6;border-radius:10px;background:#fafafa;">
			<tr><td colspan="2" style="padding:14px 18px;border-bottom:1px solid #e6e6e6;font-size:13px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:<?php echo esc_attr( $primary ); ?>;"><?php esc_html_e( 'Booking details', 'hakka-reservations' ); ?></td></tr>
			<?php foreach ( self::summary_rows( $booking, $loc, $audience ) as $row ) : ?>
			<tr>
				<td valign="top" style="padding:10px 18px;width:38%;font-size:13px;color:#777777;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $row[0] ); ?></td>
				<td valign="top" style="padding:10px 18px;font-size:15px;color:#222222;border-bottom:1px solid #eeeeee;"><?php echo $row[1]; // phpcs:ignore -- escaped in summary_rows(). ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</td></tr>
	<tr><td style="padding:4px 28px 20px;">
		<?php
		if ( 'staff' === $audience ) {
			$staff_page = (int) HKR_Settings::get( 'staff_page_id' );
			if ( $staff_page ) {
				$url = add_query_arg( array( 'hkr_booking' => $booking['id'], 'hkr_date' => $booking['date'] ), get_permalink( $staff_page ) );
				echo self::button( $url, 'pending' === $booking['status'] ? __( 'Confirm booking', 'hakka-reservations' ) : __( 'Open staff screen', 'hakka-reservations' ) ); // phpcs:ignore
			}
			if ( $booking['guest_phone'] ) {
				echo self::button( 'tel:' . $booking['guest_phone'], __( 'Call guest', 'hakka-reservations' ), false ); // phpcs:ignore
			}
		} else {
			if ( 'confirmed' === $booking['status'] && ! in_array( $a['type'], array( 'guest_cancelled', 'guest_declined' ), true ) ) {
				echo self::button( self::google_calendar_url( $booking, $loc ), __( 'Add to calendar', 'hakka-reservations' ) ); // phpcs:ignore
			}
			if ( ! empty( $a['ph']['{manage_link}'] ) && in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
				echo self::button( $a['ph']['{manage_link}'], __( 'Manage / cancel booking', 'hakka-reservations' ), 'confirmed' !== $booking['status'] ); // phpcs:ignore
			}
			if ( $loc && $loc['phone'] ) {
				echo self::button( 'tel:' . $loc['phone'], __( 'Call the restaurant', 'hakka-reservations' ), false ); // phpcs:ignore
			}
			if ( $loc && $loc['map_url'] ) {
				echo self::button( $loc['map_url'], __( 'Get directions', 'hakka-reservations' ), false ); // phpcs:ignore
			}
		}
		?>
	</td></tr>
	<?php if ( $loc ) : ?>
	<tr><td style="padding:20px 28px;background:#f6f6f6;border-top:1px solid #e6e6e6;font-size:13px;line-height:1.6;color:#555555;">
		<strong style="color:#222222;"><?php echo esc_html( $loc['name'] ); ?></strong><br>
		<?php echo esc_html( $loc['address'] ); ?><br>
		<?php echo esc_html( hkr_display_phone( $loc['phone'] ) ); ?><br>
		<?php echo esc_html( implode( ' · ', HKR_Locations::hours_summary( $loc ) ) ); ?>
		<?php if ( 'guest' === $audience ) : ?>
			<?php if ( 'pending' === $booking['status'] ) : ?>
				<p style="margin:12px 0 0;"><?php echo esc_html( sprintf( /* translators: %s: guest phone */ __( 'What happens next: our team will call you on %s to confirm your booking. Your table isn\'t guaranteed until we do.', 'hakka-reservations' ), hkr_display_phone( $booking['guest_phone'] ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( HKR_Settings::get( 'terms_text' ) ) : ?>
				<p style="margin:12px 0 0;color:#777777;"><?php echo esc_html( HKR_Settings::get( 'terms_text' ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</td></tr>
	<?php endif; ?>
	<tr><td style="padding:14px 28px;background:<?php echo esc_attr( $dark ); ?>;color:#999999;font-size:12px;text-align:center;">&copy; <?php echo esc_html( hkr_now()->format( 'Y' ) . ' ' . $business ); ?></td></tr>
</table>
</td></tr></table>
</body></html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The website's own logo, used when no email logo is chosen in Settings:
	 * the WordPress site logo, else the Grand Restaurant theme's header logo.
	 */
	public static function site_logo_url() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		foreach ( array( 'tg_retina_logo', 'tg_retina_transparent_logo' ) as $mod ) {
			$url = (string) get_theme_mod( $mod );
			if ( $url ) {
				return set_url_scheme( $url, 'https' );
			}
		}
		return '';
	}

	/**
	 * Flags and fill level for the staff email.
	 */
	private static function staff_flags( $booking, $loc ) {
		$flags = array();
		if ( $loc && $booking['party_size'] >= (int) $loc['settings']['large_party'] && (int) $loc['settings']['large_party'] > 0 ) {
			$flags[] = __( 'Large party', 'hakka-reservations' );
		}
		if ( $booking['dietary_notes'] ) {
			$flags[] = __( 'Allergy / dietary note', 'hakka-reservations' );
		}
		if ( self::celebration_label( $booking ) ) {
			$flags[] = __( 'Special occasion', 'hakka-reservations' );
		}
		if ( $booking['high_chair'] ) {
			$flags[] = __( 'High chair', 'hakka-reservations' );
		}
		$out = '';
		foreach ( $flags as $flag ) {
			$out .= '<span style="display:inline-block;margin:0 6px 6px 0;padding:4px 10px;border-radius:12px;background:#fdecec;color:#9b1c1c;font-size:12px;font-weight:bold;">' . esc_html( $flag ) . '</span>';
		}
		if ( $loc ) {
			$area = $booking['area_id'] ? HKR_Locations::area( $booking['area_id'] ) : null;
			$rem  = HKR_Availability::remaining( $loc, $area, $booking['date'], $booking['time'], $booking['end_time'] );
			$line = '';
			if ( null !== $rem['capacity_area'] && $area ) {
				/* translators: 1: booking type, 2: guests booked, 3: capacity */
				$line = sprintf( __( '%1$s: %2$d of %3$d booked for this time', 'hakka-reservations' ), $area['name'], $rem['booked_area'], $rem['capacity_area'] );
			}
			if ( null !== $rem['capacity_location'] ) {
				/* translators: 1: guests booked, 2: capacity */
				$line .= ( $line ? ' · ' : '' ) . sprintf( __( 'Whole location: %1$d of %2$d booked for this time', 'hakka-reservations' ), $rem['booked_location'], $rem['capacity_location'] );
			}
			if ( $line ) {
				$out .= '<div style="margin:4px 0 0;font-size:13px;color:#555555;">' . esc_html( $line ) . '</div>';
			}
		}
		return $out;
	}

	/**
	 * Write an .ics calendar file for the booking. Returns its path, or '' on failure.
	 */
	public static function write_ics( $booking, $loc ) {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'hkr-tmp';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
		}
		$utc   = new DateTimeZone( 'UTC' );
		$start = ( new DateTimeImmutable( $booking['start_at'], hkr_tz() ) )->setTimezone( $utc );
		$end   = ( new DateTimeImmutable( $booking['end_at'], hkr_tz() ) )->setTimezone( $utc );
		$esc   = static function ( $s ) {
			return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), (string) $s );
		};
		$name  = $loc ? $loc['name'] : 'HAKKA';
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//HAKKA Restaurant//Reservations//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $booking['ref'] . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $start->format( 'Ymd\THis\Z' ),
			'DTEND:' . $end->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . $esc( $name . ' – ' . $booking['ref'] ),
			'LOCATION:' . $esc( $loc ? $loc['address'] : '' ),
			/* translators: 1: party size, 2: booking reference */
			'DESCRIPTION:' . $esc( sprintf( __( 'Table for %1$d. Booking %2$s.', 'hakka-reservations' ), $booking['party_size'], $booking['ref'] ) ),
			'END:VEVENT',
			'END:VCALENDAR',
		);
		$file = $dir . '/' . sanitize_file_name( 'hakka-booking-' . $booking['ref'] . '-' . wp_generate_password( 6, false ) . '.ics' );
		return false !== file_put_contents( $file, implode( "\r\n", $lines ) . "\r\n" ) ? $file : ''; // phpcs:ignore
	}

	/**
	 * A realistic sample booking for previews and test emails.
	 */
	public static function sample_booking( $location_id = 0 ) {
		$locs = HKR_Locations::all();
		$loc  = $location_id ? HKR_Locations::get( $location_id ) : ( $locs ? $locs[0] : null );
		$area = $loc ? HKR_Locations::areas( $loc['id'] ) : array();
		$day  = hkr_now()->modify( '+3 days' )->format( 'Y-m-d' );
		return array(
			'id'                  => 0,
			'ref'                 => 'HK-1234',
			'location_id'         => $loc ? $loc['id'] : 0,
			'area_id'             => $area ? $area[0]['id'] : 0,
			'area_name'           => $area ? $area[0]['name'] : '',
			'start_at'            => $day . ' 19:00:00',
			'end_at'              => $day . ' 20:00:00',
			'date'                => $day,
			'time'                => '19:00',
			'end_time'            => '20:00',
			'party_size'          => 4,
			'status'              => 'pending',
			'source'              => 'web',
			'guest_name'          => 'Jane Smith',
			'guest_phone'         => '+18685550123',
			'guest_email'         => 'jane@example.com',
			'celebration_type'    => 'Birthday',
			'celebration_text'    => '',
			'celebration_details' => 'It\'s my mum\'s 60th!',
			'seating_pref'        => '',
			'high_chair'          => 1,
			'dietary_notes'       => 'One guest has a peanut allergy.',
			'special_requests'    => 'A quiet table if possible.',
			'internal_notes'      => '',
			'manage_token'        => '',
		);
	}
}
