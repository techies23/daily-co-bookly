<?php
// No Permission
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Init Main Class File
 *
 * Class Daily_Co_Bookly_Init
 */
class Daily_Co_Bookly_Init {

	private static $_instance = null;

	public static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		$this->load_dependencies();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Loading all dependencies
	 */
	public function load_dependencies() {
		require DPEN_DAILY_CO_DIR_PATH . 'includes/helpers.php';

		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-client-consent.php';

		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-api.php';
		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-admin.php';
		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-fake-page.php';
		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-shortcodes.php';
//		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-shortcodes-um.php';
		require DPEN_DAILY_CO_DIR_PATH . 'includes/class-daily-co-bookly-api-helper.php';
		require DPEN_DAILY_CO_DIR_PATH . 'includes/hooks.php';

		if ( is_bookly_active() ) {
			require DPEN_DAILY_CO_DIR_PATH . 'bookly/frontend/modules/booking/Ajax.php';
			require DPEN_DAILY_CO_DIR_PATH . 'bookly/class-bookly-ajax-interceptor.php';
			require DPEN_DAILY_CO_DIR_PATH . 'bookly/datastore.php';
			require DPEN_DAILY_CO_DIR_PATH . 'bookly/ultimate-member-configs.php';
//			require DPEN_DAILY_CO_DIR_PATH . 'bookly/class-invoice-generator.php';
		}
	}

	public function enqueue_scripts() {
		wp_register_script( 'custom-script-public', DPEN_DAILY_CO_URI_PATH . 'assets/js/daily-bookly-public.js', array( 'jquery' ), time(), true );
		wp_localize_script( 'custom-script-public', 'daily', array(
			'ajax_uri' => admin_url( 'admin-ajax.php' ),
			'_nonce'   => wp_create_nonce( '_bookly_public_nonce' )
		) );
	}


}