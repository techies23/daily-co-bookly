<?php

namespace Headroom\Dailyco\Datastore;

class Appointments extends DatabaseHandler {

	protected function __construct() {
		parent::__construct();
	}

	public function get() {
		return $this->wpdb->get_results( "SELECT * FROM {$this->table_name}" );
	}

	public function getByUserAppointment( $user_id, $appointment_id ) {
		$legacyData = get_user_meta( $user_id, '_daily_co_room_details_' . $appointment_id, true );

		//Delete this if error
		if ( ! empty( $legacyData ) && ! empty( $legacyData->error ) ) {
			delete_user_meta( $user_id, '_daily_co_room_details_' . $appointment_id );
		}

		if ( ! empty( $legacyData ) && ! isset( $legacyData->error ) ) {
			$legacyData->legacy = true;

			return $legacyData;
		}

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE user_id = %d AND appointment_id = %d",
			$user_id,
			$appointment_id
		);

		return $this->wpdb->get_row( $query );
	}

	public function create( $user_id, $room_name, $appointment_id, $value ) {
		return $this->wpdb->insert( $this->table_name, [
			'user_id'        => $user_id,
			'name'           => $room_name,
			'appointment_id' => $appointment_id,
			'value'          => json_encode( $value )
		] );
	}

	public function update( $id, $user_id, $room_name, $appointment_id, $value = null ) {
		$values = [
			'user_id'        => $user_id,
			'name'           => $room_name,
			'appointment_id' => $appointment_id,
		];

		if ( ! empty( $value ) ) {
			$values['value'] = $value;
		}

		return $this->wpdb->update(
			$this->table_name,
			$values,
			[ 'id' => $id ]
		);
	}

	public function delete( $id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$id
		);

		//Delete Room first
		$data = $this->wpdb->get_row( $query );
		if ( ! empty( $data->name ) ) {
			$response = dailyco_api()->delete_room( $data->name );
			if ( $response->deleted ) {
				//Reset Cache
				dpen_clear_room_cache();
			}
		}

		//Delete table entry
		$this->wpdb->delete(
			$this->table_name,
			[ 'id' => $id ]
		);
	}

	public function deleteByAppointmentId( $appointment_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE appointment_id = %d",
			$appointment_id
		);

		//Delete Room first
		$data = $this->wpdb->get_row( $query );
		if ( ! empty( $data->name ) ) {
			$response = dailyco_api()->delete_room( $data->name );
			if ( $response->deleted ) {
				//Reset Cache
				dpen_clear_room_cache();
			}
		}

		//Delete table entry
		$this->wpdb->delete(
			$this->table_name,
			[ 'appointment_id' => $appointment_id ]
		);
	}

	/**
	 * Get Customer Appointment based on order_id
	 *
	 * @param  int  $order_id
	 * @param  int  $payment_id
	 * @param  int  $customer_id
	 *
	 * @return string
	 */
	public function getMaxCustomerAppointmentsByOrderID( int $order_id, int $payment_id, int $customer_id ): string {
		$table_name = $this->wpdb->prefix . 'bookly_customer_appointments';

		return $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MAX(appointment_id) FROM {$table_name} WHERE order_id = %d AND payment_id = %d AND customer_id = %d",
				$order_id,
				$payment_id,
				$customer_id
			)
		);
	}


	private static $_instance = null;

	public static function instance(): ?Appointments {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}