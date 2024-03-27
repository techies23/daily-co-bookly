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

		\Headroom\Dailyco\DailyIntegration\Email::prepareEmail( 'tpl-email-cancelled-client', $client_email_data, "Online Consultation | Headroom", $args['customer_email'] );

		//Send Cancellation to Therapist
		$therapist_email_data = array(
			date( 'Y-m-d H:i', strtotime( $args['start_time'] ) ),
			$args['service_title'],
			false,
			$args['customer_name'],
			$args['customer_email']
		);

		\Headroom\Dailyco\DailyIntegration\Email::prepareEmail( 'tpl-email-cancelled-therapist', $therapist_email_data, "Online Consultation | Headroom", $args['staff_email'] );
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