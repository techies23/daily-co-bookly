<?php

namespace Headroom\Dailyco;

use Headroom\Dailyco\Bookly\Appointments\AppointmentsAjax;
use Headroom\Dailyco\BooklyCustomerCabinet\Appointments\RescheduleAppointmentsAjax;
use Headroom\Dailyco\Shortcodes\UpcomingMeetingList;
use Headroom\Dailyco\WooCommerce\Woo;

class Kernel {

	private static $_instance = null;

	public static function instance(): ?Kernel {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	protected function __construct() {
		//@todo disable bookly plugin updates


		$this->init();
	}

	public function init() {
		//WooCommerce
		Woo::instance();

		//Shortcodes
		UpcomingMeetingList::instance();

		//Customer Cabinets
		RescheduleAppointmentsAjax::instance();

		//Bookly Ajax Interceptor
		if ( is_admin() || is_super_admin() ) {
			AppointmentsAjax::instance();
		}
	}

}
