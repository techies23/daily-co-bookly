<?php

namespace Headroom\Dailyco\Shortcodes;

class UltimateMember {

	public function __construct() {
		add_shortcode( 'daily_co_bookly_member_booking', array( $this, 'book_member' ) );
	}

	public function book_member() {
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

	private static $_instance = null;

	public static function instance(): ?UltimateMember {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}