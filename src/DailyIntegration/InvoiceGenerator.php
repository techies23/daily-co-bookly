<?php

namespace Headroom\Dailyco\DailyIntegration;

use Headroom\Dailyco\Datastore\BooklyDatastore;

class InvoiceGenerator {

	/**
	 * Daily_Co_Bookly_Ajax_Interceptor constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_send_invoice', [ $this, 'send_invoice' ] );
		add_action( 'wp_ajax_send_invoice', [ $this, 'send_invoice' ] );
		add_action( 'init', [ $this, 'view_invoice' ] );

		//Crron
		add_action( 'headroom_invoice_reminder', [ $this, 'cron_reminder_email' ] );
	}

	/**
	 * View Invoice
	 */
	public function view_invoice() {
		if ( isset( $_GET['cs'] ) && isset( $_GET['st'] ) && isset( $_GET['type'] ) && $_GET['type'] === "thappt" && isset( $_GET['view'] ) ) {
			$current_user_id = get_current_user_id();
			$apt_id          = absint( $_GET['view'] );
			//User must be logged in with exact same user to show this data.
			if ( ! empty( $apt_id ) && ( $current_user_id === absint( $_GET['st'] ) || $current_user_id === absint( $_GET['cs'] ) || is_super_admin() ) && is_user_logged_in() ) {
				$appointment = \Bookly\Lib\Entities\Appointment::find( $apt_id );
				if ( empty( $appointment ) ) {
					return;
				}

				$service  = \Bookly\Lib\Entities\Service::find( $appointment->getServiceId() );
				$staff    = \Bookly\Lib\Entities\Staff::find( $appointment->getStaffId() );
				$category = \Bookly\Lib\Entities\Category::find( $service->getCategoryId() );

				if ( $appointment->getCreatedFrom() === "bookly" || $appointment->getCreatedFrom() === "backend" ) {
					foreach ( $staff->getServicesData() as $serv ) {
						if ( $serv['staff_service']->getServiceId() === $appointment->getServiceId() ) {
							$backend_price = $serv['staff_service']->getPrice();
							break;
						}
					}
				}

				if ( ! empty( $appointment->getCustomerAppointments() ) ) {
					foreach ( $appointment->getCustomerAppointments() as $ca ) {
						$customer = \Bookly\Lib\Entities\Customer::find( $ca->getCustomerId() );
						$payment  = \Bookly\Lib\Entities\Payment::find( $ca->getPaymentId() );
						$extras   = $ca->getExtras();
					}
				}

				if ( ! empty( $customer ) ) {
					$med_aid_name  = get_user_meta( $customer->getWpUserId(), 'med_aid_name', true );
					$membership_no = get_user_meta( $customer->getWpUserId(), 'medaidnumber', true );
					$dpendent_code = get_user_meta( $customer->getWpUserId(), 'dependent_code', true );
				}
				$vat_number              = get_user_meta( $staff->getWpUserId(), 'VAT_reg_no', true );
				$practise_number         = get_user_meta( $staff->getWpUserId(), 'practice_no', true );
				$professional_reg_number = get_user_meta( $staff->getWpUserId(), 'reg_number', true );
				$professional_address    = get_user_meta( $staff->getWpUserId(), 'address_tv', true );

				require DPEN_DAILY_CO_DIR_PATH . 'templates/template-invoice.php';
			} else {
				wp_redirect( home_url( '/login' ) );
				exit;
			}

			die;
		}
	}

	public function send_invoice() {
		check_ajax_referer( '_bookly_public_nonce', 'security' );

		$data         = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$apt_id       = isset( $data['aptid'] ) ? absint( $data['aptid'] ) : false;
		$caid         = isset( $data['caid'] ) ? absint( $data['caid'] ) : false;
		$payment_id   = isset( $data['paymentid'] ) ? absint( $data['paymentid'] ) : false;
		$icd_code     = filter_input( INPUT_POST, 'icd' );
		$tariff_code  = filter_input( INPUT_POST, 'tariff_code' );
		$manual_price = filter_input( INPUT_POST, 'manual_price' );

		$customerAppointment = \Bookly\Lib\Entities\CustomerAppointment::find( $caid );
		$appointment         = \Bookly\Lib\Entities\Appointment::find( $apt_id );
		$staff               = \Bookly\Lib\Entities\Staff::find( $appointment->getStaffId() );
		$customer_id         = $customerAppointment->getCustomerId();
		$staff_id            = $staff->getWpUserId();
		$customer            = \Bookly\Lib\Entities\Customer::find( $customer_id );
		$wp_customer_id      = $customer->getWpUserId();
		$payment_id = ! empty( $payment_id ) ? $payment_id : $appointment->getId();

		$extras = array();
		if ( ! empty( $icd_code ) ) {
			$extras[0]['ict_code'] = $icd_code;
		}

		if ( ! empty( $tariff_code ) ) {
			$extras[0]['tariff_code'] = $tariff_code;
		}

		if ( ! empty( $manual_price ) ) {
			$extras[0]['manual_price'] = $manual_price;
		}

		//Ready for email
		$from_email = esc_html( get_option( '_dpen_daily_from_email' ) );
		$from       = ! empty( $from_email ) ? $from_email : 'no-reply@headroom.co.za';
		$headers[]  = 'Content-Type: text/html; charset=UTF-8';
		$headers[]  = 'From: ' . get_bloginfo( 'name' ) . ' < ' . $from . ' >' . "\r\n";

		//Ready email templates
		$email_template_customer  = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-send-invoice-customer.html' );
		$email_template_therapist = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-send-invoice-therapist.html' );

		$search_strings = array(
			'{client_name}',
			'{therapist_name}',
			'{invoice_view_url}',
			'{invoice_id}'
		);
		$replace_string = array(
			$customer->getFullName(),
			$staff->getFullName(),
			home_url( '/therapist_dashboard?type=thappt&st=' . $staff_id . '&cs=' . $wp_customer_id . '&view=' . $apt_id ),
			$payment_id
		);

		$subject = 'Headroom | Invoice #' . $payment_id;
		/**
		 * SEND EMAIL TO CUSTOMER FIRST
		 */
		$body_client      = str_replace( $search_strings, $replace_string, $email_template_customer );
		$sent_mail_client = wp_mail( $customer->getEmail(), $subject, $body_client, $headers );

		/**
		 * SEND EMAIL TO THERAPIST
		 */
		$body_therapist      = str_replace( $search_strings, $replace_string, $email_template_therapist );
		$sent_mail_therapist = wp_mail( $staff->getEmail(), $subject, $body_therapist, $headers );

		$extras[0]['sent_invoice'] = true;
		$extras[0]['invoice_date'] = date( 'Y-m-d' );
		$customerAppointment->setExtras( json_encode( $extras ) );

		//SAVE Extra fields
		$customerAppointment->save();

		wp_die();
	}

	/**
	 * Sent CRON EMAIL PER WEEK for not submitted invoices
	 */
	public function cron_reminder_email() {
		$customerAppointments = BooklyDatastore::getLeftOverInvoicesCustomerAppointments();
		$sent_staff_ids       = array();
		if ( ! empty( $customerAppointments ) ) {
			foreach ( $customerAppointments as $csa ) {
				$appointment_id   = $csa->appointment_id;
				$appointment      = \Bookly\Lib\Entities\Appointment::find( $appointment_id );
				$sent_staff_ids[] = $appointment->getStaffId();
			}

			$staff_ids = array_unique( $sent_staff_ids );
			if ( ! empty( $staff_ids ) ) {
				foreach ( $staff_ids as $staff_id ) {
					$staff = \Bookly\Lib\Entities\Staff::find( $staff_id );

					//Ready for email
					$from_email = esc_html( get_option( '_dpen_daily_from_email' ) );
					$from       = ! empty( $from_email ) ? $from_email : 'no-reply@headroom.co.za';
					$headers[]  = 'Content-Type: text/html; charset=UTF-8';
					$headers[]  = 'From: ' . get_bloginfo( 'name' ) . ' < ' . $from . ' >' . "\r\n";

					//Ready email templates
					$email_tpl = file_get_contents( DPEN_DAILY_CO_DIR_PATH . 'templates/emails/tpl-email-send-invoice-notification-therapist.html' );

					$search_strings = array(
						'{therapist_name}',
					);
					$replace_string = array(
						$staff->getFullName(),
					);

					$subject = 'Headroom| Reminder: Invoices outstanding';

					/**
					 * SEND EMAIL TO THERAPIST
					 */
					$body = str_replace( $search_strings, $replace_string, $email_tpl );
					wp_mail( $staff->getEmail(), $subject, $body, $headers );
				}
			}
		}
	}

	private static $_instance = null;

	public static function instance(): ?InvoiceGenerator {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}