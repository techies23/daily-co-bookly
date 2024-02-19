<?php

namespace Headroom\Dailyco;

use Headroom\Dailyco\Bookly\Appointments\Ajax;
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

		//Bookly
		Ajax::instance();
	}

}
