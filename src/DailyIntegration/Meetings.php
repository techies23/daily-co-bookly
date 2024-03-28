<?php

namespace Headroom\Dailyco\DailyIntegration;

use Headroom\Dailyco\Datastore\Appointments;

class Meetings {

	public static function createMeeting( $postData, $type = 'create', $invoice = false, $reschedule = false, $name = '' ) {
		$appointments = Appointments::instance();
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
					$appointments->create( $postData['wp_user_id'], $result->name, $postData['appointment_id'], $result );
				}
			} else {
				dailyco_api()->update_room( $submit, $name );
			}

			if ( ! empty( $result ) ) {
				//Reset Cache
				dpen_clear_room_cache();

				//Send to client
				Email::clientEmail( $postData, $result, $invoice, $reschedule );

				//Send to therapist
				Email::therapistEmail( $postData, $result, $reschedule );
			}
		}
	}
}