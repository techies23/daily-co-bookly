<?php

class Daily_Co_Bookly_WooCommerce {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'paymentComplete' ), 9999 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'paymentComplete' ), 9999 );
	}

	/**
	 * On Payment Complete
	 *
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function paymentComplete( $order_id ) {
		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $item_id => $order_item ) {
			$wc_item = wc_get_order_item_meta( $item_id, 'bookly' );
			if ( !empty($wc_item) && !empty($wc_item['ca_ids']) ) {
				foreach ( $wc_item['ca_ids'] as $item ) {
					$ca = \Bookly\Lib\Entities\CustomerAppointment::find( $item );
					if ( ! empty( $ca ) ) {
						$appointment = \Bookly\Lib\Entities\Appointment::find( $ca->getAppointmentId() );
						$service     = \Bookly\Lib\Entities\Service::find( $appointment->getServiceId() );
						$staff       = \Bookly\Lib\Entities\Staff::find( $appointment->getStaffId() );

						$room    = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId(), true );
						$details = get_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment->getId(), true );
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
							$postData['timezone_offset']   = $ca->getTimeZoneOffset();

							if ( ! empty( $room ) && ! empty( $details ) ) {
								daily_co_create_meeting( $postData, 'update', true );
							} else {
								daily_co_create_meeting( $postData, 'create', true );
							}
						}
					}
				}
			}
		}
	}
}

new Daily_Co_Bookly_WooCommerce();