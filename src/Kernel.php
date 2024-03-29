<?php

namespace Headroom\Dailyco;

use Headroom\Dailyco\Bookly\Appointments\AppointmentsAjax;
use Headroom\Dailyco\BooklyCustomerCabinet\Appointments\CancelAppointments;
use Headroom\Dailyco\BooklyCustomerCabinet\Appointments\RescheduleAppointmentsAjax;
use Headroom\Dailyco\DailyIntegration\InvoiceGenerator;
use Headroom\Dailyco\Shortcodes\CompletedMeetingList;
use Headroom\Dailyco\Shortcodes\UltimateMember;
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
		if ( is_bookly_active() ) {
			//WooCommerce
			Woo::instance();
			InvoiceGenerator::instance();

			//Shortcodes
			UpcomingMeetingList::instance();
			UltimateMember::instance();
			CompletedMeetingList::instance();

			//Customer Cabinets
			if ( is_bookly_customer_cabinet_active() ) {
				RescheduleAppointmentsAjax::instance();
				CancelAppointments::instance();
			}

			//Bookly Ajax Interceptor
			if ( is_admin() || is_super_admin() ) {
				AppointmentsAjax::instance();
			}
		}
	}

}
