<?php

namespace Headroom\Dailyco\Bookly\Appointments;

use Bookly\Lib\Base\Ajax as BooklyAjax;
use Bookly\Lib\Entities\Appointment;
use Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\Service;
use Bookly\Lib\Entities\Staff;
use Headroom\Dailyco\DailyIntegration\Meetings;
use Headroom\Dailyco\Datastore\Appointments;

/**
 * Ajax interceptor for bookly sessions.
 */
class AppointmentsAjax extends BooklyAjax {

	protected static function permissions(): array {
		return array( '_default' => array( 'staff', 'supervisor' ) );
	}

	protected function __construct() {
		if ( wp_doing_ajax() ) {
			if ( isset( $_POST['action'] ) && $_POST['action'] === "bookly_save_appointment_form" ) {
				$staff_id       = (int) self::parameter( 'staff_id', 0 );
				$appointment_id = (int) self::parameter( 'id' );
				$customers      = json_decode( self::parameter( 'customers', '[]' ), true );
				$service_id     = (int) self::parameter( 'service_id', - 1 );

				if ( ! empty( $appointment_id ) ) {
					$this->updateRoomDetails( $staff_id, $appointment_id, $customers, $service_id );
				}
			}

			if ( isset( $_POST['action'] ) && $_POST['action'] === "bookly_delete_customer_appointments" ) {
				$ca = (array) self::parameter( 'data', 0 );
				foreach ( $ca as $customerAppointment ) {
					$this->deleteRoom( $customerAppointment['id'] );
				}

//				if ( ! empty( $ca[0]['id'] ) ) {
//					$this->deleteRoom( $ca[0]['id'] );
//				}
			}
		}
	}

	/**
	 * Update room details
	 *
	 * @param $staff_id
	 * @param $appointment_id
	 * @param $customers
	 * @param $service_id
	 *
	 * @return void
	 */
	public function updateRoomDetails( $staff_id, $appointment_id, $customers, $service_id ) {
		$booklyAppointment = Appointments::instance();
		$staff             = Staff::find( $staff_id );
		$appointment       = Appointment::find( $appointment_id );
		$service           = Service::find( $service_id );

		if ( $staff->getWpUserId() ) {
			$postData['wp_user_id']     = $staff->getWpUserId();
			$postData['start_date']     = $appointment->getStartDate();
			$postData['end_date']       = $appointment->getEndDate();
			$postData['appointment_id'] = $appointment->getId();
			$postData['service_id']     = $appointment->getServiceId();
			$postData['service_title']  = ! empty( $service ) ? $service->getTitle() : false;
			$postData['staff_name']     = $staff->getFullName();
			$postData['staff_email']    = $staff->getEmail();
			$postData['order_url']      = false;

			if ( ! empty( $customers ) ) {
				$customer_detail               = Customer::find( $customers[0]['id'] );
				$postData['customer_email']    = $customer_detail->getEmail();
				$postData['customer_fullname'] = $customer_detail->getFullName();
				$postData['timezone']          = ! empty( $customers[0]['timezone'] ) ? $customers[0]['timezone'] : '';
			}

			$dailyco = $booklyAppointment->getByUserAppointment( $staff->getWpUserId(), $appointment->getId() );
			if ( ! empty( $dailyco ) ) {
				Meetings::createMeeting( $postData, 'update', true, false, $dailyco->name );
			} else {
				Meetings::createMeeting( $postData, 'create', true );
			}
		}
	}

	/**
	 * Delete room if an appointment is deleted
	 *
	 * @param $appointment_id
	 *
	 * @return void
	 */
	public static function deleteRoom( $appointment_id ) {
		$appointment = Appointment::find( $appointment_id );
		$staff       = Staff::find( $appointment->getStaffId() );
		if ( $staff->getWpUserId() ) {
			$dailyCoAppointments = Appointments::instance();

			$dailyAppointment = $dailyCoAppointments->getByUserAppointment( $staff->getWpUserId(), $appointment->getId() );
			if ( ! empty( $dailyAppointment ) ) {
				$response = dailyco_api()->delete_room( $dailyAppointment->name );
				if ( $response->deleted ) {
					$dailyCoAppointments->delete( $dailyAppointment->id );

					//Reset Cache
					dpen_clear_room_cache();
				}
			}
		}
	}

	private static $_instance = null;

	public static function instance(): ?AppointmentsAjax {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
