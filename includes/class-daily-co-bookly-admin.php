<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Admin class for admin settings and admin processses
 *
 * Class Daily_Co_Bookly_Admin
 */
class Daily_Co_Bookly_Admin {

	public static $message = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_delete_room', array( $this, 'delete_room' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( $hook === "daily-api_page_daily-rooms" || $hook === "daily-api_page_daily-flush-consent"  ) {
			wp_enqueue_style( 'dtable-admin', DPEN_DAILY_CO_URI_PATH . 'assets/vendors/dtable/dtable.min.css', array(), DPEN_DAILY_CO_PLUGIN_VERSION );
			wp_enqueue_script( 'dtable-admin', DPEN_DAILY_CO_URI_PATH . 'assets/vendors/dtable/dtable.min.js', array(), DPEN_DAILY_CO_PLUGIN_VERSION, true );

			wp_enqueue_script( 'custom-script', DPEN_DAILY_CO_URI_PATH . 'assets/js/daily-bookly.js', array( 'dtable-admin' ), DPEN_DAILY_CO_PLUGIN_VERSION, true );
			wp_localize_script( 'custom-script', 'daily', array(
				'ajax_uri' => admin_url( 'admin-ajax.php' )
			) );
		}

		if ( $hook === "daily-api_page_daily-add-room" ) {
			wp_enqueue_style( 'datepicker-admin', DPEN_DAILY_CO_URI_PATH . 'assets/vendors/datepicker/jquery.datetimepicker.min.css', array(), DPEN_DAILY_CO_PLUGIN_VERSION );
			wp_enqueue_script( 'datepicker-admin', DPEN_DAILY_CO_URI_PATH . 'assets/vendors/datepicker/jquery.datetimepicker.full.min.js', array(), DPEN_DAILY_CO_PLUGIN_VERSION, true );

			wp_enqueue_script( 'custom-script', DPEN_DAILY_CO_URI_PATH . 'assets/js/daily-bookly.js', array( 'datepicker-admin' ), DPEN_DAILY_CO_PLUGIN_VERSION, true );
			wp_localize_script( 'custom-script', 'daily', array(
				'ajax_uri' => admin_url( 'admin-ajax.php' )
			) );
		}
	}

	public function register_menu() {
		add_menu_page( __( 'Daily API', 'daily-co-bookly' ),
			'Daily API',
			'manage_options',
			'daily-api-settings',
			array( $this, 'render_menu_html' ),
			'dashicons-video-alt2',
			6
		);

		//Sub Menu Page
		add_submenu_page(
			'daily-api-settings',
			__( 'Rooms', 'daily-co-bookly' ),
			__( 'Rooms', 'daily-co-bookly' ),
			'manage_options',
			'daily-rooms',
			array( $this, 'rendor_rooms_html' )
		);

		//Sub Menu Page
		add_submenu_page(
			'daily-api-settings',
			__( 'Add Room', 'daily-co-bookly' ),
			__( 'Add Room', 'daily-co-bookly' ),
			'manage_options',
			'daily-add-room',
			array( $this, 'render_add_room_html' )
		);

		add_submenu_page(
			'daily-api-settings',
			__( 'Flush Consent', 'daily-co-bookly' ),
			__( 'Flush Consent', 'daily-co-bookly' ),
			'manage_options',
			'daily-flush-consent',
			array( $this, 'render_flush_consent_html' )
		);

		//Sub Menu Page
		/*add_submenu_page(
			'daily-api-settings',
			__( 'Consent Forms', 'daily-co-bookly' ),
			__( 'Consent Forms', 'daily-co-bookly' ),
			'manage_options',
			'headroom-consent-form',
			array( $this, 'render_consent_forms' )
		);*/
	}

	public function render_flush_consent_html() {
		require DPEN_DAILY_CO_DIR_PATH . 'views/flush-consent.php';
	}

	/**
	 * Render HTML for main menu list
	 *
	 * @since 1.0.0
	 * @author Deepen
	 */
	public function render_menu_html() {
		//Save
		$this->save_api_key();

		$api_key     = get_option( '_dpen_daily_co_api_key' );
		$expiry_time = get_option( '_dpen_daily_co_expiry_time' );
		$domain      = get_option( '_dpen_daily_domain' );
		$from_email  = get_option( '_dpen_daily_from_email' );
		#$login_page  = get_option( '_dpen_daily_login_page' );

		require DPEN_DAILY_CO_DIR_PATH . 'views/settings.php';
	}

	public function render_consent_forms() {
		$users = get_users();
		require DPEN_DAILY_CO_DIR_PATH . 'views/consent-forms.php';
	}

	/**
	 * Save API Keys
	 * @since 1.0.0
	 * @author Deepen
	 */
	function save_api_key() {
		//Check Nonce
		if ( ! isset( $_POST['api_keys_dailyco'] ) || ! wp_verify_nonce( $_POST['api_keys_dailyco'], 'api_keys_nonce_dailyco' ) ) {
			return;
		}

		if ( isset( $_POST['save_api_key'] ) ) {
			$key    = sanitize_text_field( filter_input( INPUT_POST, 'api_key' ) );
			$expiry = sanitize_text_field( filter_input( INPUT_POST, 'expiry_time' ) );
			$domain = trailingslashit( sanitize_text_field( filter_input( INPUT_POST, 'domain' ) ) );
			$email  = sanitize_email( filter_input( INPUT_POST, 'from_email' ) );
			#$login_page = sanitize_text_field( filter_input( INPUT_POST, 'login_page' ) );

			update_option( '_dpen_daily_co_api_key', $key );
			update_option( '_dpen_daily_co_expiry_time', $expiry );
			update_option( '_dpen_daily_domain', $domain );
			update_option( '_dpen_daily_from_email', $email );
			#update_option( '_dpen_daily_login_page', $login_page );
		}

		self::set_message( 'updated', 'Settings Saved !' );
	}

	public function rendor_rooms_html() {
		require DPEN_DAILY_CO_DIR_PATH . 'views/rooms-list.php';
	}

	public function render_add_room_html() {
		if ( isset( $_GET['edit'] ) ) {
			$this->edit_room();

			$room = dailyco_api()->get_room_by_name( $_GET['edit'] );
			require DPEN_DAILY_CO_DIR_PATH . 'views/rooms-edit.php';
		} else {
			$this->add_room();
			require DPEN_DAILY_CO_DIR_PATH . 'views/rooms-add.php';
		}
	}

	public function add_room() {
		//Check Nonce
		if ( ! isset( $_POST['add_dailyco'] ) || ! wp_verify_nonce( $_POST['add_dailyco'], 'add_nonce_dailyco' ) ) {
			return;
		}

		if ( isset( $_POST['add_room'] ) ) {
			$room_name            = sanitize_text_field( filter_input( INPUT_POST, 'room_name' ) );
			$start_date           = sanitize_text_field( filter_input( INPUT_POST, 'start_date' ) );
			$expiration_time      = sanitize_text_field( filter_input( INPUT_POST, 'expiration_time' ) );
			$max_participants     = sanitize_text_field( filter_input( INPUT_POST, 'max_participants' ) );
			$enable_knocking      = sanitize_text_field( filter_input( INPUT_POST, 'enable_knocking' ) );
			$owner_only_broadcast = sanitize_text_field( filter_input( INPUT_POST, 'owner_only_broadcast' ) );
			$start_video_off      = sanitize_text_field( filter_input( INPUT_POST, 'start_video_off' ) );
			$start_audio_off      = sanitize_text_field( filter_input( INPUT_POST, 'start_audio_off' ) );
			$eject_at_room_exp    = sanitize_text_field( filter_input( INPUT_POST, 'eject_at_room_exp' ) );

			$end_date = date( 'Y/m/d H:i', strtotime( $start_date ) + absint( $expiration_time ) * 60 );
			$postData = array(
				'nbf'                  => dpen_daily_co_convert_timezone( array(
					'date'      => $start_date,
					'timezone'  => 'UTC',
					'timestamp' => true
				) ),
				'exp'                  => dpen_daily_co_convert_timezone( array(
					'date'      => $end_date,
					'timezone'  => 'UTC',
					'timestamp' => true
				) ),
				'privacy'              => 'public',
				'max_participants'     => !empty($max_participants) ? absint($max_participants) : 200,
				'enable_knocking'      => $enable_knocking,
				'owner_only_broadcast' => $owner_only_broadcast,
				'start_video_off'      => $start_video_off,
				'start_audio_off'      => $start_audio_off,
				'eject_at_room_exp'    => $eject_at_room_exp,
			);

			$result = dailyco_api()->create_room( $postData );

			//Clear Cache
			dpen_clear_room_cache();

			self::set_message( 'updated', 'Room Created !' );
		}
	}

	public function edit_room() {
		//Check Nonce
		if ( ! isset( $_POST['add_dailyco'] ) || ! wp_verify_nonce( $_POST['add_dailyco'], 'add_nonce_dailyco' ) ) {
			return;
		}

		if ( isset( $_POST['edit_room'] ) ) {
			$start_date           = sanitize_text_field( filter_input( INPUT_POST, 'start_date' ) );
			$expiration_time      = sanitize_text_field( filter_input( INPUT_POST, 'expiration_time' ) );
			$max_participants     = sanitize_text_field( filter_input( INPUT_POST, 'max_participants' ) );
			$enable_knocking      = sanitize_text_field( filter_input( INPUT_POST, 'enable_knocking' ) );
			$owner_only_broadcast = sanitize_text_field( filter_input( INPUT_POST, 'owner_only_broadcast' ) );
			$start_video_off      = sanitize_text_field( filter_input( INPUT_POST, 'start_video_off' ) );
			$start_audio_off      = sanitize_text_field( filter_input( INPUT_POST, 'start_audio_off' ) );
			$eject_at_room_exp    = sanitize_text_field( filter_input( INPUT_POST, 'eject_at_room_exp' ) );
			$end_date             = date( 'Y/m/d H:i', strtotime( $start_date ) + absint( $expiration_time ) * 60 );

			$postData = array(
				'nbf'                  => dpen_daily_co_convert_timezone( array(
					'date'      => $start_date,
					'timezone'  => 'UTC',
					'timestamp' => true
				) ),
				'exp'                  => dpen_daily_co_convert_timezone( array(
					'date'      => $end_date,
					'timezone'  => 'UTC',
					'timestamp' => true
				) ),
				'privacy'              => 'private',
				'max_participants'     => !empty($max_participants) ? absint($max_participants) : 200,
				'enable_knocking'      => $enable_knocking,
				'owner_only_broadcast' => $owner_only_broadcast,
				'start_video_off'      => $start_video_off,
				'start_audio_off'      => $start_audio_off,
				'eject_at_room_exp'    => $eject_at_room_exp,
			);

			$result = dailyco_api()->update_room( $postData, $_GET['edit'] );

			//Clear Cache
			dpen_clear_room_cache();

			self::set_message( 'updated', 'Settings Saved !' );
		}
	}

	public function delete_room() {
		$room     = filter_input( INPUT_POST, 'name' );
		$response = dailyco_api()->delete_room( $room );
		if ( $response->deleted ) {
			//Reset Cache
			dpen_clear_room_cache();
		}

		wp_send_json( $response );
		wp_die();
	}

	static function get_message() {
		return self::$message;
	}

	static function set_message( $class, $message ) {
		self::$message = '<div class=' . $class . '><p>' . $message . '</p></div>';
	}

}

new Daily_Co_Bookly_Admin();