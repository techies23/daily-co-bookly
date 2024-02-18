<div class="wrap">
    <h1>Daily API Settings</h1>

    <div class="message">
		<?php
		$message = self::get_message();
		if ( isset( $message ) && ! empty( $message ) ) {
			echo $message;
		}
		?>
    </div>
    <form method="POST" action="">
		<?php wp_nonce_field( 'api_keys_nonce_dailyco', 'api_keys_dailyco' ); ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'API Key', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="api_key" type="text" id="daily-co-api-key" value="<?php echo ! empty( $api_key ) ? esc_attr( $api_key ) : false; ?>" class="regular-text">
                    <p class="description">Get your keys from <a href="https://dashboard.daily.co/">Here</a>. Under "Developer" Tab.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Daily.co Domain', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="domain" type="url" id="daily-co-domain" placeholder="https://your-domain.daily.co/" value="<?php echo ! empty( $domain ) ? $domain : 'https://your-domain.daily.co/'; ?>" class="regular-text">
                    <p class="description">Your Domain Name for the App. Should always end with '/' slash. Ex: https://your-domain.daily.co/</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'Meeting Expiry Time', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="expiry_time" type="number" id="daily-co-expiry-time-room" value="<?php echo ! empty( $expiry_time ) ? esc_attr( $expiry_time ) : '1'; ?>" class="regular-text">
                    <p class="description">Defined in minutes. Cannot be lower than 1.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" valign="top">
					<?php _e( 'From Email Address', 'daily-co-bookly' ); ?>
                </th>
                <td>
                    <input name="from_email" type="email" id="daily-co-from-email" value="<?php echo ! empty( $from_email ) ? esc_attr( $from_email ) : 'no-reply@headroom.co.za'; ?>" class="regular-text">
                    <p class="description">"From" email address for reschedule, cancelled or new booking.</p>
                </td>
            </tr>
            </tbody>
        </table>

        <input type="submit" class="button button-primary" name="save_api_key" value="<?php _e( 'Save', 'daily-co-bookly' ); ?>"/>
    </form>
</div>