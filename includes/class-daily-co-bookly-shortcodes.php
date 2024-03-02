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
		add_shortcode( 'daily_co_bookly_staffs', array( $this, 'get_staff_list' ) );
		add_shortcode( 'daily_co_bookly_show_network_stats', array( $this, 'network_stats' ) );
		#add_shortcode( 'daily_co_bookly_sync_cal', array( $this, 'sync_calendar' ) );
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