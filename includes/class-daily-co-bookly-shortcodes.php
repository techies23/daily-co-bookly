<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

use Bookly\Backend\Modules\Settings\Page as SettingsPage;
use Bookly\Lib\Entities\Staff;
use Bookly\Lib\Utils\Common;
use BooklyPro\Lib\Config;

/**
 * Class for Shortcodes
 */
class Daily_Co_Bookly_Shortcodes {

	public function __construct() {
		add_shortcode( 'daily_co_bookly_therapist_list', array( $this, 'show_meeting_therapist' ) );
		add_shortcode( 'daily_co_bookly_customer_list', array( $this, 'show_meeting_customer' ) );
		add_shortcode( 'daily_co_bookly_customer_completed_meetings', array( $this, 'show_meeting_customer_completed' ) );
		add_shortcode( 'daily_co_bookly_therapist_completed_meetings', array( $this, 'show_meeting_therapist_completed' ) );
		add_shortcode( 'daily_co_bookly_staffs', array( $this, 'get_staff_list' ) );
		add_shortcode( 'daily_co_bookly_show_network_stats', array( $this, 'network_stats' ) );
		#add_shortcode( 'daily_co_bookly_sync_cal', array( $this, 'sync_calendar' ) );
	}

	public function show_meeting_therapist() {
		ob_start();

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return;
		}

		$current_user_id = get_current_user_id();
		$staff_detail    = Daily_Co_Bookly_Datastore::getStaffbyUserID( $current_user_id );
		$staff           = Staff::find( $staff_detail['id'] );
		if ( ! empty( $staff ) && (int) $staff->getWpUserId() === $current_user_id ) {
			?>
            <h3>Upcoming Professional Meeting List</h3>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th class="manage-column"><?php esc_html_e( 'Service', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Date', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Client', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Action', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Status', 'daily-co-bookly' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$appointments = Daily_Co_Bookly_Datastore::get_appointments_by_staff( $staff->getId(), '>=', 'ASC', true );
				if ( ! empty( $appointments ) ) {
					foreach ( $appointments as $appointment ) {
						$ca           = \Bookly\Lib\Entities\CustomerAppointment::find( $appointment['ca_id'] );
						$start_time   = \Bookly\Lib\Utils\DateTime::applyTimeZoneOffset( $appointment['start_date'], $ca->getTimeZoneOffset() );
						$room_details = get_user_meta( $current_user_id, '_daily_co_room_details_' . $appointment['id'], true );
						?>
                        <tr>
                            <td><?php echo $appointment['service_title']; ?></td>
                            <td><?php echo date( 'F d, Y h:i a', strtotime( $appointment['start_date'] ) ); ?> (UTC+2)</td>
                            <td><?php echo $appointment['customer_full_name']; ?></td>
                            <td>
								<?php if ( ! empty( $room_details ) ) { ?>
                                    <a class="btn btn-bookly-daily" target="_blank" href="<?php echo home_url( '/room/start/?s=' ) . $room_details->name . '&id=' . $current_user_id; ?>">Join</a>
								<?php } else {
									echo "N/A";
								} ?>
                            </td>
                            <td><?php echo $appointment['status']; ?></td>
                        </tr>
						<?php
					}
				} else {
					?>
                    <tr>
                        <td colspan="5">You don't have any upcoming meetings.</td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
			<?php
		} else {
			echo "<p>Sorry! I could not load your data at the moment, are you a professional ?.</p>";
		}

		return ob_get_clean();
	}

	public function show_meeting_customer() {
		ob_start();

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return;
		}
		?>
        <h3>Upcoming Client Meeting List</h3>
        <table class="widefat fixed striped">
            <thead>
            <tr>
                <th class="manage-column"><?php esc_html_e( 'Service', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Date', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Professional', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Action', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Status', 'daily-co-bookly' ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			$current_user_id = get_current_user_id();
			$appointments    = Daily_Co_Bookly_Datastore::get_appointments_by_customer( $current_user_id, '>=', 'ASC', true );
			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					$staff = Staff::find( $appointment['staff_id'] );
					if ( ! empty( $staff ) && $staff->getWpUserId() ) {
						$room_details = get_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment['appointment_id'], true );
						?>
                        <tr>
                            <td><?php echo $appointment['service']; ?></td>
                            <td><?php echo date( 'F d, Y h:i a', strtotime( $appointment['start_date'] ) ); ?> (UTC+2)</td>
                            <td><?php echo $appointment['staff']; ?></td>
                            <td>
								<?php if ( ! empty( $room_details ) && empty( $room_details->error ) ) { ?>
                                    <a class="btn btn-bookly-daily" target="_blank" href="<?php echo home_url( '/room/join/?j=' ) . $room_details->name; ?>">Join</a>
								<?php } else {
									echo "N/A";
								} ?>
                            </td>
                            <td><?php echo $appointment['appointment_status']; ?></td>
                        </tr>
						<?php
					}
				}
			} else {
				?>
                <tr>
                    <td colspan="5">You do not have any upcoming sessions.
                    </td>
                </tr>
				<?php
			}
			?>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get all staff list and show them
	 */
	public function get_staff_list( $atts ) {
		ob_start();

		$types = array();
		if ( ! empty( $atts ) && ! empty( $atts['show'] ) ) {
			$types = explode( ', ', $atts['show'] );
		}

		if ( isset( $_GET['book_staff'] ) ) {
			$staff_id = (int) $_GET['book_staff'];
			$staff    = Staff::find( $staff_id );
			if ( $staff ) {
				echo do_shortcode( '[bookly-form staff_member_id="' . $staff_id . '" hide="staff_members"]' );
			} else {
				echo "<p>We did not find any staff related here.</p>";
			}
		} else {
			$staffs = Daily_Co_Bookly_Datastore::getStaffList();
			if ( ! empty( $staffs ) ) {
				$count = 1;
				?>
                <div class="dailyco-staff-container">
                    <div class="dailyco-staff-row">
						<?php
						foreach ( $staffs as $staff ) {
							$services = Daily_Co_Bookly_Datastore::getStaffServicesPrice( $staff['id'], true );
							$img      = wp_get_attachment_image( $staff['attachment_id'] );
							?>
                            <div class="dailyco-staff-column staff-<?php echo $staff['id']; ?>">
                                <div class="dailyco-staff-img"><?php echo $img; ?></div>
								<?php if ( ! empty( $types ) && in_array( 'name', $types ) ) { ?>
                                    <h3><?php echo $staff['full_name']; ?></h3>
								<?php } ?>
								<?php if ( ! empty( $types ) && in_array( 'info', $types ) ) { ?>
                                    <p><?php echo $staff['info']; ?></p>
								<?php } ?>
								<?php
								if ( ! empty( $services ) && ! empty( $types ) && in_array( 'price', $types ) ) {
									if ( count( $services ) <= 1 ) {
										?>
                                        <p><?php echo \Bookly\Lib\Utils\Price::format( $services[0] ); ?></p>
										<?php
									} else {
										sort( $services );
										?>
                                        <p><?php echo \Bookly\Lib\Utils\Price::format( $services[0] ) . ' - ' . \Bookly\Lib\Utils\Price::format( end( $services ) ); ?></p>
										<?php
									}
								}
								?>
                                <a class="dailyco-btn" rel="nofollow" href="<?php echo add_query_arg( array( 'book_staff' => $staff['id'] ) ) ?>">Book Appointment</a>
                            </div>
							<?php
							if ( $count % 3 === 0 ) {
								echo '</div><div class="dailyco-staff-row">';
							}
							$count ++;
						}
						?>
                    </div>
                </div>
				<?php
			}
		}

		return ob_get_clean();
	}

	public function network_stats() {
		ob_start();

		wp_enqueue_script( 'daily-api-script' );
		wp_enqueue_script( 'daily-api-network-statistics' );
		wp_localize_script( 'daily-api-network-statistics', 'dailyco', array(
			'domain_uri' => get_option( '_dpen_daily_domain' )
		) );
		?>
        <div id="page-blocks">
            <div id="meeting-info-row">
                <div id="meeting-room-info" class="info">
                    room info
                </div>
                <div id="network-info" class="info">
                    network stats
                </div>
            </div>

            <div id="buttons-row">
                <div>
                    <button id="leave-meeting" onclick="callFrame.leave()">
                        leave meeting
                    </button>
                </div>
            </div>

            <div id="call-frame-container" style="height:400px;"></div>
        </div>
		<?php

		return ob_get_clean();
	}

	public function show_meeting_customer_completed() {
		ob_start();

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return;
		}

		wp_enqueue_script( 'custom-script-public' );
		?>
        <h3>Completed Client Meeting List</h3>
        <table class="widefat fixed striped">
            <thead>
            <tr>
                <th class="manage-column"><?php esc_html_e( 'Service', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Date', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Professional', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Price', 'daily-co-bookly' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Invoice', 'daily-co-bookly' ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			$current_user_id = get_current_user_id();
			$appointments    = Daily_Co_Bookly_Datastore::get_appointments_by_customer( $current_user_id, '<=', 'DESC' );
			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					$staff = Staff::find( $appointment['staff_id'] );
					if ( ! empty( $staff ) && $staff->getWpUserId() ) {
						$room_details = get_user_meta( $staff->getWpUserId(), '_daily_co_room_details_' . $appointment['appointment_id'], true );
						$extras = !empty( $appointment['extras'] ) ? $appointment['extras'] : false;
						$ict_codes = false;
						if ( ! empty( $extras ) ) {
						    $ict_codes = json_decode( $extras );
						}
						?>
                        <tr>
                            <td><?php echo $appointment['service']; ?></td>
                            <td><?php echo date( 'F d, Y h:i a', strtotime( $appointment['start_date'] ) ); ?></td>
                            <td><?php echo $appointment['staff']; ?></td>
                            <td>
                            	<?php
                        		if( !empty( $appointment['price'] ) ) {
                        			echo  \Bookly\Lib\Utils\Price::format( $appointment['price'] );
                        		} else if ( ! empty( $ict_codes ) && ! empty( $ict_codes[0]->manual_price ) ) {
									echo \Bookly\Lib\Utils\Price::format( $ict_codes[0]->manual_price );
								} else {
									echo "N/A";
								}
								?>
                            </td>
                            <td style="vertical-align:middle;">
								<?php
								if ( ! empty( $appointment['extras'] ) ) {
									$ict_codes = json_decode( $appointment['extras'] );
									if ( ! empty( $ict_codes[0]->sent_invoice ) ) {
										?>
                                        <a class="headroom-view-invoice-btn" href="<?php echo home_url( '/client-dashboard/?type=thappt&st=' . $staff->getWpUserId() . '&cs=' . $current_user_id . '&view=' . $appointment['appointment_id'] ); ?>" target="_blank">View Invoice</a>
										<?php
									} else {
										echo "N/A";
									}
								} else {
									echo "N/A";
								}
								?>
                            </td>
                        </tr>
						<?php
					}
				}
			} else {
				?>
                <tr>
                    <td colspan="4">You do not have any upcoming sessions.
                    </td>
                </tr>
				<?php
			}
			?>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}

	public function show_meeting_therapist_completed() {
		ob_start();

		wp_enqueue_script( 'custom-script-public' );

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return;
		}

		$current_user_id = get_current_user_id();
		$staff_detail    = Daily_Co_Bookly_Datastore::getStaffbyUserID( $current_user_id );
		$staff           = Staff::find( $staff_detail['id'] );
		if ( ! empty( $staff ) && (int) $staff->getWpUserId() === $current_user_id ) {
			?>
            <h3>Completed Professional Meeting List</h3>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th class="manage-column"><?php esc_html_e( 'Service', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Date', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Client', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Price', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'ICD Code', 'daily-co-bookly' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Invoice', 'daily-co-bookly' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$appointments = Daily_Co_Bookly_Datastore::get_appointments_by_staff( $staff->getId(), '<=', 'DESC' );
				if ( ! empty( $appointments ) ) {
					foreach ( $appointments as $appointment ) {
						$room_details = get_user_meta( $current_user_id, '_daily_co_room_details_' . $appointment['id'], true );
						$customApt = \Bookly\Lib\Entities\CustomerAppointment::find( $appointment['ca_id'] );
						$customer  = \Bookly\Lib\Entities\Customer::find( absint( $appointment['customer_id'] ) );
						$staff     = Staff::find( $appointment['staff_id'] );
						$extras    = !empty( $customApt->getExtras() ) ? $customApt->getExtras() : false;
						$ict_codes = false;
						if ( ! empty( $extras ) ) {
						    $ict_codes = json_decode( $extras );
						}
						?>
                        <tr>
                            <td><?php echo $appointment['service_title']; ?></td>
                            <td><?php echo date( 'F d, Y h:i a', strtotime( $appointment['start_date'] ) ); ?></td>
                            <td><?php echo $appointment['customer_full_name']; ?></td>
                            <td>
                        		<?php
                        		if( !empty($appointment['payment_total']) ) {
                        			echo  \Bookly\Lib\Utils\Price::format( $appointment['payment_total'] );
                        		} else if ( ! empty( $ict_codes ) && ! empty( $ict_codes[0]->manual_price ) ) {
									echo \Bookly\Lib\Utils\Price::format( $ict_codes[0]->manual_price );
								} else {
									?>
									<input type="number" name="manual_price" class="manual-price-field manual-price-field-<?php echo $appointment['id']; ?>" id="manual-price-field-<?php echo $appointment['id']; ?>" placeholder="Manually enter price if any">
									<?php
								}
								?>
                            </td>
							<?php
							if ( ! empty( $extras ) ) {
								if ( !empty($ict_codes) && ! empty( $ict_codes[0]->sent_invoice ) ) {
									?>
                                    <td>
                                        ICD Code: <?php echo ! empty( $ict_codes[0]->ict_code ) ? $ict_codes[0]->ict_code : 'N/A'; ?>
                                        <br>
                                        Tariff Code: <?php echo ! empty( $ict_codes[0]->tariff_code ) ? $ict_codes[0]->tariff_code : 'N/A'; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <a class="headroom-view-invoice-btn" href="<?php echo home_url( '/therapist_dashboard?type=thappt&st=' . $staff->getWpUserId() . '&cs=' . $customer->getWpUserId() . '&view=' . $appointment['id'] ); ?>" target="_blank">View Invoice</a>
                                    </td>
									<?php
								} else {
									?>
                                    <td>
                                        <input type="text" name="icd_code" class="icd-code-input icd-code-input-<?php echo $appointment['id']; ?>" id="icd-code-input-<?php echo $appointment['id']; ?>" placeholder="Insert ICD code here or type 'n/a'"><br>
                                        <input type="text" name="tariff_code" class="tarfiff-code-input tarfiff-code-input-<?php echo $appointment['id']; ?>" id="tarfiff-code-input-<?php echo $appointment['id']; ?>" placeholder="Insert Tariff code here or type 'n/a'">
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <a href="javascript:void(0);" data-paymentid="<?php echo $appointment['payment_id']; ?>" data-caid="<?php echo $appointment['ca_id']; ?>" data-aptid="<?php echo $appointment['id']; ?>" class="headroom-send-invoice-btn" id="headroom-send-invoice-btn">Send Invoice</a>
                                    </td>
									<?php
								}
							} else { ?>
                            <td>
                                <input type="text" name="icd_code" class="icd-code-input icd-code-input-<?php echo $appointment['id']; ?>" id="icd-code-input-<?php echo $appointment['id']; ?>" placeholder="Insert ICD code here or type 'n/a'"><br>
                                <input type="text" name="tariff_code" class="tarfiff-code-input tarfiff-code-input-<?php echo $appointment['id']; ?>" id="tarfiff-code-input-<?php echo $appointment['id']; ?>" placeholder="Insert Tariff code here or type 'n/a'">
                            </td>
                            <td style="vertical-align:middle;">
                                <a href="javascript:void(0);" data-paymentid="<?php echo $appointment['payment_id']; ?>" data-caid="<?php echo $appointment['ca_id']; ?>" data-aptid="<?php echo $appointment['id']; ?>" class="headroom-send-invoice-btn" id="headroom-send-invoice-btn">Send Invoice</a>
                            <td>
								<?php
								}
								?>
                        </tr>
						<?php
					}
				} else {
					?>
                    <tr>
                        <td colspan="6">You don't have any completed meetings.</td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
			<?php
		} else {
			echo "<p>Sorry! I could not load your data at the moment, are you a professional ?.</p>";
		}

		return ob_get_clean();
	}

	public function sync_calendar() {
		$staff = Staff::find( 30 );
		if ( $gc_errors = \Bookly\Lib\Session::get( 'staff_google_auth_error' ) ) {
			foreach ( (array) json_decode( $gc_errors, true ) as $error ) {
				$data['alert']['error'][] = $error;
			}
			\Bookly\Lib\Session::destroy( 'staff_google_auth_error' );
		}

		$auth_url           = null;
		$google_calendars   = array();
		$google_calendar_id = null;
		if ( class_exists( '\BooklyPro\Lib\Google\Client' ) ) {
			if ( ! empty( $staff ) && $staff->getGoogleData() == '' ) {
				if ( Config::getGoogleCalendarSyncMode() !== null ) {
					$google   = new \BooklyPro\Lib\Google\Client();
					$auth_url = $google->createAuthUrl( $staff->getId() );
				} else {
					$auth_url = false;
				}
			} else {
				$google = new \BooklyPro\Lib\Google\Client();
				if ( $google->auth( $staff, true ) && ( $list = $google->getCalendarList() ) !== false ) {
					$google_calendars   = $list;
					$google_calendar_id = $google->data()->calendar->id;
				} else {
					foreach ( $google->getErrors() as $error ) {
						$data['alert']['error'][] = $error;
					}
				}
			}
		}

		ob_start();
		?>
        <div class="form-group bookly-js-google-calendar-row">
            <label><?php esc_html_e( 'Google Calendar integration', 'bookly' ) ?></label>
            <div>
				<?php if ( isset ( $auth_url ) ) : ?><?php if ( $auth_url ) : ?>
                    <a style="color:#000;" href="<?php echo esc_url( $auth_url ) ?>"><?php esc_html_e( 'Connect', 'bookly' ) ?></a>
				<?php else : ?><?php printf( __( 'Please configure Google Calendar <a href="%s">settings</a> first', 'bookly' ), Common::escAdminUrl( SettingsPage::pageSlug(), array( 'tab' => 'google_calendar' ) ) ) ?><?php endif ?><?php else : ?><?php esc_html_e( 'Connected', 'bookly' ) ?> (
                    <span class="custom-control custom-checkbox d-inline-block">
                <input class="custom-control-input" id="google_disconnect" type="checkbox" name="google_disconnect">
                <label class="custom-control-label" for="google_disconnect"><?php esc_html_e( 'disconnect', 'bookly' ) ?></label>
            </span>)
				<?php endif ?>
            </div>
        </div>
		<?php

		return ob_get_clean();
	}
}

new Daily_Co_Bookly_Shortcodes();