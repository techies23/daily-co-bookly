<?php

use Bookly\Lib as BooklyLib;

/**
 * Class Ajax
 * @package BooklyCustomerCabinet\Frontend\Components\Dialogs\Reschedule
 */
class DailyCo_Bookly_CancelAppointment_Ajax extends BooklyLib\Base\Ajax {

	public static function cancelAppointment() {
		$customer    = BooklyLib\Entities\Customer::query()->where( 'wp_user_id', get_current_user_id() )->findOne();
		$customer_id = $customer->getId();

		$ca_id = filter_input( INPUT_POST, 'ca_id' );

		$ca = BooklyLib\Entities\CustomerAppointment::find( $ca_id );
		if ( $ca->getCustomerId() == $customer_id ) {
			$appointment = new BooklyLib\Entities\Appointment();
			if ( $appointment->load( $ca->getAppointmentId() ) ) {
				$allow_cancel_time = strtotime( $appointment->getStartDate() ) - (int) BooklyLib\Proxy\Pro::getMinimumTimePriorCancel();
				if ( $appointment->getStartDate() === null || current_time( 'timestamp' ) <= $allow_cancel_time ) {
					$service = BooklyLib\Entities\Service::find( $appointment->getServiceId() );
					$staff   = BooklyLib\Entities\Staff::find( $appointment->getStaffId() );

					//Send cancellation email as well
					$cancel_email_data = array(
						'start_time'     => $appointment->getStartDate(),
						'service_title'  => $service->getTitle(),
						'staff_name'     => $staff->getFullName(),
						'customer_email' => $customer->getEmail(),
						'customer_name'  => $customer->getFullName(),
						'staff_email'    => $staff->getEmail()
					);
					dpen_daily_co_cancel_meeting( $cancel_email_data );

					$ca->cancel();

					$room                = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId(), true );
					$dailyCoAppointments = \Headroom\Dailyco\Datastore\Appointments::instance();
					//Check legacy first
					if ( empty( $room ) ) {
						$userAppointment = $dailyCoAppointments->getByUserAppointment( $staff->getWpUserId(), $appointment->getId() );
						if ( ! empty( $userAppointment ) ) {
							$room = $userAppointment->name;
						}
					}

					//Delete Room
					$response = dailyco_api()->delete_room( $room );
					if ( $response->deleted ) {
						$dailyCoAppointments->delete( $appointment->getId() );

						//Delete legacy
						delete_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId() );
						delete_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment->getId() );

						//Reset Cache
						dpen_clear_room_cache();
					}

					wp_send_json_success();
				}
			}
		}

		wp_send_json_error();
	}
}