<?php

use Bookly\Lib;
use Bookly\Frontend\Modules\Booking\Lib\Errors;
use Bookly\Lib\DataHolders\Booking\Order;
use Bookly\Frontend\Components\Booking\InfoText;
use Bookly\Frontend\Modules\Booking\Lib\Steps;

remove_all_actions( 'wp_ajax_nopriv_bookly_save_appointment' );
remove_all_actions( 'wp_ajax_bookly_save_appointment' );
add_action( 'wp_ajax_nopriv_bookly_save_appointment', array( 'DailyCo_Bookly_Booking_Ajax', 'saveAppointment' ) );
add_action( 'wp_ajax_bookly_save_appointment', array( 'DailyCo_Bookly_Booking_Ajax', 'saveAppointment' ) );

class DailyCo_Bookly_Booking_Ajax extends Lib\Base\Ajax {

	/**
	 * Save cart appointments.
	 */
	public static function saveAppointment() {
		$userData = new Lib\UserBookingData( self::parameter( 'form_id' ) );
		if ( $userData->load() ) {
			$failed_cart_key = $userData->cart->getFailedKey();
			if ( $failed_cart_key === null ) {
				$cart_info              = $userData->cart->getInfo();
				$is_payment_disabled    = Lib\Config::paymentStepDisabled();
				$is_pay_locally_enabled = Lib\Config::payLocallyEnabled();
				if ( $is_payment_disabled || $is_pay_locally_enabled || $cart_info->getPayNow() <= 0 ) {
					// Handle coupon.
					$coupon = $userData->getCoupon();
					if ( $coupon ) {
						$coupon->claim()->save();
					}
					// Handle payment.
					$payment = null;
					if ( ! $is_payment_disabled ) {
						if ( $cart_info->getTotal() <= 0 ) {
							if ( $cart_info->withDiscount() ) {
								$payment = new Lib\Entities\Payment();
								$payment
									->setType( Lib\Entities\Payment::TYPE_FREE )
									->setStatus( Lib\Entities\Payment::STATUS_COMPLETED )
									->setPaidType( Lib\Entities\Payment::PAY_IN_FULL )
									->setTotal( 0 )
									->setPaid( 0 )
									->save();
							}
						} else {
							$payment = new Lib\Entities\Payment();
							$options = Lib\Proxy\Shared::preparePaymentOptions(
								array(),
								self::parameter( 'form_id' ),
								Lib\Proxy\Shared::showPaymentSpecificPrices( false ),
								clone $cart_info,
								$userData->extractPaymentStatus()
							);
							$status  = Lib\Entities\Payment::STATUS_PENDING;
							$type    = Lib\Entities\Payment::TYPE_LOCAL;
							foreach ( $options as $gateway => $data ) {
								if ( $data['pay'] == 0 ) {
									$status = Lib\Entities\Payment::STATUS_COMPLETED;
									$type   = Lib\Entities\Payment::TYPE_FREE;
									$cart_info->setGateway( $gateway );
									$payment->setGatewayPriceCorrection( $cart_info->getPriceCorrection() );
									break;
								}
							}

							$payment
								->setType( $type )
								->setStatus( $status )
								->setPaidType( Lib\Entities\Payment::PAY_IN_FULL )
								->setTotal( $cart_info->getTotal() )
								->setTax( $cart_info->getTotalTax() )
								->setPaid( 0 )
								->save();
						}
					}
					// Save cart.
					$order = $userData->save( $payment );
					if ( $payment !== null ) {
						$payment->setDetailsFromOrder( $order, $cart_info )->save();
					}
					// Send notifications.
					Lib\Notifications\Cart\Sender::send( $order );

					//Create Room
					self::createDailyRoom( $order );

					$response = array(
						'success' => true,
					);
				} else {
					$response = array(
						'success' => false,
						'error'   => Errors::PAY_LOCALLY_NOT_AVAILABLE,
					);
				}
			} else {
				$response = array(
					'success'         => false,
					'failed_cart_key' => $failed_cart_key,
					'error'           => Errors::CART_ITEM_NOT_AVAILABLE,
				);
			}
		} else {
			$response = array( 'success' => false, 'error' => Errors::SESSION_ERROR );
		}
		$userData->sessionSave();

		wp_send_json( $response );
	}

	public static function createDailyRoom( Order $order ) {
		$postData = array();
		foreach ( $order->getItems() as $item ) {
			$service = Lib\Entities\Service::find( $item->getAppointment()->getServiceId() );
			$staff   = Lib\Entities\Staff::find( $item->getAppointment()->getStaffId() );
			if ( $staff->getWpUserId() ) {
				$postData['wp_user_id']     = $staff->getWpUserId();
				$postData['start_date']     = $item->getAppointment()->getStartDate();
				$postData['end_date']       = $item->getAppointment()->getEndDate();
				$postData['appointment_id'] = $item->getAppointment()->getId();
				$postData['service_id']     = $item->getAppointment()->getServiceId();
				$postData['service_title']  = $service->getTitle();
				$postData['staff_email']    = $staff->getEmail();
				$postData['customer_email'] = $order->getCustomer()->getEmail();
			}
		}

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

			//Creating Room Now
			$result = dailyco_api()->create_room( $submit );
			update_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $postData['appointment_id'], $result );
			update_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $postData['appointment_id'], $result->name );

			//Reset Cache
			dpen_clear_room_cache();

			//Send Email to customer
			$email_data = array(
				$postData['start_date'],
				$postData['service_title'],
				home_url( '/room/join/?j=' ) . $result->name . '&id=' . get_current_user_id()
			);

			\Headroom\Dailyco\DailyIntegration\Email::prepareEmail( 'tpl-email-invite', $email_data, "Online Session | Headroom", $postData['customer_email'] );

			//Send email to Staff
			$email_data = array(
				$postData['start_date'],
				$postData['service_title'],
				home_url( '/room/start/?s=' ) . $result->name . '&id=' . get_current_user_id(),
				$order->getCustomer()->getFullName(),
				$order->getCustomer()->getEmail()
			);

			\Headroom\Dailyco\DailyIntegration\Email::prepareEmail( 'tpl-email-start', $email_data, "Online Session | Headroom", $postData['staff_email'] );
		}
	}
}

new DailyCo_Bookly_Booking_Ajax();