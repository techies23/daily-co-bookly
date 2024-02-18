<?php
// No Permission
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! class_exists( 'Daily_Co_Bookly_API_Helper' ) ) {

	/**
	 * Helper for API daily
	 *
	 * @since 1.0.0
	 * @author Deepen Bajracharya
	 *
	 * Class Daily_Co_Bookly_API_Helper
	 */
	class Daily_Co_Bookly_API_Helper {

		public static function validating_generated_token( $room, $owner = false ) {
			if ( is_user_logged_in() && ! empty( $room ) ) {
				if ( $owner ) {
					$existing_token = get_user_meta( get_current_user_id(), '_daily_co_room_token_owner', true );
				} else {
					$existing_token = get_user_meta( get_current_user_id(), '_daily_co_room_token_user', true );
				}

				if ( ! empty( $existing_token ) ) {
					$check_token = dailyco_api()->validate_room_token( $existing_token );

					if ( ! empty( $check_token->error ) ) {
						$token = self::generete_token( $room, $owner );
					} else {
						//If room name is not the same from the token then generate new one
						if ( $check_token->room_name !== $room ) {
							$token = self::generete_token( $room, $owner );
						} else {
							$token = $existing_token;
						}
					}

				} else {
					$token = self::generete_token( $room, $owner );
				}
			} else {
				$token = array(
					'error' => 'user-not-logged-in',
					'info'  => 'User is not authorized to access this room. Please try logging in to access this meeting.'
				);
			}

			return $token;
		}

		public static function generete_token( $room, $owner = false ) {
			$nbf = dpen_daily_co_convert_timezone( array(
				'timezone'  => 'UTC',
				'timestamp' => true
			) );
			$data['properties'] = array(
				'nbf'       => $nbf,
				'room_name' => $room,
				'is_owner'  => (bool) $owner
			);
			$token              = dailyco_api()->generate_room_token( $data );
			if ( $owner ) {
				update_user_meta( get_current_user_id(), '_daily_co_room_token_owner', $token->token );
			} else {
				update_user_meta( get_current_user_id(), '_daily_co_room_token_user', $token->token );
			}

			return $token->token;
		}

		private function redirect_if_needed() {
			if ( ! is_user_logged_in() && is_page( 'room/start' ) ) {
				$login_page = get_option( '_dpen_daily_login_page' );
				/*if( !empty( $login_page ) ) {
					wp_safe_redirect( wp_login_url( get_permalink() ) );
				}*/
				wp_safe_redirect( wp_login_url( get_permalink() ) );

				exit();
			}
		}

	}

	//Calling this method
	new Daily_Co_Bookly_API_Helper();
}
