<?php

namespace Headroom\Dailyco\Datastore;

abstract class DatabaseHandler {

	protected $wpdb;
	protected $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'headroom_bookly_appointments';
	}
}