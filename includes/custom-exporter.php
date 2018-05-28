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
		'exporter_friendly_name' => esc_html__( 'Additional account details' ),
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
		$group_label = esc_html__( 'Additional account details' );
		$data        = array();

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