<?php

namespace Headroom\Dailyco\DailyIntegration;

class Email {

	private static $defaultTimezone = 'Africa/Johannesburg';

	public static function prepareEmail( $template_name, array $data, $subject, $email, $ics = false, array $postData = [] ) {
		$from_email = esc_html( get_option( '_dpen_daily_from_email' ) );
		$from       = ! empty( $from_email ) ? $from_email : 'no-reply@headroom.co.za';

		//Ready for email
		$headers[]      = 'Content-Type: text/html; charset=UTF-8';
		$headers[]      = 'From: ' . get_bloginfo( 'name' ) . ' < ' . $from . ' >' . "\r\n";
		$email_template = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/' . $template_name . '.html' );

		$timezone       = ! empty( $postData['timezone'] ) ? $postData['timezone'] : 'Africa/Johannesburg';
		$search_strings = array(
			'{site_title}',
			'{site_url}',
			'{year}',
			'{room_time}',
			'{room_topic}',
			'{room_join_link}',
			'{customer}',
			'{customer_email}',
			'{appointment_notes}',
			'{therapist_name}',
			'{order_url}'
		);

		$replace_string = array(
			get_bloginfo( 'name' ),
			home_url( '/' ),
			date( 'Y' )
		);

		$final_strings = array_merge( $replace_string, $data );
		$body          = str_replace( $search_strings, $final_strings, $email_template );
		if ( $ics ) {
			$attach_ics = new CalendarICS( $ics );
			$uploads    = wp_upload_dir();
			$basedir    = $uploads['basedir'];
			$upload_dir = $basedir . '/webmeeting';
			if ( ! is_dir( $upload_dir ) ) {
				mkdir( $upload_dir, 0700 );
			}

			$file_path = $upload_dir . '/meeting.ics';
			file_put_contents( $file_path, $attach_ics->to_string() );
			if ( file_exists( $file_path ) ) {
				$attachments = array( $file_path );
			}
			$attachments = ! empty( $attachments ) ? $attachments : false;
			wp_mail( $email, $subject, $body, $headers, $attachments );
			wp_delete_file( $file_path );
		} else {
			wp_mail( $email, $subject, $body, $headers );
		}
	}

	/**
	 * Send email to client with ICS attached
	 *
	 * @param $postData
	 * @param $result
	 * @param $invoice
	 * @param  bool  $reschedule
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function clientEmail( $postData, $result, $invoice, bool $reschedule = false ) {
		//Send Email to customer
		$apt_notes  = ! empty( $postData['appointment_notes'] ) ? $postData['appointment_notes'] : 'N/A';
		$timezone   = ! empty( $postData['timezone'] ) ? $postData['timezone'] : self::$defaultTimezone;
		$start_time = dpen_daily_co_convert_timezone( array(
			'date'     => $postData['start_date'],
			'timezone' => $timezone,
		), 'd/m/Y h:i a' );

		$end_time = dpen_daily_co_convert_timezone( array(
			'date'     => $postData['end_date'],
			'timezone' => $timezone,
		), 'd/m/Y h:i a' );

		$email_data = array(
			$start_time,
			$postData['service_title'],
			home_url( '/room/join/?j=' ) . $result->name,
			false,
			false,
			$apt_notes,
			$postData['staff_name'],
			$postData['order_url']
		);

		if ( ! $reschedule ) {
			$invoice            = ! empty( $invoice ) ? 'Payment notification: ' . $postData['order_url'] : '';
			$description_invite = 'Hello\n\n,You have successfully booked a session with ' . $postData['staff_name'] . '. Please find below session details and a link to join the virtual room at booked time.\nService: ' . $postData['service_title'] . '\nStarting at: ' . $start_time . '\n' . $invoice . '\n\nInstructions:\n\nBefore joining the meeting, you will need to log in to our system. Please allow sufficient time to go through the login process before the session start time. We strongly recommend using the Chrome browser for the best video quality. If you are experiencing some granularity in the initial images, allow a 1-2 minutes for the connection to stabilise. Also please be aware that the virtual room will close automatically at the session end time. A countdown timer is visible to assist you and the therapist to bring the session to a timely end before the virtual room closes\n\n' . home_url( '/room/join/?j=' ) . $result->name;
		} else {
			$description_invite = 'Hello\n\n,Your session with therapist ' . $postData['staff_name'] . ' has been re-scheduled. Please find below session details and a link to join the virtual room at booked time.\nService: ' . $postData['service_title'] . '\nStarting at: ' . $start_time . '\n\nInstructions:\n\nBefore joining the meeting, you will need to log in to our system. Please allow sufficient time to go through the login process before the session start time. We strongly recommend using the Chrome browser for the best video quality. If you are experiencing some granularity in the initial images, allow a 1-2 minutes for the connection to stabilise. Also please be aware that the virtual room will close automatically at the session end time. A countdown timer is visible to assist you and the therapist to bring the session to a timely end before the virtual room closes.\n\n' . home_url( '/room/join/?j=' ) . $result->name;
		}

		$customer_ics = array(
			'location'    => home_url( '/room/join/?j=' ) . $result->name,
			'description' => $description_invite,
			'dtstart'     => $start_time,
			'dtend'       => $end_time,
			'organizer'   => $postData['staff_name'],
			'summary'     => 'Online Consultation | Headroom',
			'tzid'        => $timezone
		);

		$email_tpl = ! $reschedule ? 'tpl-email-invite' : 'tpl-email-invite-updated';

		self::prepareEmail( $email_tpl, $email_data, "Online Consultation | Headroom", $postData['customer_email'], $customer_ics, $postData );
	}

	/**
	 * Send email to therapist with ICS attached
	 *
	 * @param $postData
	 * @param $result
	 * @param  bool  $reschedule
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function therapistEmail( $postData, $result, bool $reschedule = false ) {
		//Send email to Staff
		$apt_notes  = ! empty( $postData['appointment_notes'] ) ? $postData['appointment_notes'] : 'N/A';
		$start_time = $postData['start_date'];

		$email_data = array(
			$start_time,
			$postData['service_title'],
			home_url( '/room/start/?s=' ) . $result->name,
			$postData['customer_fullname'],
			$postData['customer_email'],
			$apt_notes
		);

		if ( ! $reschedule ) {
			$description_start = 'Hello,\n\nYou have a new booking from ' . $postData['customer_fullname'] . ' ( ' . $postData['customer_email'] . ' ). Please find below the session details and the link to open the virtual room at the booked time.\n\nService: ' . $postData['service_title'] . '\nStarting at: ' . $start_time . '\nClient Name: ' . $postData['customer_fullname'] . '\nClient Email: ' . $postData['customer_email'] . '\n\nInstructions:\n\nYou will need to log in to our system to initiate the meeting. Please allow sufficient time to go through the login process before the session starts. We strongly recommend using the Chrome browser for the best video quality.\n\nIf you are experiencing some granularity in the initial images, allow a 1-2 minutes for the connection to stabilise. Also please be aware that the virtual room will close automatically at the session end time. A countdown timer is visible to assist you and the client to bring the session to a timely end before the virtual room closes.\n\n' . home_url( '/room/start/?s=' ) . $result->name;
		} else {
			$description_start = 'Hello,\n\nYour session with client ' . $postData['customer_fullname'] . ' ( ' . $postData['customer_email'] . ' ) has been re-scheduled. Please find below the session details and the link to open the virtual room at the booked time.\n\nService: ' . $postData['service_title'] . '\nStarting time: ' . $start_time . '\nClient Name: ' . $postData['customer_fullname'] . '\nClient Email: ' . $postData['customer_email'] . '\n\nInstructions:\n\nYou will need to log in to our system to initiate the meeting. Please allow sufficient time to go through the login process before the session starts. We strongly recommend using the Chrome browser for the best video quality.\n\nIf you are experiencing some granularity in the initial images, allow a 1-2 minutes for the connection to stabilise. Also please be aware that the virtual room will close automatically at the session end time. A countdown timer is visible to assist you and the client to bring the session to a timely end before the virtual room closes.\n\n' . home_url( '/room/start/?s=' ) . $result->name;
		}

		$author_ics = array(
			'location'    => home_url( '/room/start/?s=' ) . $result->name,
			'description' => $description_start,
			'dtstart'     => dpen_daily_co_convert_timezone( array(
				'date'      => $start_time,
				'timezone'  => self::$defaultTimezone,
				'timestamp' => true
			), 'Y-m-d h:i a' ),
			'dtend'       => dpen_daily_co_convert_timezone( array(
				'date'      => $postData['end_date'],
				'timezone'  => self::$defaultTimezone,
				'timestamp' => true
			), 'Y-m-d h:i a' ),
			'organizer'   => $postData['staff_name'],
			'summary'     => 'Online Consultation | Headroom'
		);

		$email_tpl = ! $reschedule ? 'tpl-email-start' : 'tpl-email-start-updated';

		self::prepareEmail( $email_tpl, $email_data, "Online Consultation | Headroom", $postData['staff_email'], $author_ics );
	}
}