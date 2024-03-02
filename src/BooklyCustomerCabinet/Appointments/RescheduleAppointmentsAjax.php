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
		add_action( 'wp_ajax_dailyco_appointment_reschedule', [ $this, 'rescheduleTrigger' ] );
	}

	public function rescheduleTrigger() {
		$this->booklyAppointments = Appointments::instance();
		$ca_id                    = self::parameter( 'ca_id' );
		$ca                       = CustomerAppointment::find( $ca_id );

		$appointment_id = $this->booklyAppointments->getMaxCustomerAppointmentsByOrderID( $ca->getOrderId(), $ca->getPaymentId(), $ca->getCustomerId() );
		if ( ! empty( $ca ) && ! empty( $appointment_id ) ) {
			$slots = json_decode( self::parameter( 'slot' ), true );
			list( $service_id, $staff_id, $bound_start ) = $slots[0];

			//Legacy Delete
			delete_user_meta( $staff_id, '_daily_co_room_name_' . $ca->getAppointmentId() );
			delete_user_meta( $staff_id, '_daily_co_room_details_' . $ca->getAppointmentId() );

			if ( ! empty( $service_id ) && ! empty( $staff_id ) ) {
				$this->rescheduleAppointment( $ca, $staff_id, $service_id, $appointment_id );
			}
		}

		wp_die();
	}


	public function rescheduleAppointment( $ca, int $staff_id, int $service_id, $appointment_id ) {
		$customer    = Customer::find( $ca->getCustomerId() );
		$service     = Service::find( $service_id );
		$staff       = Staff::find( $staff_id );
		$appointment = Appointment::find( $appointment_id );

		#$bound_start = date( 'Y-m-d H:i:s', strtotime( $bound_start ) );
		#$bound_end   = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $service->getDuration() );
		if ( $staff->getWpUserId() ) {
			$postData['wp_user_id']        = $staff->getWpUserId();
			$postData['start_date']        = $appointment->getStartDate();
			$postData['end_date']          = $appointment->getEndDate();
			$postData['appointment_id']    = $appointment_id;
			$postData['service_id']        = $service_id;
			$postData['service_title']     = $service->getTitle();
			$postData['staff_name']        = $staff->getFullName();
			$postData['staff_email']       = $staff->getEmail();
			$postData['customer_email']    = $customer->getEmail();
			$postData['customer_fullname'] = $customer->getFullName();
			$postData['timezone_offset']   = $ca->getTimeZoneOffset();

			$dailyco = $this->booklyAppointments->getByUserAppointment( $staff->getWpUserId(), $appointment_id );
			if ( ! empty( $dailyco ) ) {
				Meetings::createMeeting( $postData, 'update', false, true );
			} else {
				Meetings::createMeeting( $postData, 'create', false, true );
			}

			//Delete old appointment ID if old apt and new is not same.
			if ( $ca->getAppointmentId() != $appointment_id ) {
				$this->deleteAppointment( $ca->getAppointmentId() );
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
	public function deleteAppointment( int $id ) {
		$this->booklyAppointments->deleteByAppointmentId( $id );
	}

	private static $_instance = null;

	public static function instance(): ?RescheduleAppointmentsAjax {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
