<?php

add_action( 'um_after_account_general_button', 'headroom_account_page_return_btn' );
function headroom_account_page_return_btn() {
	global $current_user;
	if ( ! empty( $current_user ) ) {
		if ( in_array( 'um_therapist', $current_user->roles ) ) {
			?>
            <div style="margin-left:10px;" class="um-left um-custom-back-left-wrapper">
                <a href="<?php echo home_url( '/therapist_dashboard' ) ?>" class="um-button um-custom-back-btn">Back to my Dashboard</a>
            </div>
			<?php
		} else {
			?>
            <div style="margin-left:10px;" class="um-left um-custom-back-left-wrapper">
                <a href="<?php echo home_url( '/client_dashboard' ) ?>" class="um-button um-custom-back-btn">Back to my Dashboard</a>
            </div>
			<?php
		}
	}
}

//add_action( 'um_registration_set_extra_data', 'headroom_registered_user', 50, 2 );
add_action( 'um_after_save_registration_details', 'headroom_registered_user', 10, 2 );
#add_action( 'set_user_role', 'headroom_on_role_change', 10, 2 );
add_action( 'um_after_user_account_updated', 'headroom_edit_user_profile_frontned', 10, 2 );
add_action( 'um_user_after_updating_profile', 'headroom_save_addition_profie_fields_frontend', 10, 2 );
function headroom_edit_user_profile_frontned( $user_id, $changes ) {
	/** @var \Bookly\Lib\Entities\Customer $bookly_customer */
	$bookly_customer = Daily_Co_Bookly_Datastore::getWpUserID( $user_id );
	$user            = get_userdata( $user_id );
	if ( ! empty( $bookly_customer ) ) {
		$bookly_customer->setFullName( $user->first_name . ' ' . $user->last_name );
		if ( ! empty( $user->first_name ) ) {
			$bookly_customer->setFirstName( $user->first_name );
		}

		if ( ! empty( $user->last_name ) ) {
			$bookly_customer->setLastName( $user->last_name );
		}

		$customer = \Bookly\Lib\Entities\Customer::find( $bookly_customer->getId() );
		// Overwrite only if value is not empty.
		if ( $customer->getFacebookId() ) {
			$bookly_customer->setFacebookId( $customer->getFacebookId() );
		}
		if ( $customer->getPhone() != '' ) {
			$bookly_customer->setPhone( $customer->getPhone() );
		}
		if ( $customer->getEmail() != '' ) {
			$bookly_customer->setEmail( trim( $customer->getEmail() ) );
		}
		if ( $customer->getCountry() != '' ) {
			$bookly_customer->setCountry( $customer->getCountry() );
		}
		if ( $customer->getState() != '' ) {
			$bookly_customer->setState( $customer->getState() );
		}
		if ( $customer->getPostcode() != '' ) {
			$bookly_customer->setPostcode( $customer->getPostcode() );
		}
		if ( $customer->getCity() != '' ) {
			$bookly_customer->setCity( $customer->getCity() );
		}
		if ( $customer->getStreet() != '' ) {
			$bookly_customer->setStreet( $customer->getStreet() );
		}
		if ( $customer->getStreetNumber() != '' ) {
			$bookly_customer->setStreetNumber( $customer->getStreetNumber() );
		}
		if ( $customer->getAdditionalAddress() != '' ) {
			$bookly_customer->setAdditionalAddress( $customer->getAdditionalAddress() );
		}

		// Customer information fields.
		$bookly_customer->setInfoFields( json_encode( $customer->getInfoFields() ) );

		$bookly_customer->save();
	}
}

/**
 * Save Addtional fields of UM
 *
 * @param $to_update
 * @param $user_id
 */
function headroom_save_addition_profie_fields_frontend( $to_update, $user_id ) {
	/** @var \Bookly\Lib\Entities\Customer $bookly_customer */
	$bookly_customer = Daily_Co_Bookly_Datastore::getWpUserID( absint( $user_id ) );
	if ( ! empty( $bookly_customer ) ) {
		if ( ! empty( $to_update['mobile_number'] ) ) {
			$bookly_customer->setPhone( $to_update['mobile_number'] );
		}

		if ( ! empty( $to_update['birth_date'] ) ) {
			$date     = explode( '/', $to_update['birth_date'] );
			$birthday = array(
				'year'  => $date[0],
				'month' => $date[1],
				'day'   => $date[2]
			);
			$birthday = implode( '-', $birthday );
			$bookly_customer->setBirthday( $birthday );
		}

		if ( ! empty( $to_update['city'] ) ) {
			$bookly_customer->setCity( $to_update['city'] );
		}

		if ( ! empty( $to_update['country'] ) ) {
			$bookly_customer->setCountry( $to_update['country'] );
		}

		if ( ! empty( $to_update['state'] ) ) {
			$bookly_customer->setState( $to_update['state'] );
		}

		if ( ! empty( $to_update['postal_code'] ) ) {
			$bookly_customer->setPostcode( $to_update['postal_code'] );
		}

		$bookly_customer->save();
	}
}

/**
 * Ultimate member on register create and link user to bookly Customer Form as well.
 *
 * @param $user_id
 * @param $args
 */
function headroom_registered_user( $user_id, $args ) {
	$user = get_userdata( $user_id );
	if ( in_array( 'um_clients', $user->roles ) ) {
		headroom_save_customerdata_bookly( $user, $user_id, $args );
	}
}

/**
 * Trigger when user role is changed form wp-admin > users page
 *
 * @param $user_id
 * @param $role
 */
function headroom_on_role_change( $user_id, $role ) {
	if ( $role === "um_clients" ) {
		$user = get_userdata( $user_id );
		headroom_save_customerdata_bookly( $user, $user_id );
	}
}

/**
 * Finally save customer meta into bookly
 *
 * @param $user
 * @param $user_id
 * @param $args
 */
function headroom_save_customerdata_bookly( $user, $user_id, $args = false ) {
	$params               = array();
	$params['wp_user_id'] = $user_id;

	$params['full_name']  = ! empty( $args['first_name'] ) && ! empty( $args['last_name'] ) ? $args['first_name'] . ' ' . $args['last_name'] : $user->display_name;
	$params['first_name'] = ! empty( $args['first_name'] ) ? $args['first_name'] : $user->first_name;
	$params['last_name']  = ! empty( $args['last_name'] ) ? $args['last_name'] : $user->last_name;
	$params['email']      = ! empty( $args['user_email'] ) ? $args['user_email'] : $user->user_email;

	if ( ! empty( $args ) ) {
		$args['dob']        = ! empty( $args['dob'] ) ? $args['dob'] : $args['date_of_birth'];
		$date               = explode( '/', date( 'Y/m/d', strtotime( $args['dob'] ) ) );
		$birthday           = array(
			'year'  => $date[0],
			'month' => $date[1],
			'day'   => $date[2]
		);
		$birthday           = implode( '-', $birthday );
		$params['birthday'] = $birthday;
		$params['phone']    = $args['mobile_number'];
		$params['city']     = $args['city'];
		$params['country']  = $args['country'];
	}

	$bookly_customer = Daily_Co_Bookly_Datastore::getWpUserID( $user_id );
	if ( empty( $bookly_customer ) ) {
		#$prepared_formdata = \Bookly\Backend\Components\Dialogs\Customer\Edit\Proxy\CustomerInformation::prepareCustomerFormData( $params );
		$form = new \Bookly\Lib\Entities\Customer();
		$form->setWpUserId( $user_id );
		$form->setFullName( $params['full_name'] );
		$form->setFirstName( $params['first_name'] );
		$form->setLastName( $params['last_name'] );
		if ( ! empty( $params['phone'] ) ) {
			$form->setPhone( $params['phone'] );
		}
		$form->setEmail( $params['email'] );
		if ( ! empty( $params['city'] ) ) {
			$form->setCity( $params['city'] );
		}
		if ( ! empty( $params['country'] ) ) {
			$form->setCountry( $params['country'] );
		}
		if ( ! empty( $params['birthday'] ) ) {
			$date     = explode( '/', $params['birthday'] );
			$birthday = array(
				'year'  => $date[0],
				'month' => $date[1],
				'day'   => $date[2]
			);
			$birthday = implode( '-', $birthday );
			$form->setBirthday( $birthday );
		}

		$form->save();
	}
}