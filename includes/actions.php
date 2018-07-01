<?php
/**
 * Handles actions of the addon.
 *
 * @package     wpum-personal-data
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a new personal data tab within the account page.
 *
 * @param array $tabs
 * @return void
 */
function wpumpd_register_new_account_tabs( $tabs ) {

	$tabs['personal-data'] = [
		'name'     => esc_html__( 'Personal data', 'wpum-personal-data' ),
		'priority' => 3,
	];

	return $tabs;

}
add_filter( 'wpum_get_account_page_tabs', 'wpumpd_register_new_account_tabs' );

/**
 * Display the content for the account personal data tab.
 *
 * @return void
 */
function wpumpd_register_account_tab_content() {

	echo WPUM()->forms->get_form( 'personal-data', [] );

	echo '<br/>';

	echo WPUM()->forms->get_form( 'data-erasure', [] );

}
add_action( 'wpum_account_page_content_personal-data', 'wpumpd_register_account_tab_content' );

/**
 * Tell WPUM to load the form for this addon from within this plugin's path.
 *
 * @param string $path
 * @param string $form_name
 * @return string
 */
function wpumpd_register_form_path( $path, $form_name ) {
	if ( 'personal-data' == $form_name ) {
		$path = WPUMPD_PLUGIN_DIR . 'includes/class-wpum-form-personal-data.php';
	} elseif ( 'data-erasure' == $form_name ) {
		$path = WPUMPD_PLUGIN_DIR . 'includes/class-wpum-form-data-erasure.php';
	}
	return $path;
}
add_filter( 'wpum_load_form_path', 'wpumpd_register_form_path', 20, 2 );

/**
 * Add a new path to WPUM's template loader.
 *
 * @param array $file_paths
 * @return array
 */
function wpumpd_set_template_loader_path( $file_paths ) {
	$file_paths[13] = trailingslashit( WPUMPD_PLUGIN_DIR . 'templates' );
	return $file_paths;
}
add_filter( 'wpum_template_paths', 'wpumpd_set_template_loader_path' );
