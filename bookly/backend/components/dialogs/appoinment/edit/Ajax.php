<?php

use Bookly\Backend\Modules\Calendar;
use Bookly\Lib;
use Bookly\Lib\DataHolders\Booking as DataHolders;
use Bookly\Lib\Entities\Appointment;
use Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Service;
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Utils\DateTime;

#remove_all_actions( 'wp_ajax_nopriv_bookly_save_appointment_form' );
#remove_all_actions( 'wp_ajax_bookly_save_appointment_form' );
#add_action( 'wp_ajax_nopriv_bookly_save_appointment_form', array( 'DailyCo_Bookly_Appointment_Edit', 'saveAppointmentForm' ) );
#add_action( 'wp_ajax_bookly_save_appointment_form', array( 'DailyCo_Bookly_Appointment_Edit', 'saveAppointmentForm' ) );

class DailyCo_Bookly_Appointment_Edit extends Lib\Base\Ajax {

	/**
	 * Save appointment form (for both create and edit).
	 */
	public static function saveAppointmentForm() {
		$response = array( 'success' => false );

		$appointment_id       = (int) self::parameter( 'id', 0 );
		$staff_id             = (int) self::parameter( 'staff_id', 0 );
		$service_id           = (int) self::parameter( 'service_id', -1 );
		$custom_service_name  = trim( self::parameter( 'custom_service_name' ) );
		$custom_service_price = trim( self::parameter( 'custom_service_price' ) );
		$location_id          = (int) self::parameter( 'location_id', 0 );
		$skip_date            = self::parameter( 'skip_date', 0 );
		$start_date           = self::parameter( 'start_date' );
		$end_date             = self::parameter( 'end_date' );
		$repeat               = json_decode( self::parameter( 'repeat', '[]' ), true );
		$schedule             = self::parameter( 'schedule', array() );
		$reschedule_type      = self::parameter( 'reschedule_type', 'current' );
		$customers            = json_decode( self::parameter( 'customers', '[]' ), true );
		$notification         = self::parameter( 'notification', false );
		$internal_note        = self::parameter( 'internal_note' );
		$created_from         = self::parameter( 'created_from' );

		if ( ! $service_id ) {
			// Custom service.
			$service_id = null;
		}
		if ( $service_id || $custom_service_name == '' ) {
			$custom_service_name = null;
		}
		if ( $service_id || $custom_service_price == '' ) {
			$custom_service_price = null;
		}
		if ( ! $location_id ) {
			$location_id = null;
		}

		// Check for errors.
		if ( ! $skip_date ) {
			if ( ! $start_date ) {
				$response['errors']['time_interval'] = __( 'Start time must not be empty', 'bookly' );
			} elseif ( ! $end_date ) {
				$response['errors']['time_interval'] = __( 'End time must not be empty', 'bookly' );
			} elseif ( $start_date == $end_date ) {
				$response['errors']['time_interval'] = __( 'End time must not be equal to start time', 'bookly' );
			}
		}

		if ( $service_id == -1 ) {
			$response['errors']['service_required'] = true;
		} else if ( $service_id === null && $custom_service_name === null ) {
			$response['errors']['custom_service_name_required'] = true;
		}
		$total_number_of_persons = 0;
		$max_extras_duration = 0;
		foreach ( $customers as $i => $customer ) {
			if ( in_array( $customer['status'], Lib\Proxy\CustomStatuses::prepareBusyStatuses( array(
				CustomerAppointment::STATUS_PENDING,
				CustomerAppointment::STATUS_APPROVED
			) ) ) ) {
				$total_number_of_persons += $customer['number_of_persons'];
				if ( $customer['extras_consider_duration'] ) {
					$extras_duration = Lib\Proxy\ServiceExtras::getTotalDuration( $customer['extras'] );
					if ( $extras_duration > $max_extras_duration ) {
						$max_extras_duration = $extras_duration;
					}
				}
			}
			$customers[ $i ]['created_from'] = ( $created_from == 'backend' ) ? 'backend' : 'frontend';
		}
		if ( $service_id ) {
			$staff_service = new Lib\Entities\StaffService();
			$staff_service->loadBy( array(
				'staff_id'    => $staff_id,
				'service_id'  => $service_id,
				'location_id' => $location_id ?: null,
			) );
			if ( ! $staff_service->isLoaded() ) {
				$staff_service->loadBy( array(
					'staff_id'    => $staff_id,
					'service_id'  => $service_id,
					'location_id' => null,
				) );
			}
			if ( $total_number_of_persons > $staff_service->getCapacityMax() ) {
				$response['errors']['overflow_capacity'] = sprintf(
					__( 'The number of customers should not be more than %d', 'bookly' ),
					$staff_service->getCapacityMax()
				);
			}
		}

		// If no errors then try to save the appointment.
		if ( ! isset ( $response['errors'] ) ) {
			// Determine display time zone,
			// and shift the dates to WP time zone if needed
			$display_tz = Common::getCurrentUserTimeZone();
			$wp_tz = Lib\Config::getWPTimeZone();
			if ( $display_tz !== $wp_tz ) {
				$start_date = DateTime::convertTimeZone( $start_date, $display_tz, $wp_tz );
				$end_date   = DateTime::convertTimeZone( $end_date, $display_tz, $wp_tz );
			}

			$duration = Lib\Slots\DatePoint::fromStr( $end_date )->diff( Lib\Slots\DatePoint::fromStr( $start_date ) );
			if ( ! $skip_date && $repeat['enabled'] ) {
				$queue = array();
				// Series.
				if ( ! empty ( $schedule ) ) {
					/** @var DataHolders\Order[] $orders */
					$orders = array();

					if ( $service_id ) {
						$service = Service::find( $service_id );
					} else {
						$service = new Service();
						$service
							->setTitle( $custom_service_name )
							->setDuration( $duration )
							->setPrice( $custom_service_price );
					}

					foreach ( $customers as $customer ) {
						// Create new series.
						$series = new Lib\Entities\Series();
						$series
							->setRepeat( self::parameter( 'repeat' ) )
							->setToken( Common::generateToken( get_class( $series ), 'token' ) )
							->save();

						// Create order
						if ( $notification ) {
							$orders[ $customer['id'] ] = DataHolders\Order::create( Customer::find( $customer['id'] ) )
							                                              ->addItem( 0, DataHolders\Series::create( $series ) );
						}

						foreach ( $schedule as $i => $slot ) {
							$slot       = json_decode( $slot, true );
							$start_date = $slot[0][2];
							$end_date   = Lib\Slots\DatePoint::fromStr( $start_date )->modify( $duration )->format( 'Y-m-d H:i:s' );
							// Try to find existing appointment
							/** @var Appointment $appointment */
							$appointment = Appointment::query( 'a' )
							                          ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
							                          ->where( 'a.staff_id', $staff_id )
							                          ->where( 'a.service_id', $service_id )
							                          ->whereNotIn( 'ca.status', Lib\Proxy\CustomStatuses::prepareFreeStatuses( array(
								                          CustomerAppointment::STATUS_CANCELLED,
								                          CustomerAppointment::STATUS_REJECTED
							                          ) ) )
							                          ->where( 'start_date', $start_date )
							                          ->findOne();

							$ca_customers = array();
							if ( $appointment ) {
								foreach ( $appointment->getCustomerAppointments( true ) as $ca ) {
									$ca_customer                  = $ca->getFields();
									$ca_customer['ca_id']         = $ca->getId();
									$ca_customer['extras']        = json_decode( $ca_customer['extras'], true );
									$ca_customer['custom_fields'] = json_decode( $ca_customer['custom_fields'], true );
									$ca_customers[]               = $ca_customer;
								}
							} else {
								// Create appointment.
								$appointment = new Appointment();
								$appointment
									->setLocationId( $location_id )
									->setStaffId( $staff_id )
									->setServiceId( $service_id )
									->setCustomServiceName( $custom_service_name )
									->setCustomServicePrice( $custom_service_price )
									->setStartDate( $start_date )
									->setEndDate( $end_date )
									->setInternalNote( $internal_note )
									->setExtrasDuration( $max_extras_duration )
									->save();
								Lib\Utils\Log::createEntity( $appointment, __METHOD__ );
							}

							if ( $appointment->getId() ) {
								// Online meeting
								Lib\Proxy\Shared::syncOnlineMeeting( array(), $appointment, $service );
								// Save customer appointments.
								$ca_list = $appointment->saveCustomerAppointments( array_merge( $ca_customers, array( $customer ) ), $series->getId() );
								// Google Calendar.
								Lib\Proxy\Pro::syncGoogleCalendarEvent( $appointment );
								// Outlook Calendar.
								Lib\Proxy\OutlookCalendar::syncEvent( $appointment );

								if ( $notification ) {
									// Waiting list.
									Lib\Proxy\WaitingList::handleParticipantsChange( $queue, $appointment );
									foreach ( $ca_list as $ca ) {
										$item = DataHolders\Simple::create( $ca )
										                          ->setService( $service )
										                          ->setAppointment( $appointment );
										$orders[ $ca->getCustomerId() ]->getItem( 0 )->addItem( $i, $item );
									}
								}
							}
						}
						if ( $customer['payment_create'] === true ) {
							Proxy\RecurringAppointments::createBackendPayment( $series, $customer );
						}
					}
					if ( $notification ) {
						foreach ( $orders as $order ) {
							Lib\Notifications\Booking\Sender::sendForOrder( $order, array(), $notification == 'all', $queue );
						}
					}
				}
				$response['success'] = true;
				$response['queue']   = array( 'all' => $queue, 'changed_status' => array() );
				$response['data']    = array( 'resourceId' => $staff_id );  // make EventCalendar refetch events
			} else {
				// Single appointment.
				$appointment = new Appointment();
				if ( $appointment_id ) {
					// Edit.
					$appointment->load( $appointment_id );
					if ( $appointment->getStaffId() != $staff_id ) {
						$appointment->setStaffAny( 0 );
					}
					if ( $reschedule_type != 'current' ) {
						$start_date_timestamp  = strtotime( $start_date );
						$days_offset           = floor( $start_date_timestamp / DAY_IN_SECONDS ) - floor( strtotime( $appointment->getStartDate() ) / DAY_IN_SECONDS );
						$reschedule_start_time = $start_date_timestamp % DAY_IN_SECONDS;
						$current_start_date    = $appointment->getStartDate();
					}
				}
				$appointment
					->setLocationId( $location_id )
					->setStaffId( $staff_id )
					->setServiceId( $service_id )
					->setCustomServiceName( $custom_service_name )
					->setCustomServicePrice( $custom_service_price )
					->setStartDate( $skip_date ? null : $start_date )
					->setEndDate( $skip_date ? null : $end_date )
					->setInternalNote( $internal_note )
					->setExtrasDuration( $max_extras_duration );

				if ( $appointment_id ) {
					Lib\Utils\Log::updateEntity( $appointment, __METHOD__ );
				}
				$modified = $appointment->getModified();
				if ( $appointment->save() !== false ) {

					$queue_changed_status = array();
					$queue = array();

					if ( ! $appointment_id ) {
						Lib\Utils\Log::createEntity( $appointment, __METHOD__ );
					}
					// Save customer appointments.
					$ca_status_changed = $appointment->saveCustomerAppointments( $customers );

					foreach ( $customers as $customer ) {
						if ( $customer['payment_create'] === true && $customer['series_id'] ) {
							Proxy\RecurringAppointments::createBackendPayment( Lib\Entities\Series::find( $customer['series_id'] ), $customer );
						}
						// Reschedule all recurring appointments for $days_offset days and set it's time to $reschedule_start_time
						$rescheduled_appointments = array( $appointment_id );
						if ( $appointment_id && $reschedule_type != 'current' && $customer['series_id'] ) {
							$query = Appointment::query( 'a' )
							                    ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
							                    ->where( 'ca.series_id', $customer['series_id'] )
							                    ->whereNotIn( 'a.id', $rescheduled_appointments );
							if ( $reschedule_type == 'next' ) {
								$query->whereGt( 'a.start_date', $current_start_date );
							}
							$reschedule_appointments = $query->find();
							/** @var Appointment $reschedule_appointment */
							foreach ( $reschedule_appointments as $reschedule_appointment ) {
								$start_timestamp     = strtotime( $reschedule_appointment->getStartDate() );
								$duration            = strtotime( $reschedule_appointment->getEndDate() ) - $start_timestamp;
								$new_start_timestamp = ( (int) ( $start_timestamp / DAY_IN_SECONDS ) + $days_offset ) * DAY_IN_SECONDS + $reschedule_start_time;
								$reschedule_appointment
									->setStartDate( date( 'Y-m-d H:i:s', $new_start_timestamp ) )
									->setEndDate( date( 'Y-m-d H:i:s', $new_start_timestamp + $duration ) );
								$reschedule_modified = $reschedule_appointment->getModified();

								Lib\Utils\Log::updateEntity( $reschedule_appointment, __METHOD__, 'Reschedule recurring appointment' );

								$reschedule_appointment->save();

								$rescheduled_appointments[] = $reschedule_appointment->getId();
								if ( $notification ) {
									foreach ( $reschedule_appointment->getCustomerAppointments( true ) as $ca ) {
										Lib\Notifications\Booking\Sender::sendForCA( $ca, $appointment, array(), true, $queue );
									}
								}

								self::_deleteSentReminders( $reschedule_appointment, $reschedule_modified );
							}
						}
					}

					// Online meeting.
					if ( $service_id ) {
						$service = Service::find( $service_id );
						$response['alert_errors'] = Lib\Proxy\Shared::syncOnlineMeeting( array(), $appointment, $service );
					}
					// Google Calendar.
					Lib\Proxy\Pro::syncGoogleCalendarEvent( $appointment );
					// Outlook Calendar.
					Lib\Proxy\OutlookCalendar::syncEvent( $appointment );

					// Send notifications.
					if ( $notification ) {
						// Waiting list.
						$queue = Lib\Proxy\WaitingList::handleParticipantsChange( $queue, $appointment );

						$ca_list = $appointment->getCustomerAppointments( true );
						foreach ( $ca_status_changed as $ca ) {
							if ( $appointment_id ) {
								Lib\Notifications\Booking\Sender::sendForCA( $ca, $appointment, array(), false, $queue_changed_status );
							}
							Lib\Notifications\Booking\Sender::sendForCA( $ca, $appointment, array(), true, $queue );
							unset( $ca_list[ $ca->getId() ] );
						}
						foreach ( $ca_list as $ca ) {
							Lib\Notifications\Booking\Sender::sendForCA( $ca, $appointment, array(), true, $queue );
						}
					}

					$response['success'] = true;
					$response['data']    = self::_getAppointmentForCalendar( $appointment->getId(), $staff_id, $display_tz );
					$response['queue']   = array( 'all' => $queue, 'changed_status' => $queue_changed_status );

					self::_deleteSentReminders( $appointment, $modified );
				} else {
					$response['errors'] = array( 'db' => __( 'Could not save appointment in database.', 'bookly' ) );
				}
			}
		}
		update_user_meta( get_current_user_id(), 'bookly_appointment_form_send_notifications', $notification );

		//Update Room as Well
		self::_updateRoomDetails( $staff_id, $appointment, $customer, $service_id );

		wp_send_json( $response );
	}

	/**
	 * Delete marks for sent reminders
	 *
	 * @param Appointment $appointment
	 * @param array $modified
	 */
	private static function _deleteSentReminders( Appointment $appointment, $modified )
	{
		// When changed start_date need resend the reminders
		if ( array_key_exists( 'start_date', $modified ) ) {
			$ca_ids = CustomerAppointment::query()
			                             ->select( 'id')
			                             ->where( 'appointment_id', $appointment->getId() )
			                             ->fetchCol( 'id' );
			if ( $ca_ids ) {
				Lib\Entities\SentNotification::query( 'sn' )
				                             ->delete( 'sn' )
				                             ->leftJoin( 'Notification', 'n', 'n.id = sn.notification_id' )
				                             ->whereIn( 'sn.ref_id', $ca_ids )
				                             ->whereIn( 'n.type', array( Lib\Entities\Notification::TYPE_APPOINTMENT_REMINDER, Lib\Entities\Notification::TYPE_LAST_CUSTOMER_APPOINTMENT ) )
				                             ->where( 'n.active', 1 )
				                             ->execute();
			}
		}
	}

	/**
	 * Get appointment for Event Calendar
	 *
	 * @param int $appointment_id
	 * @param int $staff_id
	 * @param string $display_tz
	 * @return array
	 */
	private static function _getAppointmentForCalendar( $appointment_id, $staff_id, $display_tz )
	{
		$query = Appointment::query( 'a' )
		                    ->where( 'a.id', $appointment_id );

		$appointments = Calendar\Page::buildAppointmentsForCalendar( $query, $staff_id, $display_tz );

		return $appointments[0];
	}

	/**
	 * Update Daily.co Room Details
	 *
	 * @param $staff_id
	 * @param $appointment
	 * @param $customer
	 * @param $service_id
	 *
	 * @throws Exception
	 */
	private static function _updateRoomDetails( $staff_id, $appointment, $customer, $service_id ) {
		$staff           = Lib\Entities\Staff::find( $staff_id );
		$service         = \Bookly\Lib\Entities\Service::find( $service_id );
		$customer_detail = Lib\Entities\Customer::find( $customer['id'] );

		if ( $staff->getWpUserId() ) {
			$room    = get_user_meta( $staff->getWpUserId(), '_daily_co_room_name_' . $appointment->getId(), true );
			$details = get_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment->getId(), true );

			$postData['wp_user_id']        = $staff->getWpUserId();
			$postData['start_date']        = $appointment->getStartDate();
			$postData['end_date']          = $appointment->getEndDate();
			$postData['appointment_id']    = $appointment->getId();
			$postData['service_id']        = $appointment->getServiceId();
			$postData['service_title']     = $service->getTitle();
			$postData['staff_name']        = $staff->getFullName();
			$postData['staff_email']       = $staff->getEmail();
			$postData['customer_email']    = $customer_detail->getEmail();
			$postData['customer_fullname'] = $customer_detail->getFullName();
			$postData['order_url']         = false;

			if ( ! empty( $room ) && ! empty( $details ) ) {
				daily_co_create_meeting( $postData, 'update', true );
			} else {
				daily_co_create_meeting( $postData, 'create', true );
			}
		}

	}

	/**
	 * Update Daily.co Room Details
	 *
	 * @param $old_staff_id
	 * @param $appointment_id
	 * @param $appointment Lib\Entities\Appointment
	 * @param $customer
	 * @param $service_id
	 *
	 * @throws Exception
	 */
	private static function _updateRoomDetailsssss( $old_staff_id, $appointment_id, $appointment, $customer, $service_id ) {
		if ( ! empty( $appointment ) ) {
			$staff           = Lib\Entities\Staff::find( $appointment->getStaffId() );
			$service         = \Bookly\Lib\Entities\Service::find( $service_id );
			$customer_detail = Lib\Entities\Customer::find( $customer['id'] );
			if ( $staff->getWpUserId() ) {
				$postData['wp_user_id']    = $staff->getWpUserId();
				$postData['start_date']    = $appointment->getStartDate();
				$postData['end_date']      = $appointment->getEndDate();
				$postData['service_title'] = $service->getTitle();
				$postData['staff_name']    = $staff->getFullName();
				$postData['staff_email']   = $staff->getEmail();

				$room    = get_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $appointment_id, true );
				$details = get_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $appointment_id, true );

				/*$status = ! empty( $customer['status'] ) ? $customer['status'] : false;
				if ( $status !== "approved" && empty( $room ) && empty( $details ) ) {
					return;
				}*/

				//If appointment is cancelled
				if ( ! empty( $customer ) && $customer['status'] === "cancelled" ) {
					$cancel_email_data = array(
						'start_time'     => $appointment->getStartDate(),
						'service_title'  => $service->getTitle(),
						'staff_name'     => $staff->getFullName(),
						'customer_email' => $customer_detail->getEmail(),
						'customer_name'  => $customer_detail->getFullName(),
						'staff_email'    => $staff->getEmail()
					);
					dpen_daily_co_cancel_meeting( $cancel_email_data );

					return;
				}

				$room_not_found = ! empty( $details ) && ! empty( $details->error ) ? true : false;
				if ( ! empty( $room ) && ! empty( $details ) && ! $room_not_found ) {
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

					$result = dailyco_api()->update_room( $submit, $room );
					if ( $old_staff_id !== $appointment->getStaffId() ) {
						//Delete Old staff data
						delete_user_meta( $old_staff_id, '_daily_co_room_details_' . $appointment_id );
					}

					update_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $appointment_id, $result );
					update_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $appointment_id, $result->name );

					//Clear Cache
					dpen_clear_room_cache();
				} else {
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
					update_user_meta( $postData['wp_user_id'], '_daily_co_room_details_' . $appointment_id, $result );
					update_user_meta( $postData['wp_user_id'], '_daily_co_room_name_' . $appointment_id, $result->name );

					//Reset Cache
					dpen_clear_room_cache();
				}

				//Send Email to customer
				$email_data = array(
					$postData['start_date'],
					$postData['service_title'],
					home_url( '/room/join/?j=' ) . $result->name,
					false,
					false,
					$postData['staff_name']
				);

				$description_invite = 'Hi there\n\n,Your session with ' . $postData['staff_name'] . ' has been re-scheduled. Please find below details to join your appointment on your booked time.\n\nDuration: ' . $postData['service_title'] . '\nStarting on: ' . $postData['start_date'] . '\n\nInstructions:\n\nBefore joining the meeting\, you will need to login to our system. This process is automated. If you have not logged in then you will be redirected to login page first.\n\nAfter logging in you can join your meetup.\n\n' . home_url( '/room/join/?j=' ) . $result->name;

				$customer_ics = array(
					'location'    => home_url( '/room/join/?j=' ) . $result->name,
					'description' => $description_invite,
					'dtstart'     => dpen_daily_co_convert_timezone( array(
						'date'      => $postData['start_date'],
						'timezone'  => 'UTC',
						'timestamp' => true
					), 'Y-m-d h:i a' ),
					'dtend'       => dpen_daily_co_convert_timezone( array(
						'date'      => $postData['end_date'],
						'timezone'  => 'UTC',
						'timestamp' => true
					), 'Y-m-d h:i a' ),
					'organizer'   => $postData['staff_name'],
					'summary'     => 'Online Consultation | Headroom'
				);

				dpen_daily_co_prepare_email( 'tpl-email-invite-updated', $email_data, "Online Consultation | Headroom", $customer_detail->getEmail(), $customer_ics );

				//Send email to Staff
				$email_data = array(
					$postData['start_date'],
					$postData['service_title'],
					home_url( '/room/start/?s=' ) . $result->name,
					$customer_detail->getFullName(),
					$customer_detail->getEmail(),
					false
				);

				dpen_daily_co_prepare_email( 'tpl-email-start-updated', $email_data, "Online Consultation | Headroom", $staff->getEmail() );
			}
		}
	}
}