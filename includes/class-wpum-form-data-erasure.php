<?php
/**
 * Handles the form where users can request their personal data erasure.
 *
 * @package     wpum-personal-data
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPUM_Form_Data_Erasure extends WPUM_Form {

	/**
	 * Form name.
	 *
	 * @var string
	 */
	public $form_name = 'data-erasure';

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 */
	protected static $_instance = null;

	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'process' ) );
		$this->steps = (array) apply_filters(
			'personal_data_erasure_steps', array(
				'submit'           => array(
					'name'     => __( 'Personal data erasure request', 'wpum-personal-data' ),
					'view'     => array( $this, 'submit' ),
					'handler'  => array( $this, 'submit_handler' ),
					'priority' => 10,
				),
				'submit_confirmed' => array(
					'name'     => __( 'Personal data erasure request confirmation', 'wpum-personal-data' ),
					'view'     => array( $this, 'confirmation' ),
					'handler'  => false,
					'priority' => 11,
				),
			)
		);
		uasort( $this->steps, array( $this, 'sort_by_priority' ) );
		if ( isset( $_POST['step'] ) ) {
			$this->step = is_numeric( $_POST['step'] ) ? max( absint( $_POST['step'] ), 0 ) : array_search( $_POST['step'], array_keys( $this->steps ) );
		} elseif ( ! empty( $_GET['step'] ) ) {
			$this->step = is_numeric( $_GET['step'] ) ? max( absint( $_GET['step'] ), 0 ) : array_search( $_GET['step'], array_keys( $this->steps ) );
		}
	}

	/**
	 * Initializes the fields used in the form.
	 */
	public function init_fields() {
		if ( $this->fields ) {
			return;
		}

		$user = wp_get_current_user();

		$this->fields = apply_filters(
			'personal_data_erasure_form_fields', array(
				'personal_data_erasure' => array(
					'password_erasure' => array(
						'label'       => __( 'Current password', 'wpum-personal-data' ),
						'description' => __( 'Enter your current password to request erasure of your personal data.', 'wpum-personal-data' ),
						'type'        => 'password',
						'required'    => true,
						'placeholder' => '',
						'priority'    => 2,
					),
				),
			)
		);
	}

	/**
	 * Show the form.
	 *
	 * @return void
	 */
	public function submit() {
		$this->init_fields();
		$data = [
			'form'         => $this->form_name,
			'action'       => $this->get_action(),
			'fields'       => $this->get_fields( 'personal_data_erasure' ),
			'step'         => $this->get_step(),
			'step_name'    => $this->steps[ $this->get_step_key( $this->get_step() ) ]['name'],
			'submit_label' => esc_html__( 'Request personal data erasure', 'wpum-personal-data' ),
		];

		if ( ! is_user_logged_in() ) {
			return;
		}

		WPUM()->templates
			->set_template_data( $data )
			->get_template_part( 'forms/data-erasure-form' );
	}

	/**
	 * Handle submission of the form.
	 *
	 * @return void
	 */
	public function submit_handler() {

		try {

			$this->init_fields();

			$values = $this->get_posted_fields();

			if ( ! wp_verify_nonce( $_POST['data_erasure_nonce'], 'verify_data_erasure_form' ) ) {
				return;
			}

			if ( empty( $_POST['submit_data_erasure'] ) ) {
				return;
			}

			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			$user               = wp_get_current_user();
			$submitted_password = $values['personal_data_erasure']['password_erasure'];

			if ( $user instanceof WP_User && wp_check_password( $submitted_password, $user->data->user_pass, $user->ID ) && is_user_logged_in() ) {

				$request_id = wp_create_user_request( $user->user_email, 'remove_personal_data' );

				if ( is_wp_error( $request_id ) ) {
					throw new Exception( $request_id->get_error_message() );
				} else {

					wp_send_user_request( $request_id );

					$this->step ++;
				}
			} else {
				throw new Exception( __( 'The password you entered is incorrect.', 'wpum-personal-data' ) );
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return;
		}

	}

	/**
	 * Display confirmation message.
	 *
	 * @return void
	 */
	public function confirmation() {

		$user = wp_get_current_user();

		$message = sprintf( esc_html__( 'A confirmation email has been sent to %s. Click the link within the email to confirm your erasure request.', 'wpum-personal-data' ), '<strong>' . $user->user_email . '</strong>' );

		echo '<h2>' . $this->steps[ $this->get_step_key( $this->get_step() ) ]['name'] . '</h2>';

		$data = [
			'message' => apply_filters( 'wpumpd_personal_data_erasure_request_success_message', $message ),
		];
		WPUM()->templates
			->set_template_data( $data )
			->get_template_part( 'messages/general', 'success' );

	}

}
