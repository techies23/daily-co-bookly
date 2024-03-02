<?php

/**
 * Class Daily_Co_Bookly_Ajax_Interceptor
 */
class Daily_Co_Bookly_Ajax_Interceptor {

	/**
	 * Service names here
	 *
	 * @var array
	 */
	private $bookly_service_names;

	/**
	 * Daily_Co_Bookly_Ajax_Interceptor constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'intercept_ajax' ] );
		add_filter( 'um_user_permissions_filter', [ $this, 'display_without_cache' ], 10, 2 );
	}

	/**
	 * Intercept AJAX call for bookly staff update
	 */
	public function intercept_ajax() {
		if ( wp_doing_ajax() && isset( $_POST['action'] ) ) {
			$ajax_act = $_POST['action'];
			switch ( $ajax_act ) {
				case 'bookly_staff_services_update':
					$this->rate_30mins();
					break;
				case 'bookly_save_customer':
					$this->save_um_fields_on_bookly_customer();
					break;
			}
		}
	}

	public function rate_30mins() {
		$services = filter_input( INPUT_POST, 'service', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$price    = filter_input( INPUT_POST, 'price', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$staff_id = filter_input( INPUT_POST, 'staff_id' );

		if ( empty( $services ) ) {
			return;
		}

		$this->bookly_service_names = [
			'30-minutes-consultation',
			'30 minutes consultation',
			'60min session'
		];

		foreach ( $services as $k => $service ) {
			$service = \Bookly\Lib\Entities\Service::find( $service );
			if ( in_array( $service->getTitle(), $this->bookly_service_names ) ) {
				$staff   = \Bookly\Lib\Entities\Staff::find( $staff_id );
				$user_id = ! empty( $staff->getWpUserId() ) ? $staff->getWpUserId() : false;
				if ( $user_id ) {
					update_user_meta( $user_id, 'rate_30min', $price[ $k ] );
					update_user_meta( $user_id, 'rate_30mins', $price[ $k ] );
					update_user_meta( $user_id, 'rate_30_mis', $price[ $k ] );
				}
			}
		}
	}

	/**
	 * Resolve cache
	 *
	 * @param $find_user
	 * @param $user_id
	 *
	 * @return mixed
	 */
	public function display_without_cache( $find_user, $user_id ) {
		$find_user['rate_30min']  = get_user_meta( $user_id, 'rate_30min', true );
		$find_user['rate_30mins'] = get_user_meta( $user_id, 'rate_30mins', true );
		$find_user['rate_30_mis'] = get_user_meta( $user_id, 'rate_30_mis', true );

		return $find_user;
	}

	/**
	 * Save Meta fields on DB when saved on Bookly
	 */
	public function save_um_fields_on_bookly_customer() {
		$wp_user_id = filter_input( INPUT_POST, 'wp_user_id' );
		$phone      = filter_input( INPUT_POST, 'phone' );
		$birthday   = filter_input( INPUT_POST, 'birthday' );
		$city       = filter_input( INPUT_POST, 'city' );
		$country    = filter_input( INPUT_POST, 'country' );
		$state      = filter_input( INPUT_POST, 'state' );
		$postcode   = filter_input( INPUT_POST, 'postcode' );
		$first_name = filter_input( INPUT_POST, 'first_name' );
		$last_name  = filter_input( INPUT_POST, 'last_name' );
		if ( ! empty( $wp_user_id ) ) {
			//Save in UM Fields
			$wp_fields = array(
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'mobile_number' => $phone,
				'date_of_birth' => $birthday,
				'city'          => $city,
				'country'       => $country,
				'state'         => $state,
				'postal_code'   => $postcode,
			);
			foreach ( $wp_fields as $k => $field ) {
				update_user_meta( $wp_user_id, $k, $field );
			}
		}
	}
}

new Daily_Co_Bookly_Ajax_Interceptor();