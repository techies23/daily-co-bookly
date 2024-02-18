<style>
    .dataTables_length {
        margin-bottom: 20px;
    }

    .dataTables_wrapper {
        margin-top: 30px;
    }
</style>
<div class="wrap">
    <h1>API Room List</h1>
    <p class="description">Only Shows API created rooms here.</p>
    <a href="<?php echo add_query_arg( array( 'cache_flush' => true ) ) ?>">Clear Room Cache</a> ( Click this if you don't see new created rooms here
    )
    <div class="error" style="display: none;"></div>
    <div class="updated" style="display: none;"></div>
    <table class="datatable-render wp-list-table widefat fixed striped">
        <thead>
        <th class="manage-column"><?php esc_html_e( 'Name', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Privacy', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'URL', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Created', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Start Date', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Expiry', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Action', 'daily-co-bookly' ); ?></th>
        </thead>
        <tbody>
		<?php
		$data = dpen_get_cached_rooms();
		if ( ! empty( $data->data ) ) {
			foreach ( $data->data as $room ) {
				if ( $room->api_created ) {
					$nbf = dpen_daily_co_convert_timezone( array(
						'date'        => date( 'Y-m-d H:i', $room->config->nbf ),
					) );
					$exp = dpen_daily_co_convert_timezone( array(
						'date' => date( 'Y-m-d H:i', $room->config->exp )
					) );
					$created_at = dpen_daily_co_convert_timezone( array(
						'date' => $room->created_at
					), 'F d, Y h:i a' );
					?>
                    <tr>
                        <td><?php echo $room->name; ?></td>
                        <td><?php echo $room->privacy; ?></td>
                        <td><a href="<?php echo home_url( '/room/join/?j=' ) . $room->name; ?>">Join</a></td>
                        <td><?php echo $created_at . ' (UTC+2)'; ?></td>
                        <td><?php echo ! empty( $room->config->nbf ) ? date( 'F d, Y h:i a', $nbf ) . ' (UTC+2)' : 'N/A'; ?></td>
                        <td><?php echo date( 'F d, Y h:i a', $exp ) . ' (UTC+2)'; ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=daily-add-room&edit=' . $room->name ); ?>" rel="nofollow" class="edit-room">Edit</a>
                            <a href="javascript:void(0);" rel="nofollow" class="delete-room" data-room="<?php echo esc_attr( $room->name ); ?>">Delete</a>
                        </td>
                    </tr>
					<?php
				}
			}
		}
		?>
        </tbody>
    </table>
</div>