<?php

namespace Headroom\Dailyco\Datastore;

class Migrations {

	protected function __construct() {
	}

	public function migrate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'headroom_bookly_appointments';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE IF NOT EXISTS $table_name (
	        id bigint(20) NOT NULL AUTO_INCREMENT,
	        user_id bigint(20) NOT NULL,
    		name varchar(255) NOT NULL,
	        appointment_id bigint(20) NOT NULL,
	        value text NOT NULL,
	        PRIMARY KEY (id)
    	) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static $_instance = null;

	public static function instance(): ?Migrations {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}