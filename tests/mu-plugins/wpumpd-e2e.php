<?php
/**
 * What the E2E suite needs on the wp-env site.
 *
 * wp-env has no mail server, so mail is dropped: wp_mail() fails otherwise, and how a failure
 * looks isn't what the E2E specs are about. Everything else is WP-CLI commands the suite uses
 * to set up and inspect the site: wp wpumpd-e2e <command>.
 *
 * Only the commands are limited to WP-CLI. They do nothing in WPUnit or on page loads.
 */

add_filter( 'pre_wp_mail', '__return_true' );

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Register the field the way the Custom Fields addon does, a private addon this repo's CI
 * can't check out.
 */
add_action(
	'carbon_fields_register_fields',
	function () {
		\WPUM\Carbon_Fields\Container::make( 'user_meta', 'Personal Data E2E fields' )
			->set_datastore( new WPUM_User_Meta_Custom_Datastore() )
			->add_fields( array( \WPUM\Carbon_Fields\Field::make( 'text', WPUMPD_E2E_Command::FIELD, 'Phone' ) ) );
	}
);

/**
 * Set up and inspect the E2E site.
 */
class WPUMPD_E2E_Command {

	/**
	 * The password every member gets.
	 */
	const PASSWORD = 'TestPass123!';

	/**
	 * The custom field's meta key.
	 */
	const FIELD = 'wpum_e2e_phone';

	/**
	 * Create the custom field, once.
	 */
	public function setup() {
		global $wpdb;

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpum_fieldmeta WHERE meta_key = 'user_meta_key' AND meta_value = %s", self::FIELD ) );

		if ( ! $existing ) {
			$group_id = WPUM()->fields_groups->insert( array( 'name' => 'E2E' ) );
			$field_id = WPUM()->fields->insert(
				array(
					'group_id' => $group_id,
					'type'     => 'text',
					'name'     => 'Phone',
				)
			);

			$field = new WPUM_Field( $field_id );
			$field->update_meta( 'user_meta_key', self::FIELD );
		}

		WP_CLI::line( wp_json_encode( array( 'field' => self::FIELD ) ) );
	}

	/**
	 * Create (or recreate) a member with a value in the custom field. Prints the ID as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login. The email is <login>@example.com.
	 *
	 * [--role=<role>]
	 * : Role.
	 * ---
	 * default: subscriber
	 * ---
	 */
	public function create_member( $args, $assoc_args ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$login    = $args[0];
		$existing = get_user_by( 'login', $login );
		if ( $existing ) {
			wp_delete_user( $existing->ID );
		}

		foreach ( $this->requests_for( $login . '@example.com' ) as $request ) {
			wp_delete_post( $request->ID, true );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.com',
				'user_pass'  => self::PASSWORD,
				'role'       => $assoc_args['role'],
			)
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( $user_id );
		}

		carbon_set_user_meta( $user_id, self::FIELD, 'Phone of ' . $login );

		WP_CLI::line( wp_json_encode( array( 'id' => $user_id ) ) );
	}

	/**
	 * Print a member's custom field and their privacy requests as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login.
	 */
	public function member( $args ) {
		$user = get_user_by( 'login', $args[0] );

		WP_CLI::line(
			wp_json_encode(
				array(
					'phone'    => get_user_meta( $user->ID, self::FIELD, true ),
					'requests' => array_map(
						function ( $request ) {
							return array(
								'id'     => $request->ID,
								'action' => $request->action_name,
								'status' => $request->status,
							);
						},
						$this->requests_for( $user->user_email )
					),
				)
			)
		);
	}

	/**
	 * Create a request the way the form does. Prints its ID and the key its email carries.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login.
	 *
	 * <action>
	 * : export_personal_data or remove_personal_data.
	 */
	public function request( $args ) {
		$user       = get_user_by( 'login', $args[0] );
		$request_id = wp_create_user_request( $user->user_email, $args[1] );

		if ( is_wp_error( $request_id ) ) {
			WP_CLI::error( $request_id );
		}

		WP_CLI::line(
			wp_json_encode(
				array(
					'id'  => $request_id,
					'key' => wp_generate_user_request_key( $request_id ),
				)
			)
		);
	}

	/**
	 * Turn the lock on wp-login.php on or off.
	 *
	 * ## OPTIONS
	 *
	 * <state>
	 * : on or off.
	 */
	public function lock_wp_login( $args ) {
		wpum_update_option( 'lock_wplogin', 'on' === $args[0] );
	}

	/**
	 * Run the addon's exporter as WordPress does. Prints what it returns as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login.
	 */
	public function export( $args ) {
		$user = get_user_by( 'login', $args[0] );

		WP_CLI::line( wp_json_encode( $this->run( 'wp_privacy_personal_data_exporters', 'wpumpd_export_custom_fields_user_data', $user->user_email ) ) );
	}

	/**
	 * Run the addon's eraser as WordPress does. Prints what it returns as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login.
	 */
	public function erase( $args ) {
		$user = get_user_by( 'login', $args[0] );

		WP_CLI::line( wp_json_encode( $this->run( 'wp_privacy_personal_data_erasers', 'wpumpd_plugin_user_data_eraser', $user->user_email ) ) );
	}

	/**
	 * Find a callback in one of WordPress's privacy lists and run it.
	 *
	 * @param string $filter
	 * @param string $callback
	 * @param string $email
	 *
	 * @return array
	 */
	private function run( $filter, $callback, $email ) {
		foreach ( apply_filters( $filter, array() ) as $entry ) {
			if ( $callback === $entry['callback'] ) {
				return call_user_func( $entry['callback'], $email, 1 );
			}
		}

		WP_CLI::error( $callback . ' is not registered.' );
	}

	/**
	 * Every request for an email address.
	 *
	 * @param string $email
	 *
	 * @return WP_User_Request[]
	 */
	private function requests_for( $email ) {
		$ids = get_posts(
			array(
				'post_type'      => 'user_request',
				'post_status'    => array( 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'title'          => $email,
			)
		);

		return array_map( 'wp_get_user_request', $ids );
	}
}

WP_CLI::add_command( 'wpumpd-e2e', 'WPUMPD_E2E_Command' );
