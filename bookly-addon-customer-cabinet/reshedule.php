<?php

use Bookly\Lib as BooklyLib;

remove_all_actions( 'wp_ajax_nopriv_bookly_customer_cabinet_save_reschedule' );
remove_all_actions( 'wp_ajax_bookly_customer_cabinet_save_reschedule' );
add_action( 'wp_ajax_nopriv_bookly_customer_cabinet_save_reschedule', array( 'DailyCo_Bookly_Reschedule_Ajax', 'saveReschedule' ) );
add_action( 'wp_ajax_bookly_customer_cabinet_save_reschedule', array( 'DailyCo_Bookly_Reschedule_Ajax', 'saveReschedule' ) );

/**
 * Class Ajax
 * @package BooklyCustomerCabinet\Frontend\Components\Dialogs\Reschedule
 */
class DailyCo_Bookly_Reschedule_Ajax extends BooklyLib\Base\Ajax {

	/**
	 * Save rescheduled appointment with a new start date
	 */
	public static function saveReschedule() {
		$response = array( 'success' => true, 'errors' => array() );

		$customer    = BooklyLib\Entities\Customer::query()->where( 'wp_user_id', get_current_user_id() )->findOne();

		$ca_id = self::parameter( 'ca_id' );

		$ca = BooklyLib\Entities\CustomerAppointment::find( $ca_id );

		//Delete old meeting stuff
		$delete_apt   = BooklyLib\Entities\Appointment::find( $ca->getAppointmentId() );
		$delete_staff = \Bookly\Lib\Entities\Staff::find( $delete_apt->getStaffId() );
		delete_user_meta( $delete_staff->getWpUserId(), '_daily_co_room_name_' . $delete_apt->getId() );
		delete_user_meta( $delete_staff->getWpUserId(), '_daily_co_room_details_' . $delete_apt->getId() );

		$is_compound      = false;
		$is_collaborative = false;
		if ( $ca->getCustomerId() == $customer->getId() ) {
			if ( $ca->getCompoundToken() ) {
				$ca_list          = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                                          ->where( 'ca.compound_token', $ca->getCompoundToken() )
				                                                          ->find();
				$is_compound      = true;
				$compound_service = BooklyLib\Entities\Service::find( $ca->getCompoundServiceId() );
			} elseif ( $ca->getCollaborativeToken() ) {
				$ca_list               = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                                               ->where( 'ca.collaborative_token', $ca->getCollaborativeToken() )
				                                                               ->find();
				$is_collaborative      = true;
				$collaborative_service = BooklyLib\Entities\Service::find( $ca->getCollaborativeServiceId() );
			} else {
				$ca_list = array( $ca );
			}
			$slots = json_decode( self::parameter( 'slot' ), true );

			$reshedule_appointments = BooklyLib\Entities\Appointment::query()
			                                                        ->whereIn( 'id', array_map( function ( $ca ) {
				                                                        return $ca->getAppointmentId();
			                                                        }, $ca_list ) )->indexBy( 'id' )->fetchArray();
			foreach ( $reshedule_appointments as &$data ) {
				unset(
					$data['id'],
					$data['google_event_id'],
					$data['google_event_etag'],
					$data['outlook_event_id'],
					$data['outlook_event_change_key'],
					$data['outlook_event_series_id'],
					$data['created_from'] );
			}

			/** @var BooklyLib\Entities\CustomerAppointment $ca */
			$ignore_appointments = array();
			foreach ( $ca_list as $ca ) {
				$ignore_appointments[] = $ca->getAppointmentId();
			}
			/** @var BooklyLib\Entities\CustomerAppointment $ca */
			foreach ( $ca_list as $index => $ca ) {
				list( $service_id, $staff_id, $bound_start ) = $slots[ $index ];
				if ( $service_id === null ) {
					// Custom service.
					$service     = new BooklyLib\Entities\Service();
					$appointment = BooklyLib\Entities\Appointment::find( $ca->getAppointmentId() );
					$service->setDuration( strtotime( $appointment->getEndDate() ) - strtotime( $appointment->getStartDate() ) );
					BooklyLib\Entities\Service::putInCache( null, $service );
				}
				$service   = BooklyLib\Entities\Service::find( $service_id );
				$duration  = $service->getDuration() + BooklyLib\Proxy\ServiceExtras::getTotalDuration( (array) json_decode( $ca->getExtras(), true ) );
				$bound_end = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $duration );

				if ( BooklyLib\Slots\DatePoint::now()->modify( BooklyLib\Proxy\Pro::getMinimumTimePriorBooking( $service_id ) )->toClientTz()->value()->getTimestamp() > strtotime( $bound_start ) ) {
					// Check minimum time requirement prior to booking
					$response['success']                      = false;
					$response['errors']['time_prior_booking'] = true;
				} elseif ( strtotime( $bound_start ) > current_time( 'timestamp' ) + BooklyLib\Config::getMaximumAvailableDaysForBooking() * DAY_IN_SECONDS ) {
					// Check max available days for booking
					$response['success']                    = false;
					$response['errors']['max_booking_date'] = true;
				}
				// Search intersect appointments
				$query = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                               ->select( 'ss.capacity_max, SUM(ca.number_of_persons) AS total_number_of_persons,
                    DATE_SUB(a.start_date, INTERVAL COALESCE(s.padding_left,0) SECOND) AS bound_left,
                    DATE_ADD(a.end_date,   INTERVAL (COALESCE(s.padding_right,0) + IF(ca.extras_consider_duration, a.extras_duration, 0)) SECOND) AS bound_right' )
				                                               ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
				                                               ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = a.staff_id AND ss.service_id = a.service_id' )
				                                               ->leftJoin( 'Service', 's', 's.id = a.service_id' )
				                                               ->where( 'a.staff_id', $staff_id )
				                                               ->whereIn( 'ca.status', BooklyLib\Proxy\CustomStatuses::prepareBusyStatuses( array(
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_PENDING,
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_APPROVED,
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_WAITLISTED,
				                                               ) ) )
				                                               ->groupBy( 'a.service_id, a.start_date' )
				                                               ->whereNotIn( 'a.id', $ignore_appointments )
				                                               ->havingRaw( '%s > bound_left AND bound_right > %s AND ( total_number_of_persons + %d ) > ss.capacity_max',
					                                               array( $bound_end, $bound_start, 1 ) )
				                                               ->limit( 1 );
				$rows  = $query->execute( BooklyLib\Query::HYDRATE_NONE );
				if ( $rows != 0 ) {
					// Exist intersect appointment, time not available.
					$response['success']            = false;
					$response['errors']['occupied'] = true;
					break;
				}
			}

			if ( empty ( $response['errors'] ) ) {
				if ( $is_compound ) {
					$new_token = BooklyLib\Utils\Common::generateToken( '\Bookly\Lib\Entities\CustomerAppointment', 'compound_token' );
					$compound  = BooklyLib\DataHolders\Booking\Compound::create( $compound_service )->setToken( $new_token );
				} elseif ( $is_collaborative ) {
					$new_token     = BooklyLib\Utils\Common::generateToken( '\Bookly\Lib\Entities\CustomerAppointment', 'collaborative_token' );
					$collaborative = BooklyLib\DataHolders\Booking\Collaborative::create( $collaborative_service )->setToken( $new_token );
				}

				foreach ( $ca_list as $index => $ca ) {
					list( $service_id, $staff_id, $bound_start ) = $slots[ $index ];
					$service     = BooklyLib\Entities\Service::find( $service_id );
					$duration    = $service->getDuration() + BooklyLib\Proxy\ServiceExtras::getTotalDuration( (array) json_decode( $ca->getExtras(), true ) );
					$bound_end   = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $duration );
					$appointment = BooklyLib\Entities\Appointment::query( 'a' )
					                                             ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
					                                             ->where( 'a.staff_id', $staff_id )
					                                             ->whereNotIn( 'ca.status', BooklyLib\Proxy\CustomStatuses::prepareFreeStatuses( array(
						                                             BooklyLib\Entities\CustomerAppointment::STATUS_CANCELLED,
						                                             BooklyLib\Entities\CustomerAppointment::STATUS_REJECTED,
					                                             ) ) )
					                                             ->whereLte( 'start_date', $bound_start )
					                                             ->whereGte( 'end_date', $bound_end )
					                                             ->findOne();
					if ( ! $appointment ) {
						$appointment = new BooklyLib\Entities\Appointment( $reshedule_appointments[ $ca->getAppointmentId() ] );
						$appointment
							->setStaffId( $staff_id )
							->setStartDate( $bound_start )
							->setEndDate( $bound_end )
							->save();
						BooklyLib\Utils\Log::createEntity( $appointment, __METHOD__ );
					}

					$ca_data = $ca->getFields();
					unset( $ca_data['id'] );
					$new_ca = new BooklyLib\Entities\CustomerAppointment( $ca_data );
					$new_ca
						->setAppointment( $appointment )
						->setStatus( BooklyLib\Proxy\CustomerGroups::takeDefaultAppointmentStatus( BooklyLib\Config::getDefaultAppointmentStatus(), $customer->getGroupId() ) )
						->setCreatedAt( current_time( 'mysql' ) )
						->setToken( '' );
					if ( $is_compound ) {
						$new_ca->setCompoundToken( $new_token );
					} elseif ( $is_collaborative ) {
						$new_ca->setCollaborativeToken( $new_token );
					}
					$new_ca->save();
					BooklyLib\Utils\Log::createEntity( $new_ca, __METHOD__ );

					BooklyLib\Proxy\Pro::syncGoogleCalendarEvent( $appointment );
					BooklyLib\Proxy\OutlookCalendar::syncEvent( $appointment );

					BooklyLib\Utils\Log::updateEntity( $ca, __METHOD__, 'Cancel appointment' );
					$ca->cancel();
					$item = BooklyLib\DataHolders\Booking\Simple::create( $new_ca )->setService( $service )->setAppointment( $appointment );
					if ( $is_compound ) {
						$item = $compound->addItem( $item );
					} elseif ( $is_collaborative ) {
						$item = $collaborative->addItem( $item );
					}

					self::reschedule_client( $item->getAppointment(), $item->getCA() );
				}
				if ( isset( $item ) ) {
					BooklyLib\Notifications\Booking\Sender::send( $item );
				}
			}
		}

		wp_send_json( $response );
	}


	/**
	 * Save rescheduled appointment with a new start date$
	 */
	public static function oldsaveReschedule() {
		$response    = array( 'success' => true, 'errors' => array() );
		$customer    = BooklyLib\Entities\Customer::query()->where( 'wp_user_id', get_current_user_id() )->findOne();
		$customer_id = $customer->getId();

		$ca_id = self::parameter( 'ca_id' );

		$ca = BooklyLib\Entities\CustomerAppointment::find( $ca_id );

		//Delete old meeting stuff
		$delete_apt   = BooklyLib\Entities\Appointment::find( $ca->getAppointmentId() );
		$delete_staff = \Bookly\Lib\Entities\Staff::find( $delete_apt->getStaffId() );
		delete_user_meta( $delete_staff->getWpUserId(), '_daily_co_room_name_' . $delete_apt->getId() );
		delete_user_meta( $delete_staff->getWpUserId(), '_daily_co_room_details_' . $delete_apt->getId() );

		$is_compound      = false;
		$is_collaborative = false;
		if ( $ca->getCustomerId() == $customer_id ) {
			if ( $ca->getCompoundToken() ) {
				$ca_list          = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                                          ->where( 'ca.compound_token', $ca->getCompoundToken() )
				                                                          ->find();
				$is_compound      = true;
				$compound_service = BooklyLib\Entities\Service::find( $ca->getCompoundServiceId() );
			} elseif ( $ca->getCollaborativeToken() ) {
				$ca_list               = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                                               ->where( 'ca.collaborative_token', $ca->getCollaborativeToken() )
				                                                               ->find();
				$is_collaborative      = true;
				$collaborative_service = BooklyLib\Entities\Service::find( $ca->getCollaborativeServiceId() );
			} else {
				$ca_list = array( $ca );
			}
			$slots = json_decode( self::parameter( 'slot' ), true );

			$reshedule_appointments = BooklyLib\Entities\Appointment::query()
			                                                        ->whereIn( 'id', array_map( function ( $ca ) {
				                                                        return $ca->getAppointmentId();
			                                                        }, $ca_list ) )->indexBy( 'id' )->fetchArray();
			foreach ( $reshedule_appointments as &$data ) {
				unset(
					$data['id'],
					$data['google_event_id'],
					$data['google_event_etag'],
					$data['outlook_event_id'],
					$data['outlook_event_change_key'],
					$data['outlook_event_series_id'],
					$data['created_from'] );
			}

			/** @var BooklyLib\Entities\CustomerAppointment $ca */
			foreach ( $ca_list as $index => $ca ) {
				list( $service_id, $staff_id, $bound_start ) = $slots[ $index ];
				if ( $service_id === null ) {
					// Custom service.
					$service     = new BooklyLib\Entities\Service();
					$appointment = BooklyLib\Entities\Appointment::find( $ca->getAppointmentId() );
					$service->setDuration( strtotime( $appointment->getEndDate() ) - strtotime( $appointment->getStartDate() ) );
					BooklyLib\Entities\Service::putInCache( null, $service );
				}
				$service   = BooklyLib\Entities\Service::find( $service_id );
				$duration  = $service->getDuration() + BooklyLib\Proxy\ServiceExtras::getTotalDuration( (array) json_decode( $ca->getExtras(), true ) );
				$bound_end = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $duration );

				if ( BooklyLib\Slots\DatePoint::now()->modify( BooklyLib\Proxy\Pro::getMinimumTimePriorBooking() )->toClientTz()->value()->getTimestamp() > strtotime( $bound_start ) ) {
					// Check minimum time requirement prior to booking
					$response['success']                      = false;
					$response['errors']['time_prior_booking'] = true;
				} elseif ( strtotime( $bound_start ) > current_time( 'timestamp' ) + BooklyLib\Config::getMaximumAvailableDaysForBooking() * DAY_IN_SECONDS ) {
					// Check max available days for booking
					$response['success']                    = false;
					$response['errors']['max_booking_date'] = true;
				}
				// Search intersect appointments
				$query = BooklyLib\Entities\CustomerAppointment::query( 'ca' )
				                                               ->select( 'ss.capacity_max, SUM(ca.number_of_persons) AS total_number_of_persons,
                    DATE_SUB(a.start_date, INTERVAL COALESCE(s.padding_left,0) SECOND) AS bound_left,
                    DATE_ADD(a.end_date,   INTERVAL (COALESCE(s.padding_right,0) + IF(ca.extras_consider_duration, a.extras_duration, 0)) SECOND) AS bound_right' )
				                                               ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
				                                               ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = a.staff_id AND ss.service_id = a.service_id' )
				                                               ->leftJoin( 'Service', 's', 's.id = a.service_id' )
				                                               ->where( 'a.staff_id', $staff_id )
				                                               ->whereIn( 'ca.status', BooklyLib\Proxy\CustomStatuses::prepareBusyStatuses( array(
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_PENDING,
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_APPROVED,
					                                               BooklyLib\Entities\CustomerAppointment::STATUS_WAITLISTED,
				                                               ) ) )
				                                               ->groupBy( 'a.service_id, a.start_date' )
				                                               ->havingRaw( '%s > bound_left AND bound_right > %s AND ( total_number_of_persons + %d ) > ss.capacity_max',
					                                               array( $bound_end, $bound_start, 1 ) )
				                                               ->limit( 1 );
				$rows  = $query->execute( BooklyLib\Query::HYDRATE_NONE );
				if ( $rows != 0 ) {
					// Exist intersect appointment, time not available.
					$response['success']            = false;
					$response['errors']['occupied'] = true;
					break;
				}
			}

			if ( empty ( $response['errors'] ) ) {
				if ( $is_compound ) {
					$new_token = BooklyLib\Utils\Common::generateToken( '\Bookly\Lib\Entities\CustomerAppointment', 'compound_token' );
					$compound  = BooklyLib\DataHolders\Booking\Compound::create( $compound_service )->setToken( $new_token );
				} elseif ( $is_collaborative ) {
					$new_token     = BooklyLib\Utils\Common::generateToken( '\Bookly\Lib\Entities\CustomerAppointment', 'collaborative_token' );
					$collaborative = BooklyLib\DataHolders\Booking\Collaborative::create( $collaborative_service )->setToken( $new_token );
				}

				foreach ( $ca_list as $index => $ca ) {
					list( $service_id, $staff_id, $bound_start ) = $slots[ $index ];
					$service     = BooklyLib\Entities\Service::find( $service_id );
					$duration    = $service->getDuration() + BooklyLib\Proxy\ServiceExtras::getTotalDuration( (array) json_decode( $ca->getExtras(), true ) );
					$bound_end   = date( 'Y-m-d H:i:s', strtotime( $bound_start ) + $duration );
					$appointment = BooklyLib\Entities\Appointment::query( 'a' )
					                                             ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
					                                             ->where( 'a.staff_id', $staff_id )
					                                             ->whereNotIn( 'ca.status', BooklyLib\Proxy\CustomStatuses::prepareFreeStatuses( array(
						                                             BooklyLib\Entities\CustomerAppointment::STATUS_CANCELLED,
						                                             BooklyLib\Entities\CustomerAppointment::STATUS_REJECTED,
					                                             ) ) )
					                                             ->whereLt( 'start_date', $bound_start )
					                                             ->whereGt( 'end_date', $bound_end )
					                                             ->findOne();
					if ( ! $appointment ) {
						$appointment = new BooklyLib\Entities\Appointment( $reshedule_appointments[ $ca->getAppointmentId() ] );
						$appointment
							->setStaffId( $staff_id )
							->setStartDate( $bound_start )
							->setEndDate( $bound_end )
							->save();
					}

					$ca_data = $ca->getFields();
					unset( $ca_data['id'] );
					$new_ca = new BooklyLib\Entities\CustomerAppointment( $ca_data );
					$new_ca
						->setAppointment( $appointment )
						->setStatus( BooklyLib\Proxy\CustomerGroups::takeDefaultAppointmentStatus( get_option( 'bookly_gen_default_appointment_status' ), $customer->getGroupId() ) )
						->setCreatedAt( current_time( 'mysql' ) )
						->setToken( '' );
					if ( $is_compound ) {
						$new_ca->setCompoundToken( $new_token );
					} elseif ( $is_collaborative ) {
						$new_ca->setCollaborativeToken( $new_token );
					}
					$new_ca->save();

					BooklyLib\Proxy\Pro::syncGoogleCalendarEvent( $appointment );
					BooklyLib\Proxy\OutlookCalendar::syncEvent( $appointment );

					$ca->cancel();
					$item = BooklyLib\DataHolders\Booking\Simple::create( $new_ca )->setService( $service )->setAppointment( $appointment );
					if ( $is_compound ) {
						$item = $compound->addItem( $item );
					} elseif ( $is_collaborative ) {
						$item = $collaborative->addItem( $item );
					}

					self::reschedule_client( $item->getAppointment(), $item->getCA() );
				}
				if ( isset( $item ) ) {
					BooklyLib\Notifications\Booking\Sender::send( $item );
				}
			}
		}

		wp_send_json( $response );
	}

	/**
	 * @param BooklyLib\Entities\Appointment $appointments
	 * @param BooklyLib\Entities\CustomerAppointment $customerAppointments
	 *
	 * @throws Exception
	 */
	public static function reschedule_client( $appointments, $customerAppointments ) {
		$customer = \Bookly\Lib\Entities\Customer::find( $customerAppointments->getCustomerId() );
		$service  = \Bookly\Lib\Entities\Service::find( $appointments->getServiceId() );
		$staff    = \Bookly\Lib\Entities\Staff::find( $appointments->getStaffId() );

		$room    = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointments->getId(), true );
		$details = get_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointments->getId(), true );
		if ( $staff->getWpUserId() ) {
			$postData['wp_user_id']        = $staff->getWpUserId();
			$postData['start_date']        = $appointments->getStartDate();
			$postData['end_date']          = $appointments->getEndDate();
			$postData['appointment_id']    = $appointments->getId();
			$postData['service_id']        = $appointments->getServiceId();
			$postData['service_title']     = $service->getTitle();
			$postData['staff_name']        = $staff->getFullName();
			$postData['staff_email']       = $staff->getEmail();
			$postData['customer_email']    = $customer->getEmail();
			$postData['customer_fullname'] = $customer->getFullName();
			$postData['timezone_offset']   = $customerAppointments->getTimeZoneOffset();

			if ( ! empty( $room ) && ! empty( $details ) ) {
				daily_co_create_meeting( $postData, 'update', false, true );
			} else {
				daily_co_create_meeting( $postData, 'create', false, true );
			}
		}
	}
}