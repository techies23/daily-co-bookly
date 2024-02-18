<?php

use Bookly\Lib;
use Bookly\Lib\Entities\Appointment;

/**
 * Class Ajax
 * @package Bookly\Backend\Modules\Appointments
 */
remove_all_actions( 'wp_ajax_nopriv_bookly_delete_customer_appointments' );
remove_all_actions( 'wp_ajax_bookly_delete_customer_appointments' );
add_action( 'wp_ajax_nopriv_bookly_delete_customer_appointments', array( 'DailyCo_Bookly_Appoinment_Ajax', 'deleteCustomerAppointments' ) );
add_action( 'wp_ajax_bookly_delete_customer_appointments', array( 'DailyCo_Bookly_Appoinment_Ajax', 'deleteCustomerAppointments' ) );

class DailyCo_Bookly_Appoinment_Ajax extends Lib\Base\Ajax {
	/**
	 * Delete customer appointments.
	 */
	public static function deleteCustomerAppointments() {
		// Customer appointments to delete
		$ca_list = array();
		// Appointments without customers to delete
		$appointments_list = array();
		foreach ( self::parameter( 'data', array() ) as $ca_data ) {
			if ( $ca_data['ca_id'] === 'null' ) {
				$appointments_list[] = $ca_data['id'];
			} else {
				$ca_list[] = $ca_data['ca_id'];
			}
		}

		$queue = array();
		/** @var Lib\Entities\CustomerAppointment $ca */

		foreach ( Lib\Entities\CustomerAppointment::query()->whereIn( 'id', $ca_list )->find() as $ca ) {
			if ( self::parameter( 'notify' ) ) {
				switch ( $ca->getStatus() ) {
					case Lib\Entities\CustomerAppointment::STATUS_PENDING:
					case Lib\Entities\CustomerAppointment::STATUS_WAITLISTED:
						$ca->setStatus( Lib\Entities\CustomerAppointment::STATUS_REJECTED );
						break;
					case Lib\Entities\CustomerAppointment::STATUS_APPROVED:
						$ca->setStatus( Lib\Entities\CustomerAppointment::STATUS_CANCELLED );
						break;
					default:
						$busy_statuses = (array) Lib\Proxy\CustomStatuses::prepareBusyStatuses( array() );
						if ( in_array( $ca->getStatus(), $busy_statuses ) ) {
							$ca->setStatus( Lib\Entities\CustomerAppointment::STATUS_CANCELLED );
						}
				}
				Lib\Notifications\Booking\Sender::sendForCA(
					$ca,
					null,
					array( 'cancellation_reason' => self::parameter( 'reason' ) ),
					false,
					$queue
				);
			}

			//Delete Room As Well
			self::deleteRoomAsWell( $ca );

			$ca->deleteCascade();
		}

		/** @var Lib\Entities\Appointment $appointment */
		foreach ( Lib\Entities\Appointment::query()->whereIn( 'id', $appointments_list )->find() as $appointment ) {
			$ca = $appointment->getCustomerAppointments();
			if ( empty( $ca ) ) {
				$appointment->delete();
			}
		}

		wp_send_json_success( compact( 'queue' ) );
	}

	/**
	 * Detlete Created room when appointment is deleted
	 *
	 * @param $ca
	 */
	public static function deleteRoomAsWell( $ca ) {
		$appointment = Lib\Entities\Appointment::find( $ca->getAppointmentId() );
		$staff       = Lib\Entities\Staff::find( $appointment->getStaffId() );
		if ( $staff->getWpUserId() ) {
			$room_name = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $ca->getAppointmentId(), true );
			if ( ! empty( $room_name ) ) {
				$response = dailyco_api()->delete_room( $room_name );
				if ( $response->deleted ) {
					delete_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $ca->getAppointmentId() );
					delete_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $ca->getAppointmentId() );
					//Reset Cache
					dpen_clear_room_cache();
				}
			}
		}
	}
}