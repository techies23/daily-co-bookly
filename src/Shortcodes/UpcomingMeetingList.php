<?php

namespace Headroom\Dailyco\Shortcodes;

use Bookly\Lib\Entities\Staff;
use Headroom\Dailyco\Datastore\Appointments;
use Headroom\Dailyco\Datastore\BooklyDatastore;

class UpcomingMeetingList {

	protected int $current_user_id;

	protected ?Appointments $booklyAppointments;

	protected function __construct() {
		$this->current_user_id    = get_current_user_id();
		$this->booklyAppointments = Appointments::instance();

		add_shortcode( 'daily_co_bookly_customer_list', [ $this, 'upcomingMeetingCustomer' ] );
		add_shortcode( 'daily_co_bookly_therapist_list', array( $this, 'upcomingMeetingTherapist' ) );
	}

	public function upcomingMeetingTherapist() {
		ob_start();

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return false;
		}

		$this->current_user_id = get_current_user_id();
		$staff_detail          = BooklyDatastore::getStaffbyUserID( $this->current_user_id );
		$staff                 = Staff::find( $staff_detail['id'] );
		if ( ! empty( $staff ) && (int) $staff->getWpUserId() === $this->current_user_id ) {
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
				$appointments = BooklyDatastore::get_appointments_by_staff( $staff->getId(), '>=', 'ASC', true );
				if ( ! empty( $appointments ) ) {
					foreach ( $appointments as $appointment ) {
						if ( $appointment['status'] !== "cancelled" ) {
							$appt = $this->booklyAppointments->getByUserAppointment( $this->current_user_id, $appointment['id'] );
							if ( ! empty( $appt->legacy ) ) {
								$room_details = $appt;
							} else {
								$room_details = ! empty( $appt->value ) ? json_decode( $appt->value ) : false;
							}
							?>
                            <tr>
                                <td><?php echo $appointment['service_title']; ?></td>
                                <td><?php echo date( 'd/m/Y h:i a', strtotime( $appointment['start_date'] ) ); ?> (UTC+2)</td>
                                <td><?php echo $appointment['customer_full_name']; ?></td>
                                <td>
									<?php if ( ! empty( $room_details ) ) { ?>
                                        <a class="btn btn-bookly-daily" target="_blank" href="<?php echo home_url( '/room/start/?s=' ) . $room_details->name . '&id=' . $this->current_user_id; ?>">Join</a>
									<?php } else {
										echo "N/A";
									} ?>
                                </td>
                                <td><?php echo $appointment['status']; ?></td>
                            </tr>
							<?php
						}
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

	public function upcomingMeetingCustomer() {
		ob_start();

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return false;
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
			$this->current_user_id = get_current_user_id();
			$appointments          = BooklyDatastore::get_appointments_by_customer( $this->current_user_id, '>=', 'ASC', true );
			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					if ( $appointment['appointment_status'] !== "cancelled" ) {
						$staff = Staff::find( $appointment['staff_id'] );
						if ( ! empty( $staff ) && $staff->getWpUserId() ) {
							$appt = $this->booklyAppointments->getByUserAppointment( $staff->getWpUserId(), $appointment['appointment_id'] );
							if ( ! empty( $appt->legacy ) ) {
								$room_details = $appt;
							} else {
								$room_details = ! empty( $appt->value ) ? json_decode( $appt->value ) : false;
							}
							?>
                            <tr class="appointment-<?php echo $appointment['appointment_id']; ?>">
                                <td><?php echo $appointment['service']; ?></td>
                                <td><?php echo date( 'd/m/Y h:i a', strtotime( $appointment['start_date'] ) ); ?> (UTC+2)</td>
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

	private static $_instance = null;

	public static function instance(): ?UpcomingMeetingList {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}