<?php

namespace Headroom\Dailyco;

class Helpers {

	/**
	 * Conver timezone according to WordPress GMT offset.
	 *
	 * @param  array  $args
	 * @param  bool  $format
	 *
	 * @return int|string
	 * @throws \Exception
	 */
	public static function dateFormat( array $args = array(), bool $format = false ) {
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
			$current_time = new \DateTime( $now, new \DateTimeZone( $offset ) );
			$current_time->setTimezone( new \DateTimeZone( $params['timezone'] ) );
		} else {
			$current_time = new \DateTime( $now );
			$current_time->setTimezone( new \DateTimeZone( $offset ) );
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