<?php
// No Permission
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! class_exists( 'Daily_Co_Bookly_API' ) ) {

	/**
	 * MAIN API CLASS
	 *
	 * @since 1.0.0
	 * @author Deepen Bajracharya
	 *
	 * Class Daily_Co_Bookly_API
	 */
	class Daily_Co_Bookly_API {

		private static $_instance = null;

		private $api_uri = 'https://api.daily.co/v1/';

		private $key;

		public static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function __construct() {
			$this->key = get_option( '_dpen_daily_co_api_key' );
		}

		protected function sendRequest( $calledFunction, $data, $request = "GET" ) {
			$request_url = $this->api_uri . $calledFunction;
			$args        = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->key,
					'Content-Type'  => 'application/json'
				)
			);

			if ( $request == "GET" ) {
				$args['body'] = ! empty( $data ) ? $data : array();
				$response     = wp_remote_get( $request_url, $args );
			} else if ( $request == "DELETE" ) {
				$args['body']   = ! empty( $data ) ? json_encode( $data ) : array();
				$args['method'] = "DELETE";
				$response       = wp_remote_request( $request_url, $args );
			} else if ( $request == "PATCH" ) {
				$args['body']   = ! empty( $data ) ? json_encode( $data ) : array();
				$args['method'] = "PATCH";
				$response       = wp_remote_request( $request_url, $args );
			} else {
				$args['body']   = ! empty( $data ) ? json_encode( $data ) : array();
				$args['method'] = "POST";
				$response       = wp_remote_post( $request_url, $args );
			}

			$response = wp_remote_retrieve_body( $response );
			if ( ! $response ) {
				return false;
			}

			return json_decode( $response );
		}

		public function create_room( $data = array() ) {
			$data = array(
				'name'       => ! empty( $data['name'] ) ? sanitize_title( $data['name'] ) : '',
				'privacy'    => ! empty( $data['privacy'] ) ? $data['privacy'] : 'public',
				'properties' => array(
					'nbf'                  => ! empty( $data['nbf'] ) ? $data['nbf'] - 60 * 5 : '',
					'exp'                  => ! empty( $data['exp'] ) ? $data['exp'] : time() + 60 * 60,
					'max_participants'     => ! empty( $data['max_participants'] ) ? $data['max_participants'] : 20,
					'enable_knocking'      => ! empty( $data['enable_knocking'] ) ? true : false,
					'owner_only_broadcast' => ! empty( $data['owner_only_broadcast'] ) ? true : false,
					'start_video_off'      => ! empty( $data['start_video_off'] ) ? true : false,
					'start_audio_off'      => ! empty( $data['start_audio_off'] ) ? true : false,
					'eject_at_room_exp'    => true,
					'enable_new_call_ui'   => true
				)
			);

			/*dump( date( 'Y-m-d h:i a', $data['properties']['nbf'] ) );
			dump( date( 'Y-m-d h:i a', $data['properties']['exp'] ) );
			dump( $data );
			die;*/

			return $this->sendRequest( 'rooms', $data, "POST" );
		}

		public function update_room( $data = array(), $room_name ) {
			$data = array(
				'privacy'    => ! empty( $data['privacy'] ) ? $data['privacy'] : 'public',
				'properties' => array(
					'nbf'                  => ! empty( $data['nbf'] ) ? $data['nbf'] - 60 * 5 : '',
					'exp'                  => ! empty( $data['exp'] ) ? $data['exp'] : time() + 60 * 60,
					'max_participants'     => ! empty( $data['max_participants'] ) ? $data['max_participants'] : 20,
					'enable_knocking'      => ! empty( $data['enable_knocking'] ) ? true : false,
					'owner_only_broadcast' => ! empty( $data['owner_only_broadcast'] ) ? true : false,
					'start_video_off'      => ! empty( $data['start_video_off'] ) ? true : false,
					'start_audio_off'      => ! empty( $data['start_audio_off'] ) ? true : false,
					'eject_at_room_exp'    => true,
					'enable_new_call_ui'   => true
				)
			);

			return $this->sendRequest( 'rooms/' . $room_name, $data, "POST" );
		}

		public function get_room_by_name( $room ) {
			return $this->sendRequest( 'rooms/' . $room, false, "GET" );
		}

		public function get_rooms( $data = array() ) {
			$data = array(
				'limit'          => '',
				'ending_before'  => '',
				'starting_after' => ''
			);

			return $this->sendRequest( 'rooms', $data, "GET" );
		}

		public function delete_room( $room ) {
			return $this->sendRequest( 'rooms/' . $room, false, "DELETE" );
		}

		public function validate_room_token( $token ) {
			return $this->sendRequest( 'meeting-tokens/' . $token, false, "GET" );
		}

		public function generate_room_token( $data = array() ) {
			$postData['properties'] = array(
				'nbf'       => ! empty( $data['properties']['nbf'] ) ? $data['properties']['nbf'] - 60 * 5 : false,
				'exp'       => ! empty( $data['properties']['exp'] ) ? $data['properties']['exp'] : time() + 60 * 60,
				'room_name' => ! empty( $data['properties']['room_name'] ) ? $data['properties']['room_name'] : false,
				'is_owner'  => ! empty( $data['properties']['is_owner'] ) ? true : false,
			);

			return $this->sendRequest( 'meeting-tokens', $data, "POST" );
		}
	}

	function dailyco_api() {
		return Daily_Co_Bookly_API::instance();
	}

	//Calling this method
	dailyco_api();
}
