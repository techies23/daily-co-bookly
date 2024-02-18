<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class for Shortcodes
 */
class Daily_Co_Bookly_Shortcodes_UltimateMember {

	public function __construct() {
		add_shortcode( 'daily_co_bookly_member_booking', array( $this, 'book_member' ) );
	}

	public function book_member( $atts ) {
		$profile_id = um_profile_id();
		ob_start();
		if ( ! empty( $profile_id ) ) {
			$staff_detail = Daily_Co_Bookly_Datastore::getStaffbyUserID( $profile_id );
			if ( ! empty( $staff_detail ) ) {
			    echo '<div class="bookly-therapist-booking-form-daily">';
				echo do_shortcode( '[bookly-form category_id="1" staff_member_id="' . $staff_detail['id'] . '" hide="categories,staff_members"]' );
				echo '</div>';
			} else {
			    echo "Therapist is not a Bookly Staff yet";
            }
		}

		return ob_get_clean();
	}

	/**
	 * Show booking form
	 *
	 * @param $atts
	 *
	 * @return false|string
	 */
	public function tttbook_member( $atts ) {
		$profile_id = um_profile_id();

		ob_start();
		if ( ! empty( $profile_id ) ) {
			$staff_detail = Daily_Co_Bookly_Datastore::getStaffbyUserID( $profile_id );
			if ( ! empty( $staff_detail ) ) {
				?>
                <button class="um-edit-profile-btn um-button um-alt btn-book-memeber-appointment" id="btn-book-memeber-appointment">
                    Book an Appointment
                </button>
                <div id="headroombook-member-appointment-modal" class="headroombook-member-appointment-modal">
                    <div class="headroombook-member-appointment-modal__content">
                        <span class="headroombook-member-appointment-modal__content--close">&times;</span>
						<?php echo do_shortcode( '[bookly-form category_id="1" staff_member_id="' . $staff_detail['id'] . '" hide="categories,staff_members"]' ); ?>
                    </div>
                </div>
                <script type="text/javascript">
                    jQuery(function ($) {
                        var modal = $('#headroombook-member-appointment-modal');
                        var btn = $('#btn-book-memeber-appointment');
                        var close = $('.headroombook-member-appointment-modal__content--close');
                        $(btn).on('click', function (e) {
                            e.preventDefault();
                            $(modal).show();
                        });

                        $(close).on('click', function (e) {
                            e.preventDefault();
                            $(modal).hide();
                        });
                    });
                </script>
				<?php
			}
		}

		return ob_get_clean();
	}
}

new Daily_Co_Bookly_Shortcodes_UltimateMember();