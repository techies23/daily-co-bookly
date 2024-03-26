<?php

namespace Headroom\Dailyco\Shortcodes;

use Bookly\Lib\Entities\Customer;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Staff;
use Bookly\Lib\Utils\Price;
use Headroom\Dailyco\Datastore\BooklyDatastore;

class CompletedMeetingList {

	protected int $current_user_id;

	protected function __construct() {
		add_shortcode( 'daily_co_bookly_customer_completed_meetings', array( $this, 'customerCompleted' ) );
		add_shortcode( 'daily_co_bookly_therapist_completed_meetings', array( $this, 'therapistCompleted' ) );
	}

	public function therapistCompleted() {
		ob_start();

		wp_enqueue_script( 'custom-script-public' );

		if ( ! is_user_logged_in() ) {
			echo "<p>You need to be logged in to view this page.</p>";

			return;
		}

		$current_user_id = get_current_user_id();
		$staff_detail    = BooklyDatastore::getStaffbyUserID( $current_user_id );
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
				$appointments = BooklyDatastore::get_appointments_by_staff( $staff->getId(), '<=', 'DESC' );
				if ( ! empty( $appointments ) ) {
					foreach ( $appointments as $appointment ) {
						$room_details = get_user_meta( $current_user_id, '_daily_co_room_details_' . $appointment['id'], true );
						$customApt    = CustomerAppointment::find( $appointment['ca_id'] );
						$customer     = Customer::find( absint( $appointment['customer_id'] ) );
						$staff        = Staff::find( $appointment['staff_id'] );
						$extras       = ! empty( $customApt->getExtras() ) ? $customApt->getExtras() : false;
						$ict_codes    = false;
						if ( ! empty( $extras ) ) {
							$ict_codes = json_decode( $extras );
						}
						?>
						<tr>
							<td><?php echo $appointment['service_title']; ?></td>
							<td><?php echo date( 'd/m/Y h:i a', strtotime( $appointment['start_date'] ) ); ?></td>
							<td><?php echo $appointment['customer_full_name']; ?></td>
							<td>
								<?php
								if ( ! empty( $appointment['payment_total'] ) ) {
									echo Price::format( $appointment['payment_total'] );
								} elseif ( ! empty( $ict_codes ) && ! empty( $ict_codes[0]->manual_price ) ) {
									echo Price::format( $ict_codes[0]->manual_price );
								} else {
									?>
									<input type="number" name="manual_price" class="manual-price-field manual-price-field-<?php echo $appointment['id']; ?>" id="manual-price-field-<?php echo $appointment['id']; ?>" placeholder="Manually enter price if any">
									<?php
								}
								?>
							</td>
							<?php
							if ( ! empty( $extras ) ) {
								if ( ! empty( $ict_codes ) && ! empty( $ict_codes[0]->sent_invoice ) ) {
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

	public function customerCompleted() {
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
			$appointments    = BooklyDatastore::get_appointments_by_customer( $current_user_id, '<=', 'DESC' );
			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					$staff = Staff::find( $appointment['staff_id'] );
					if ( ! empty( $staff ) && $staff->getWpUserId() ) {
						$extras       = ! empty( $appointment['extras'] ) ? $appointment['extras'] : false;
						$ict_codes    = false;
						if ( ! empty( $extras ) ) {
							$ict_codes = json_decode( $extras );
						}
						?>
						<tr>
							<td><?php echo $appointment['service']; ?></td>
							<td><?php echo date( 'd/m/Y h:i a', strtotime( $appointment['start_date'] ) ); ?></td>
							<td><?php echo $appointment['staff']; ?></td>
							<td>
								<?php
								if ( ! empty( $appointment['price'] ) ) {
									echo Price::format( $appointment['price'] );
								} elseif ( ! empty( $ict_codes ) && ! empty( $ict_codes[0]->manual_price ) ) {
									echo Price::format( $ict_codes[0]->manual_price );
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

	public static function instance(): ?CompletedMeetingList {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}