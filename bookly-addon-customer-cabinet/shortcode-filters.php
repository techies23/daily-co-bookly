<?php
add_filter( 'wpml_translate_single_string', 'daily_co_bookly_translate_names', 10, 4 );
function daily_co_bookly_translate_names( $original_value, $plugin, $name, $language_code ) {
	if ( $plugin === "bookly" && $name === "bookly_l10n_label_employee" ) {
		$original_value = __( 'Therapist', 'daily-co-bookly' );
	}

	return $original_value;
}