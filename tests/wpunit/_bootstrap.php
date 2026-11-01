<?php
require_once __DIR__ . '/AddonTestCase.php';

// Load the form classes without creating the forms, which hooks their handlers: WPUM loads
// them the first time a form is shown or posted.
if ( defined( 'WPUM_PLUGIN_DIR' ) && defined( 'WPUMPD_PLUGIN_DIR' ) ) {
	if ( ! class_exists( 'WPUM_Form' ) ) {
		require_once WPUM_PLUGIN_DIR . 'includes/abstracts/class-wpum-form.php';
	}
	if ( ! class_exists( 'WPUM_Form_Personal_Data' ) ) {
		require_once WPUMPD_PLUGIN_DIR . 'includes/class-wpum-form-personal-data.php';
	}
	if ( ! class_exists( 'WPUM_Form_Data_Erasure' ) ) {
		require_once WPUMPD_PLUGIN_DIR . 'includes/class-wpum-form-data-erasure.php';
	}
}
