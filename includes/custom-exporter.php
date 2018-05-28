<?php
/**
 * Add a new exporter for the privacy tools.
 *
 * @package     wpum-personal-data
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a new exporter for custom fields registered through WPUM.
 *
 * @param array $exporters
 * @return void
 */
function wpumpd_plugin_register_exporters( $exporters ) {

	$exporters[] = array(
		'exporter_friendly_name' => esc_html__( 'Additional account details', 'wpum-personal-data' ),
		'callback'               => 'wpumpd_export_custom_fields_user_data',
	);

	return $exporters;

}
add_filter( 'wp_privacy_personal_data_exporters', 'wpumpd_plugin_register_exporters' );

/**
 * Inject all the additional details generated through WPUM.
 *
 * @param string $email_address
 * @param integer $page
 * @return void
 */
function wpumpd_export_custom_fields_user_data( $email_address, $page = 1 ) {

	$export_items = array();

	$user = get_user_by( 'email', $email_address );

	if ( $user && $user->ID ) {

		$item_id     = "additional-user-data-{$user->ID}";
		$group_id    = 'user';
		$group_label = esc_html__( 'Additional account details', 'wpum-personal-data' );
		$data        = array();

		if ( carbon_get_user_meta( $user->ID, 'current_user_avatar' ) ) {
			$data[] = array(
				'name'  => esc_html__( 'User avatar', 'wpum-personal-data' ),
				'value' => carbon_get_user_meta( $user->ID, 'current_user_avatar' ),
			);
		}

		if ( carbon_get_user_meta( $user->ID, 'user_cover' ) ) {
			$data[] = array(
				'name'  => esc_html__( 'User profile cover', 'wpum-personal-data' ),
				'value' => carbon_get_user_meta( $user->ID, 'user_cover' ),
			);
		}

		$fields = WPUM()->fields->get_fields(
			[
				'orderby' => 'field_order',
				'order'   => 'ASC',
			]
		);

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( $field->is_primary() ) {
					continue;
				}
				if ( ! empty( carbon_get_user_meta( $user->ID, $field->get_meta( 'user_meta_key' ) ) ) ) {

					$value = carbon_get_user_meta( $user->ID, $field->get_meta( 'user_meta_key' ) );

					if ( $field->get_type() == 'checkbox' ) {
						$value = esc_html__( 'Yes', 'wpum-personal-data' );
					} elseif ( $field->get_type() == 'dropdown' || $field->get_type() == 'radio' ) {
						$options = $field->get_meta( 'dropdown_options' );
						if ( is_array( $options ) ) {
							foreach ( $options as $key => $option ) {
								if ( $option['value'] == $value ) {
									$value = $option['label'];
								}
							}
						}
					} elseif ( $field->get_type() == 'multiselect' || $field->get_type() == 'multicheckbox' ) {

						$stored_field_options = $field->get_meta( 'dropdown_options' );
						$stored_options       = [];
						$found_options_labels = [];

						foreach ( $stored_field_options as $key => $stored_option ) {
							$stored_options[ $stored_option['value'] ] = $stored_option['label'];
						}

						$values = [];

						foreach ( $value as $user_stored_value ) {
							$values[] = $stored_options[ $user_stored_value ];
						}

						$value = implode( ', ', $values );

					}

					$data[] = array(
						'name'  => $field->get_name(),
						'value' => $value,
					);
				}
			}
		}

		$export_items[] = array(
			'group_id'    => $group_id,
			'group_label' => $group_label,
			'item_id'     => $item_id,
			'data'        => $data,
		);

	}

	return array(
		'data' => $export_items,
		'done' => true,
	);

}
