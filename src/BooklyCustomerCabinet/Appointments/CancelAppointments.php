<?php

namespace Headroom\Dailyco\BooklyCustomerCabinet\Appointments;

use Bookly\Lib\Base\Ajax as BooklyAjax;
use Bookly\Lib\Entities\Appointment;
use Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Service;
use Bookly\Lib\Entities\Staff;
use Headroom\Dailyco\Datastore\Appointments;

class CancelAppointments extends BooklyAjax {

	protected function __construct() {
		if ( wp_doing_ajax() && isset( $_POST['action'] ) && $_POST['action'] === "bookly_customer_cabinet_cancel_appointment" ) {
			$this->cancelAppointment();
		}
	}

	public function cancelAppointment() {
		$customer    = Customer::query()->where( 'wp_user_id', get_current_user_id() )->findOne();
		$customer_id = $customer->getId();

		$ca_id = filter_input( INPUT_POST, 'ca_id' );

		$ca = CustomerAppointment::find( $ca_id );
		if ( $ca->getCustomerId() == $customer_id ) {
			$appointment = Appointment::find( $ca->getAppointmentId() );
			if ( ! empty( $appointment ) ) {
				$service = Service::find( $appointment->getServiceId() );
				$staff   = Staff::find( $appointment->getStaffId() );

				//Send cancellation email as well
				dpen_daily_co_cancel_meeting( [
					'start_time'     => $appointment->getStartDate(),
					'service_title'  => $service->getTitle(),
					'staff_name'     => $staff->getFullName(),
					'customer_email' => $customer->getEmail(),
					'customer_name'  => $customer->getFullName(),
					'staff_email'    => $staff->getEmail()
				] );

				$room                = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId(), true );
				$dailyCoAppointments = Appointments::instance();
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
					$dailyCoAppointments->deleteByAppointmentId( $appointment->getId() );

					//Delete legacy
					delete_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId() );
					delete_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment->getId() );
				}

				wp_send_json_success();
			}
		}

		wp_send_json_error();
	}

	private static $_instance = null;

	public static function instance(): ?CancelAppointments {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
