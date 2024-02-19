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
		if ( ! empty( $legacyData ) ) {
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
		$this->wpdb->delete(
			$this->table_name,
			[ 'id' => $id ]
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