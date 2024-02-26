<?php

use \Bookly\Lib\Entities\Appointment;
use \Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\CustomerAppointment;

class Daily_Co_Bookly_Datastore {

	/**
	 * Get Appointments of staff by id
	 *
	 * @param $staff_id
	 * @param $compare
	 * @param $order
	 * @param $show_cancelled
	 *
	 * @return array
	 */
	public static function get_appointments_by_staff( $staff_id, $compare = '>=', $order = 'ASC', $show_cancelled = false ) {
		if ( $show_cancelled ) {
			$stats = array(
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_PENDING,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_APPROVED,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_CANCELLED,
			);
		} else {
			$stats = array(
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_PENDING,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_APPROVED,
			);
		}

		$query = Appointment::query( 'a' )->select( 'a.id,
                ca.payment_id,
                ca.status,
                ca.id        AS ca_id,
                ca.notes,
                ca.number_of_persons,
                ca.extras,
                ca.extras_multiply_nop,
                ca.rating,
                ca.rating_comment,
                a.start_date,
                a.end_date,
                a.staff_any,
                a.staff_id,
                c.id as customer_id,
                c.full_name  AS customer_full_name,
                c.phone      AS customer_phone,
                c.email      AS customer_email,
                st.full_name AS staff_name,
                st.visibility AS staff_visibility,
                p.paid       AS payment,
                p.total      AS payment_total,
                p.type       AS payment_type,
                p.status     AS payment_status,
                COALESCE(s.title, a.custom_service_name) AS service_title,
                (TIME_TO_SEC(TIMEDIFF(a.end_date, a.start_date)) + a.extras_duration) AS service_duration' )
				            ->leftJoin( 'CustomerAppointment', 'ca', 'a.id = ca.appointment_id' )
				            ->leftJoin( 'Service', 's', 's.id = a.service_id' )
				            ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
				            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
				            ->leftJoin( 'Staff', 'st', 'st.id = a.staff_id' )
				            ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = st.id AND ss.service_id = s.id AND ss.location_id = a.location_id' )
		                    ->sortBy( 'start_date' )
		                    ->order( $order )
		                    ->whereIn( 'ca.status', \Bookly\Lib\Proxy\CustomStatuses::prepareBusyStatuses( $stats ) );

		$date        = current_time( 'mysql' );
		/*$currentDate = strtotime( $date );
		$futureDate  = $currentDate + ( 60 * 60 );
		$formatDate  = date( "Y-m-d H:i:s", $futureDate );*/

		$result = $query->where( 'a.staff_id', $staff_id )->whereRaw( 'a.end_date ' . $compare . ' "%s" OR (a.end_date IS NULL)', array( $date ) )->fetchArray();

		return $result;
	}

	/**
	 * Get customer data by customer ID
	 *
	 * @param $customer_id
	 * @param $compare
	 * @param $sort
	 * @param $cancelled
	 *
	 * @return array
	 */
	public static function get_appointments_by_customer( $customer_id, $compare = ">=", $sort = 'ASC', $cancelled = false ) {
		$client_diff = get_option( 'gmt_offset' ) * MINUTE_IN_SECONDS;

		if ( $cancelled ) {
			$post_status = array(
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_PENDING,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_APPROVED,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_CANCELLED,
			);
		} else {
			$post_status = array(
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_PENDING,
				\Bookly\Lib\Entities\CustomerAppointment::STATUS_APPROVED,
			);
		}

		$query = Appointment::query( 'a' )
		                    ->select( 'ca.id AS ca_id,
                    c.name AS category,
                    COALESCE(s.title, a.custom_service_name) AS service,
                    st.full_name AS staff,
                    a.staff_id,
                    a.staff_any,
                    a.service_id,
                    s.*,
                    customer.id as customer_id,
                    ca.status AS appointment_status,
                    ca.extras,
                    ca.collaborative_service_id,
                    ca.compound_token,
                    ca.number_of_persons,
                    ca.custom_fields,
                    ca.appointment_id,
                    IF (ca.compound_service_id IS NULL AND ca.collaborative_service_id IS NULL, COALESCE(ss.price, ss_no_location.price, a.custom_service_price), s.price) AS price,
                    a.start_date AS start_date,
                    a.end_date AS end_date,
                    ca.token' )
		                    ->leftJoin( 'Staff', 'st', 'st.id = a.staff_id' )
		                    ->leftJoin( 'Customer', 'customer', 'customer.wp_user_id = ' . $customer_id )
		                    ->innerJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id AND ca.customer_id = customer.id' )
		                    ->leftJoin( 'Service', 's', 's.id = COALESCE(ca.compound_service_id, ca.collaborative_service_id, a.service_id)' )
		                    ->leftJoin( 'Category', 'c', 'c.id = s.category_id' )
		                    ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = a.staff_id AND ss.service_id = a.service_id AND ss.location_id <=> a.location_id' )
		                    ->leftJoin( 'StaffService', 'ss_no_location', 'ss_no_location.staff_id = a.staff_id AND ss_no_location.service_id = a.service_id AND ss_no_location.location_id IS NULL' )
		                    ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
		                    ->groupBy( 'COALESCE(compound_token, collaborative_token, ca.id)' )
		                    ->sortBy( 'start_date' )
		                    ->order( $sort )
		                    ->whereIn( 'ca.status', \Bookly\Lib\Proxy\CustomStatuses::prepareBusyStatuses( $post_status ) );

		$date        = current_time( 'mysql' );
		/*$currentDate = strtotime( $date );
		$futureDate  = $currentDate + ( 60 * 60 );
		$formatDate  = date( "Y-m-d H:i:s", $futureDate );*/

		$result = $query->whereRaw( 'a.end_date ' . $compare . ' "%s" OR (a.end_date IS NULL)', array(
			$date
		) )->fetchArray();

		return $result;
	}

	public static function getStaffbyUserID( $user_id ) {
		global $wpdb;

		$query = \Bookly\Lib\Entities\Staff::query( 's' );
		$query->select( 's.id, s.category_id, s.full_name, s.visibility, s.wp_user_id, s.position, email, phone, wpu.display_name AS wp_user' )
		      ->tableJoin( $wpdb->users, 'wpu', 'wpu.ID = s.wp_user_id' )
		      ->sortBy( 'position' );
		$query->where( 's.wp_user_id', $user_id );

		$staff = $query->fetchRow();

		return $staff;
	}

	public static function getLeftOverInvoicesCustomerAppointments() {
		global $wpdb;
		$tbl_name     = $wpdb->prefix . 'bookly_customer_appointments';
		$query        = 'SELECT id, JSON_EXTRACT(extras, "$[0].sent_invoice") AS invoice_sent FROM ' . $tbl_name . ' WHERE JSON_EXTRACT(extras, "$[0].sent_invoice") = true';
		$sentInvoices = $wpdb->get_results( $query );
		$exclude      = array();
		if ( ! empty( $sentInvoices ) ) {
			foreach ( $sentInvoices as $sentInvoice ) {
				$exclude[] = $sentInvoice->id;
			}
		}

		$notSentInvoicesQuery  = "SELECT * FROM " . $tbl_name . " WHERE id NOT IN ( '" . implode( "', '", $exclude ) . "')";
		$notSentInvoicesResult = $wpdb->get_results( $notSentInvoicesQuery );

		return $notSentInvoicesResult;
	}

	public static function getAppointments() {
		$query = Bookly\Lib\Entities\Appointment::query( 's' );
		$query->select( '*' )
		      ->sortBy( 'start_date' );

		$list = $query->fetchArray();

		return $list;
	}

	public static function getStaffList() {
		global $wpdb;

		$query = Bookly\Lib\Entities\Staff::query( 's' );
		$query->select( 's.*, email, phone, wpu.display_name AS wp_user' )
		      ->tableJoin( $wpdb->users, 'wpu', 'wpu.ID = s.wp_user_id' )
		      ->sortBy( 'position' );
		if ( ! Bookly\Lib\Utils\Common::isCurrentUserAdmin() ) {
			$query->where( 's.wp_user_id', get_current_user_id() );
		}

		$list = $query->fetchArray();

		return $list;
	}

	public static function getStaffServicesPrice( $user_id, $price_only = false ) {
		global $wpdb;

		$query = \Bookly\Lib\Entities\Service::query( 's' );
		if ( $price_only ) {
			$query->select( 'ss.price' );
		} else {
			$query->select( 's.title, s.id, s.type, ss.price' );
		}

		$query->leftJoin( 'StaffService', 'ss', 'ss.service_id = s.id' );
		$query->sortBy( 'position' );
		$query->where( 'ss.staff_id', $user_id );

		$result = $query->fetchArray();
		if ( $price_only ) {
			$result_price = array();
			foreach ( $result as $price ) {
				$result_price[] = $price['price'];
			}

			return $result_price;
		}

		return $result;
	}

	public static function getWpUserID( $user_id ) {
		$result = \Bookly\Lib\Entities\Customer::query()->where( 'wp_user_id', $user_id )->findOne();

		return $result;
	}

	/**
	 * @param $bookly_user_id
	 *
	 * @return object \Bookly\Lib\Entities\Staff
	 */
	public static function getStaffUserIdBy_booklyUserId( $bookly_user_id ) {
		$staff = \Bookly\Lib\Entities\Staff::query( 's' )->where( 'id', absint( $bookly_user_id ) )->findOne();

		return $staff;
	}
}