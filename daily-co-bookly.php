<?php
/**
 * @link              http://www.deepenbajracharya.com.np
 * @since             1.0.0
 * @package           daily-co-bookly
 *
 * Plugin Name:       Integration of daily.co API
 * Plugin URI:        https://www.deepenbajracharya.com.np
 * Description:       Create, moderate rooms via daily.co API
 * Version:           1.0.2
 * Author:            Deepen Bajracharya
 * Author URI:        https://www.deepenbajracharya.com.np
 * Text Domain:       daily-co-bookly
 * Domain Path:       /lang
 **/

// No Permission
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'DPEN_DAILY_CO_URI_PATH', plugin_dir_url( __FILE__ ) );
define( 'DPEN_DAILY_CO_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DPEN_DAILY_CO_PLUGIN_VERSION', '1.0.12' );

// The main plugin class.
require_once dirname( __FILE__ ) . '/includes/class-daily-co-bookly-init.php';

add_action( 'plugins_loaded', array( 'Daily_Co_Bookly_Init', 'instance' ), 999999 );
register_activation_hook( __FILE__, 'dayily_co_plugin_activation' );
function dayily_co_plugin_activation() {
	if ( ! wp_next_scheduled( 'headroom_invoice_reminder' ) ) {
		wp_schedule_event( time(), 'weekly', 'headroom_invoice_reminder' );
	}
}

register_deactivation_hook( __FILE__, 'dayily_co_plugin_deactivation' );
function dayily_co_plugin_deactivation() {
	wp_clear_scheduled_hook( 'headroom_invoice_reminder' );
}