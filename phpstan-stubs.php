<?php
/**
 * PHPStan stubs for the WPUM core classes and functions the addon uses. Core isn't a
 * Composer dependency, so PHPStan can't see it.
 *
 * @package wpum-personal-data
 */

// phpcs:disable

abstract class WPUM_Form {
	/** @var array */
	protected $fields = array();
	/** @var string */
	protected $action = '';
	/** @var array */
	protected $errors = array();
	/** @var array */
	protected $steps = array();
	/** @var int */
	protected $step = 0;
	/** @var string */
	public $form_name = '';

	public function process() {}
	public function output( $atts = array() ) {}
	public function add_error( $error, $error_code = '' ) {}
	public function get_action() {}
	public function get_step() {}
	public function get_step_key( $step = '' ) {}
	public function get_fields( $key ) {}
	protected function init_fields() {}
	protected function sort_by_priority( $a, $b ) {}
	protected function get_posted_fields() {}
	protected function validate_fields( $values ) {}
}

/**
 * @return object
 */
function WPUM() {}
function wpum_get_option( $key = '', $default = false ) {}
function wpum_get_core_page_id( $page = '' ) {}
function carbon_get_user_meta( $id, $name, $container_id = '' ) {}
