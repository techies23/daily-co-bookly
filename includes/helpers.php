<?php
if ( ! function_exists( 'dump' ) ) {
	function dump( $data ) {
		echo "<pre>";
		var_dump( $data );
		echo "</pre>";
	}
}

if ( ! function_exists( 'dpen_get_rooms' ) ) {
	function dpen_get_cached_rooms() {
		//Check if any transient by name is available
		$cache      = get_option( '_dpen_daily_room_lists' );
		$cache_time = get_option( '_dpen_daily_room_cache_time' );
		if ( ! empty( $cache ) && $cache_time >= time() && ! isset( $_GET['cache_flush'] ) ) {
			return $cache;
		} else {
			$data = dailyco_api()->get_rooms();
			if ( ! empty( $data ) ) {
				update_option( '_dpen_daily_room_lists', $data );
				update_option( '_dpen_daily_room_cache_time', time() + 3600 );

				return $data;
			} else {
				return false;
			}
		}
	}
}

if ( ! function_exists( 'dpen_clear_room_cache' ) ) {
	function dpen_clear_room_cache() {
		update_option( '_dpen_daily_room_lists', '' );
		update_option( '_dpen_daily_room_cache_time', '' );
	}
}

if ( ! function_exists( 'dpen_daily_co_cancel_meeting' ) ) {
	function dpen_daily_co_cancel_meeting( $args = array() ) {
		$client_email_data = array(
			$args['start_time'],
			$args['service_title'],
			false,
			false,
			false,
			$args['staff_name']
		);
		dpen_daily_co_prepare_email( 'tpl-email-cancelled-client', $client_email_data, "Online Consultation | Headroom", $args['customer_email'] );

		//Send Cancellation to Therapist
		$therapist_email_data = array(
			date( 'Y-m-d H:i', strtotime( $args['start_time'] ) ),
			$args['service_title'],
			false,
			$args['customer_name'],
			$args['customer_email']
		);
		dpen_daily_co_prepare_email( 'tpl-email-cancelled-therapist', $therapist_email_data, "Online Consultation | Headroom", $args['staff_email'] );
	}
}

if ( ! function_exists( 'dpen_daily_co_prepare_email' ) ) {
	function dpen_daily_co_prepare_email( $template_name, array $data, $subject, $email, $ics = false ) {
		$email      = trim( $email );
		$from_email = esc_html( get_option( '_dpen_daily_from_email' ) );
		$from       = ! empty( $from_email ) ? $from_email : 'no-reply@headroom.co.za';

		//Ready for email
		$headers[]      = 'Content-Type: text/html; charset=UTF-8';
		$headers[]      = 'From: ' . get_bloginfo( 'name' ) . ' < ' . $from . ' >' . "\r\n";
		$email_template = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/' . $template_name . '.html' );

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
			$attach_ics = new ICS( $ics );
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
}

if ( ! function_exists( 'dpen_daily_co_convert_timezone' ) ) {
	/**
	 * Conver timezone according to WordPress GMT offset.
	 *
	 * @param  array  $args
	 * @param $format
	 *
	 * @return DateTime|int
	 * @throws Exception
	 */
	function dpen_daily_co_convert_timezone( $args = array(), $format = false ) {
		$params = array(
			'date'        => ! empty( $args['date'] ) ? $args['date'] : false,
			'timezone'    => ! empty( $args['timezone'] ) ? $args['timezone'] : false,
			'timestamp'   => ! empty( $args['timestamp'] ) ? $args['timestamp'] : false,
			'change_time' => ! empty( $args['change'] ) ? $args['change'] : false,
		);

		$offset  = get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = abs( ( $offset - (int) $offset ) * 60 );
		$offset  = sprintf( '%+03d:%02d', $hours, $minutes );
		$now     = ! empty( $params['date'] ) ? $params['date'] : 'now';
		$now     = ! empty( $params['change_time'] ) ? $now . $params['change_time'] : $now;
		if ( ! empty( $params['timezone'] ) ) {
			$current_time = new DateTime( $now, new DateTimeZone( $offset ) );
			$current_time->setTimezone( new DateTimeZone( $params['timezone'] ) );
		} else {
			$current_time = new DateTime( $now );
			$current_time->setTimezone( new DateTimeZone( $offset ) );
		}

		if ( $format ) {
			$current_time = $current_time->format( $format );
		} else {
			if ( ! empty( $params['timestamp'] ) ) {
				$current_time = $current_time->getTimestamp();
			} else {
				$current_time = $current_time->getTimestamp() + $current_time->getOffset();
			}
		}

		return $current_time;
	}
}

/**
 * Create Appointment
 *
 * @param $postData
 * @param $type
 * @param $invoice
 * @param $reschedule
 *
 * @throws Exception
 */
function daily_co_create_meeting( $postData, $type = 'create', $invoice = false, $reschedule = false ) {
	if ( ! empty( $postData ) ) {
		$submit = array(
			'nbf'                  => dpen_daily_co_convert_timezone( array(
				'date'      => $postData['start_date'],
				'timezone'  => 'UTC',
				'timestamp' => true
			) ),
			'exp'                  => dpen_daily_co_convert_timezone( array(
				'date'      => $postData['end_date'],
				'timezone'  => 'UTC',
				'timestamp' => true
			) ),
			'privacy'              => 'private',
			'enable_knocking'      => true,
			'owner_only_broadcast' => false,
			'start_video_off'      => true,
			'start_audio_off'      => true,
			'eject_at_room_exp'    => false,
		);

		if ( $type === "create" ) {
			//Creating Room Now
			$result = dailyco_api()->create_room( $submit );
			if ( ! empty( $result ) ) {
				update_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $postData['appointment_id'], $result );
				update_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $postData['appointment_id'], $result->name );
			}
		} else {
			$result    = get_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $postData['appointment_id'], true );
			$room_name = get_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $postData['appointment_id'], true );
			dailyco_api()->update_room( $submit, $room_name );
		}

		if ( ! empty( $result ) ) {
			//Reset Cache
			dpen_clear_room_cache();

			//Send to client
			dpen_daily_co_send_ics_mail_to_client( $postData, $result, $invoice, $reschedule );

			//Send to therapist
			dpen_daily_co_send_ics_mail_to_therapist( $postData, $result, $reschedule );
		}
	}
}

/**
 * Send email to client with ICS attached
 *
 * @param $postData
 * @param $result
 * @param $invoice
 * @param $reschedule
 *
 * @throws Exception
 */
function dpen_daily_co_send_ics_mail_to_client( $postData, $result, $invoice, $reschedule = false ) {
	//Send Email to customer
	$apt_notes  = ! empty( $postData['appointment_notes'] ) ? $postData['appointment_notes'] : 'N/A';
	$start_time = dpen_daily_co_convert_timezone( array(
		'date'     => $postData['start_date'],
		'timezone' => ! empty( $postData['timezone'] ) ? $postData['timezone'] : 'UTC+2',
	), 'd/m/Y h:i a' );

	$end_time = dpen_daily_co_convert_timezone( array(
		'date'     => $postData['end_date'],
		'timezone' => ! empty( $postData['timezone'] ) ? $postData['timezone'] : 'UTC+2',
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
		'summary'     => 'Online Consultation | Headroom'
	);

	$email_tpl = ! $reschedule ? 'tpl-email-invite' : 'tpl-email-invite-updated';
	dpen_daily_co_prepare_email( $email_tpl, $email_data, "Online Consultation | Headroom", $postData['customer_email'], $customer_ics );
}

/**
 * Send email to therapist with ICS attached
 *
 * @param $postData
 * @param $result
 * @param $reschedule
 *
 * @throws Exception
 */
function dpen_daily_co_send_ics_mail_to_therapist( $postData, $result, $reschedule = false ) {
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
			'date'      => $postData['start_date'],
			'timezone'  => 'UTC',
			'timestamp' => true
		), 'Y-m-d h:i a' ),
		'dtend'       => dpen_daily_co_convert_timezone( array(
			'date'      => $postData['end_date'],
			'timezone'  => 'UTC',
			'timestamp' => true
		), 'Y-m-d h:i a' ),
		'organizer'   => $postData['staff_name'],
		'summary'     => 'Online Consultation | Headroom'
	);

	$email_tpl = ! $reschedule ? 'tpl-email-start' : 'tpl-email-start-updated';
	dpen_daily_co_prepare_email( $email_tpl, $email_data, "Online Consultation | Headroom", $postData['staff_email'], $author_ics );
}