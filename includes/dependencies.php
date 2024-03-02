<?php

function is_bookly_active() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	return in_array( 'bookly-responsive-appointment-booking-tool/main.php', $active_plugins ) || array_key_exists( 'bookly-responsive-appointment-booking-tool/main.php', $active_plugins );
}

function is_bookly_customer_cabinet_active() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	return in_array( 'bookly-addon-customer-cabinet/main.php', $active_plugins ) || array_key_exists( 'bookly-addon-customer-cabinet/main.php', $active_plugins );
}
