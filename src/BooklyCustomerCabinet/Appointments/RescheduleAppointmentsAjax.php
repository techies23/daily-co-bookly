<?php

namespace Headroom\Dailyco\BooklyCustomerCabinet\Appointments;

use Bookly\Lib\Base\Ajax as BooklyAjax;
use Bookly\Lib\Entities\Appointment;
use Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Service;
use Bookly\Lib\Entities\Staff;
use Headroom\Dailyco\DailyIntegration\Meetings;
use Headroom\Dailyco\Datastore\Appointments;

class RescheduleAppointmentsAjax extends BooklyAjax {

	protected ?Appointments $booklyAppointments;

	protected function __construct() {
		if ( wp_doing_ajax() ) {
			if ( isset( $_POST['action'] ) && $_POST['action'] === "bookly_customer_cabinet_save_reschedule" ) {
				$this->booklyAppointments = Appointments::instance();
				$ca_id                    = self::parameter( 'ca_id' );
				$ca                       = CustomerAppointment::find( $ca_id );

				if ( ! empty( $ca ) ) {
					$slots = json_decode( self::parameter( 'slot' ), true );
					list( $service_id, $staff_id, $bound_start ) = $slots[0];

					//Legacy Delete
					delete_user_meta( $staff_id, '_daily_co_room_name_' . $ca->getAppointmentId() );
					delete_user_meta( $staff_id, '_daily_co_room_details_' . $ca->getAppointmentId() );

					if ( ! empty( $service_id ) && ! empty( $staff_id ) && ! empty( $bound_start ) ) {
						$this->rescheduleAppointment( $ca, $staff_id, $service_id, $bound_start );
					}
				}
			}
		}
	}

	public static function saveReschedule() {
		die('here');
	}

	public function rescheduleAppointment( $ca, int $staff_id, int $service_id, $bound_start ) {
		$customer = Customer::find( $ca->getCustomerId() );
		$service  = Service::find( $service_id );
		$staff    = Staff::find( $staff_id );

		$bound_end = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $service->getDuration() );
		if ( $staff->getWpUserId() ) {
			$postData['wp_user_id']        = $staff->getWpUserId();
			$postData['start_date']        = $bound_start;
			$postData['end_date']          = $bound_end;
			$postData['appointment_id']    = $ca->getAppointmentId();
			$postData['service_id']        = $service_id;
			$postData['service_title']     = $service->getTitle();
			$postData['staff_name']        = $staff->getFullName();
			$postData['staff_email']       = $staff->getEmail();
			$postData['customer_email']    = $customer->getEmail();
			$postData['customer_fullname'] = $customer->getFullName();
			$postData['timezone_offset']   = $ca->getTimeZoneOffset();

			$dailyco = $this->booklyAppointments->getByUserAppointment( $staff->getWpUserId(), $ca->getAppointmentId() );
			if ( ! empty( $dailyco ) ) {
				Meetings::createMeeting( $postData, 'update', false, true );
			} else {
				Meetings::createMeeting( $postData, 'create', false, true );
			}
		}
	}

	/**
	 * Delete previous Appointment
	 *
	 * @param $id
	 *
	 * @return void
	 */
	public function deleteAppointment( $id ) {
		$this->booklyAppointments->delete( $id );
	}

	private static $_instance = null;

	public static function instance(): ?RescheduleAppointmentsAjax {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
