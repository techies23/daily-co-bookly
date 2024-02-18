<div class="wrap">
    <h1>Add Room</h1>
    <div class="message">
		<?php
		$message = self::get_message();
		if ( isset( $message ) && ! empty( $message ) ) {
			echo $message;
		}
		?>
    </div>
    <form method="POST" action="">
		<?php wp_nonce_field( 'add_nonce_dailyco', 'add_dailyco' ); ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Name', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="room_name" type="text" id="daily-co-room-name" value="<?php echo ! empty( $_POST['room_name'] ) ? esc_attr( $_POST['room_name'] ) : false; ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Start Date', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="start_date" type="text" id="daily-co-start_date" value="<?php echo ! empty( $_POST['start_date'] ) ? esc_attr( $_POST['start_date'] ) : dpen_daily_co_convert_timezone( array( 'timestamp' => false ), 'Y/m/d H:i' ); ?>" class="regular-text datepicker-render">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Expiration in Minutes', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="expiration_time" type="number" id="daily-co-expiration-time" value="<?php echo ! empty( $_POST['expiration_time'] ) ? esc_attr( $_POST['expiration_time'] ) : 60; ?>" class="regular-text">
                    <p class="description">Give an expiration duration in minutes.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Max Participants', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="max_participants" type="number" id="daily-co-max-participants" value="<?php echo ! empty( $_POST['max_participants'] ) ? esc_attr( $_POST['max_participants'] ) : false; ?>" class="regular-text">
                    <p class="description">Leave blank for unlimited.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Enable Knocking', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="enable_knocking" type="checkbox" id="daily-co-knocking">
                    <p class="description">If a room is non-public, and a user isn't logged in and doesn't have a meeting token, then let them "knock"
                        to request access to the room. Default is false for api-created rooms and true for dashboard-created rooms.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Owner only Broadcaset', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="owner_only_broadcast" type="checkbox" id="daily-co-owner_only_broadcast">
                    <p class="description">Only the meeting owners are allowed to turn on camera, unmute mic, and share screen. </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Start Video Off', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="start_video_off" type="checkbox" id="daily-co-start_video_off">
                    <p class="description">Always start with camera off when a user joins a meeting in the room.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Start Audio Off', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="start_audio_off" type="checkbox" id="daily-co-start_audio_off">
                    <p class="description">Always start with microphone muted when a user joins a meeting in the room.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Eject At Room Expiration', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="eject_at_room_exp" type="checkbox" id="daily-co-eject_at_room_exp">
                    <p class="description">If there's a meeting going on at room exp time, end the meeting by kicking everyone out.</p>
                </td>
            </tr>
            </tbody>
        </table>

        <input type="submit" class="button button-primary" name="add_room" value="<?php _e( 'Add', 'daily-co-bookly' ); ?>"/>
    </form>
</div>