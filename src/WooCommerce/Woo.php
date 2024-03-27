<?php

namespace Headroom\Dailyco\WooCommerce;

use Headroom\Dailyco\DailyIntegration\Meetings;
use Headroom\Dailyco\Datastore\Appointments;

class Woo {

	protected ?Appointments $appointments;

	protected function __construct() {
		$this->appointments = Appointments::instance();

		add_action( 'woocommerce_order_status_completed', [ $this, 'paymentComplete' ], 9999 );
		add_action( 'woocommerce_order_status_processing', [ $this, 'paymentComplete' ], 9999 );
	}

	/**
	 * On Payment Completed
	 *
	 * @param $order_id
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function paymentComplete( $order_id ) {
		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $item_id => $order_item ) {
			$wc_item = wc_get_order_item_meta( $item_id, 'bookly' );
			if ( ! empty( $wc_item ) && ! empty( $wc_item['ca_ids'] ) ) {
				foreach ( $wc_item['ca_ids'] as $item ) {
					$ca = \Bookly\Lib\Entities\CustomerAppointment::find( $item );
					if ( ! empty( $ca ) ) {
						$appointment = \Bookly\Lib\Entities\Appointment::find( $ca->getAppointmentId() );
						$service     = \Bookly\Lib\Entities\Service::find( $appointment->getServiceId() );
						$staff       = \Bookly\Lib\Entities\Staff::find( $appointment->getStaffId() );

						if ( $staff->getWpUserId() ) {
							$postData['wp_user_id']        = $staff->getWpUserId();
							$postData['start_date']        = $appointment->getStartDate();
							$postData['end_date']          = $appointment->getEndDate();
							$postData['appointment_id']    = $appointment->getId();
							$postData['service_id']        = $appointment->getServiceId();
							$postData['service_title']     = $service->getTitle();
							$postData['staff_name']        = $staff->getFullName();
							$postData['staff_email']       = $staff->getEmail();
							$postData['customer_email']    = $wc_item['email'];
							$postData['customer_fullname'] = $wc_item['full_name'];
							$postData['order_url']         = $order->get_checkout_order_received_url();
							$postData['appointment_notes'] = $ca->getNotes();
							$postData['timezone']          = $ca->getTimeZone();
							$postData['timezone_offset']   = $ca->getTimeZoneOffset();

							$appointment = $this->appointments->getByUserAppointment( $staff->getWpUserId(), $appointment->getId() );
							if ( ! empty( $appointment ) ) {
								Meetings::createMeeting( $postData, 'update', true, false, $appointment->name );
							} else {
								Meetings::createMeeting( $postData, 'create', true );
							}
						}
					}
				}
			}
		}
	}

	private static $_instance = null;

	public static function instance(): ?Woo {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}