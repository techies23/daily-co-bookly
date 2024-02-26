<?php

namespace Headroom\Dailyco\BooklyCustomerCabinet\Appointments;

use Bookly\Lib\Base\Ajax as BooklyAjax;

class CancelAppointmentAjax extends BooklyAjax {

	protected static function permissions(): array {
		return array( '_default' => array( 'staff', 'supervisor' ) );
	}

	protected function __construct() {

	}

	private static $_instance = null;

	public static function instance(): ?AppointmentsAjax {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
