<!DOCTYPE html>
<html style="margin-top: 0 !important;">
<head>
    <meta charset="UTF-8">
    <meta name="format-detection" content="telephone=no">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Session Join</title>
    <link rel='stylesheet' type="text/css" href="<?php echo DPEN_DAILY_CO_URI_PATH . 'assets/css/daily-api.css'; ?>">
    <link rel='stylesheet' type="text/css" href="<?php echo DPEN_DAILY_CO_URI_PATH . 'assets/css/daily-api-join-room.css'; ?>">
<body class="dailyco-start-room">
<?php
$room = isset( $_GET['s'] ) ? $_GET['s'] : false;
if ( ! empty( $room ) ) {
	$token  = Daily_Co_Bookly_API_Helper::validating_generated_token( $room, true );
	$domain = get_option( '_dpen_daily_domain' );
	if ( ! empty( $token ) ) {
		if ( isset( $token['error'] ) && $token['error'] === "user-not-logged-in" ) {
			echo '<p>' . $token['info'] . '</p>';
		} else {
			$room = dailyco_api()->get_room_by_name( $room );
			if ( ! empty( $room ) && ! empty( $room->error ) ) {
				?>
                <div id="daily-co-error-output" class="daily-co-error-output">
                    <img src="<?php echo DPEN_DAILY_CO_URI_PATH; ?>assets/images/warning.png">
                    <p><?php echo ucfirst( $room->info ); ?></p>
                </div>
				<?php
			} else {
				wp_enqueue_script( 'daily-api-scheduler', DPEN_DAILY_CO_URI_PATH . 'assets/js/daily-api-room-scheduler.js', array( 'jquery', 'daily-api-script' ), time(), true );
				wp_localize_script( 'daily-api-scheduler', 'dailyco', array(
					'domain_uri'  => esc_url( $domain ),
					'plugin_path' => DPEN_DAILY_CO_URI_PATH,
					'room'        => ! empty( $room ) ? $room : false,
					'token'       => esc_attr( $token )
				) );
				?>
                <div id="daily-co-expiry-countdown"></div>
                <div id="daily-co-error-output" class="daily-co-error-output"></div>
                <div id="daily-co-output-room" class="daily-co-output-room" data-userid="<?php echo get_current_user_id(); ?>"></div>
				<?php
			}
		}
	}
} else {
	echo "<p>Please login into your account first to be able join scheduled video meeting.</p>";
}
wp_footer();
?>
</body>
</html>