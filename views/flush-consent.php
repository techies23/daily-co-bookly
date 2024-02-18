<style>
    .dataTables_length {
        margin-bottom: 20px;
    }

    .dataTables_wrapper {
        margin-top: 30px;
    }
</style>
<div class="wrap">
    <h1>Flush Consent for Therapists</h1>
    <table class="datatable-render wp-list-table widefat fixed striped">
        <thead>
        <th class="manage-column"><?php esc_html_e( 'Therapist Name', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Therapist Email', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'WP User', 'daily-co-bookly' ); ?></th>
        <th class="manage-column"><?php esc_html_e( 'Action', 'daily-co-bookly' ); ?></th>
        </thead>
        <tbody class="bookly-flush-cache-tbl-body">
		<?php
		$staffs   = Daily_Co_Bookly_Datastore::getStaffList();
		$wp_users = get_users(
			array(
				'role__in' => [ 'administrator', 'client', 'clients', 'um_clients' ],
				'number'   => - 1,
			)
		);
		if ( ! empty( $staffs ) ) {
			foreach ( $staffs as $staff ) {
			    if( !empty($staff['wp_user_id'])) {
				    ?>
                    <tr>
                        <td><?php echo $staff['full_name']; ?></td>
                        <td><?php echo $staff['email'] ?></td>
                        <td>
						    <?php
						    if ( ! empty( $wp_users ) ) {
							    echo "<select class='daily-co-admin-wp-user-flush-consent'>";
							    echo "<option>Select a User</option>";
							    foreach ( $wp_users as $user ) {
								    ?>
                                    <option value="<?php echo $user->ID; ?>"><?php echo $user->display_name; ?></option>
								    <?php
							    }
							    echo "</select>";
						    }
						    ?>
                        </td>
                        <td><a href="javascript:void(0);" class="daily-co-admin-flush-btn" data-therapist="<?php echo $staff['wp_user_id']; ?>">Flush</a></td>
                    </tr>
				    <?php
			    }
			}
		} else {
			?>
            <tr>
                <td colspan="4">No Staffs Assigned. Please assign from Bookly side.</td>
            </tr>
			<?php
		}
		?>
        </tbody>
    </table>
</div>