<?php
/**
 * Base test case for the Personal Data addon tests.
 *
 * Provides common setup: bootstraps WPUM, creates its custom tables, and helpers to create
 * members and custom fields, post the two forms the way a real request does, and run the
 * addon's exporter and eraser the way WordPress does.
 */

/**
 * Thrown from the wp_redirect filter, so a redirect followed by exit can be tested.
 */
class AddonRedirectException extends \Error {

	/**
	 * @var string
	 */
	public $location;

	public function __construct( $location ) {
		parent::__construct( 'Redirected to ' . $location );
		$this->location = $location;
	}
}

abstract class AddonTestCase extends \Codeception\TestCase\WPTestCase {

	/**
	 * The password every member gets, strong enough for WPUM's password check.
	 */
	const PASSWORD = 'CurrentP@ssw0rd!123';

	/**
	 * The error shown when the password doesn't match.
	 */
	const WRONG_PASSWORD_ERROR = 'The password you entered is incorrect.';

	const EXPORT_FORM = 'personal-data';

	const ERASURE_FORM = 'data-erasure';

	/**
	 * Whether the custom fields are registered with Carbon Fields. That lasts for the whole run.
	 *
	 * @var bool
	 */
	private static $fields_registered = false;

	/**
	 * Emails sent during the test.
	 *
	 * @var array
	 */
	protected $emails = array();

	/**
	 * Files a test created, removed afterwards.
	 *
	 * @var string[]
	 */
	protected $files = array();

	public function _setUp() {
		parent::_setUp();

		// Reset the $wpum_options global: WP rolls back the DB between tests but PHP globals persist.
		global $wpum_options;
		$wpum_options = get_option( 'wpum_settings' ) ?: array();

		// Fail, not skip: a skipped suite would still pass the release gate.
		if ( ! function_exists( 'WPUM' ) ) {
			$this->fail( 'WPUM plugin is not active.' );
		}

		if ( ! class_exists( 'WPUM_Personal_Data' ) ) {
			$this->fail( 'WPUM Personal Data addon is not active.' );
		}

		$this->ensure_tables();
		$this->register_custom_fields();

		$this->emails = array();
		add_filter(
			'pre_wp_mail',
			function ( $return, $atts ) {
				$this->emails[] = $atts;

				return true;
			},
			999,
			2
		);

		$_SERVER['REQUEST_URI'] = '/account/personal-data';

		$this->reset_form_instances();
	}

	public function _tearDown() {
		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
		wp_set_current_user( 0 );

		global $post;
		$post = null;
		set_query_var( 'tab', '' );

		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore
			}
		}
		$this->files = array();

		$this->reset_form_instances();

		parent::_tearDown();
	}

	/**
	 * What differs between the two forms.
	 *
	 * @param string $form
	 *
	 * @return array
	 */
	protected function form_details( $form ) {
		$forms = array(
			self::EXPORT_FORM  => array(
				'class'        => 'WPUM_Form_Personal_Data',
				'action'       => 'export_personal_data',
				'password'     => 'password',
				'nonce'        => 'personal_data_nonce',
				'nonce_action' => 'verify_personal_data_form',
				'submit'       => 'submit_personal_data',
				'form_id'      => 'wpum-submit-personal-data-form',
				'heading'      => 'Export personal data',
				'confirmed'    => 'Data export request confirmation',
				'button'       => 'Request personal data export',
				'fields'       => 'personal_data_form_fields',
				'group'        => 'personal_data',
				'success'      => 'wpumpd_personal_data_request_success_message',
				'before'       => 'wpum_before_personal_data_form',
				'after'        => 'wpum_after_personal_data_form',
			),
			self::ERASURE_FORM => array(
				'class'        => 'WPUM_Form_Data_Erasure',
				'action'       => 'remove_personal_data',
				'password'     => 'password_erasure',
				'nonce'        => 'data_erasure_nonce',
				'nonce_action' => 'verify_data_erasure_form',
				'submit'       => 'submit_data_erasure',
				'form_id'      => 'wpum-submit-data-erasure-form',
				'heading'      => 'Personal data erasure request',
				'confirmed'    => 'Personal data erasure request confirmation',
				'button'       => 'Request personal data erasure',
				'fields'       => 'personal_data_erasure_form_fields',
				'group'        => 'personal_data_erasure',
				'success'      => 'wpumpd_personal_data_erasure_request_success_message',
				'before'       => 'wpum_before_data_erasure_form',
				'after'        => 'wpum_after_data_erasure_form',
			),
		);

		return $forms[ $form ];
	}

	/**
	 * Data provider: every test that takes $form runs on both forms.
	 *
	 * @return array
	 */
	public function forms() {
		return array(
			'export'  => array( self::EXPORT_FORM ),
			'erasure' => array( self::ERASURE_FORM ),
		);
	}

	/**
	 * Forget the form singletons, so the next form starts from scratch.
	 */
	protected function reset_form_instances() {
		foreach ( array( 'WPUM_Form_Personal_Data', 'WPUM_Form_Data_Erasure' ) as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$prop = new \ReflectionProperty( $class, '_instance' );
			$prop->setAccessible( true );
			$current = $prop->getValue();

			if ( $current ) {
				remove_action( 'wp', array( $current, 'process' ) );
			}

			$prop->setValue( null, null );
		}
	}

	/**
	 * A fresh form, the way WPUM loads it for a request.
	 *
	 * @param string $form
	 *
	 * @return \WPUM_Form
	 */
	protected function form( $form ) {
		$this->reset_form_instances();

		return call_user_func( array( $this->form_details( $form )['class'], 'instance' ) );
	}

	/**
	 * Create a member with a known password.
	 *
	 * @param string $role
	 * @param array  $args
	 *
	 * @return int
	 */
	protected function create_member( $role = 'subscriber', $args = array() ) {
		return self::factory()->user->create(
			array_merge(
				array(
					'role'      => $role,
					'user_pass' => self::PASSWORD,
				),
				$args
			)
		);
	}

	/**
	 * Create a member and log in as them.
	 *
	 * @param string $role
	 * @param array  $args
	 *
	 * @return int
	 */
	protected function login_as_member( $role = 'subscriber', $args = array() ) {
		$user_id = $this->create_member( $role, $args );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * A member's email address.
	 *
	 * @param int $user_id
	 *
	 * @return string
	 */
	protected function email_of( $user_id ) {
		return get_userdata( $user_id )->user_email;
	}

	/**
	 * What a form posts, with overrides. An override of null leaves that value out.
	 *
	 * Slashed, as WordPress slashes all request data before handlers see it.
	 *
	 * @param string $form
	 * @param array  $overrides Use 'password', 'nonce' and 'submit' for the fields that differ
	 *                          between the forms.
	 *
	 * @return array
	 */
	protected function posted_values( $form, $overrides = array() ) {
		$details = $this->form_details( $form );

		foreach ( array( 'password', 'nonce', 'submit' ) as $name ) {
			if ( array_key_exists( $name, $overrides ) ) {
				$overrides[ '__' . $name ] = $overrides[ $name ];
				unset( $overrides[ $name ] );
			}
		}

		$values = array_merge(
			array(
				'wpum_form'  => $form,
				'step'       => '0',
				'__password' => self::PASSWORD,
				'__nonce'    => wp_create_nonce( $details['nonce_action'] ),
				'__submit'   => $details['button'],
			),
			$overrides
		);

		foreach ( array( 'password', 'nonce', 'submit' ) as $name ) {
			$values[ $details[ $name ] ] = $values[ '__' . $name ];
			unset( $values[ '__' . $name ] );
		}

		return wp_slash(
			array_filter(
				$values,
				function ( $value ) {
					return null !== $value;
				}
			)
		);
	}

	/**
	 * Post a form through the handler WPUM runs for a real request.
	 *
	 * @param string $form
	 * @param array  $overrides See posted_values().
	 *
	 * @return object {
	 *     @type \WPUM_Form $form
	 *     @type array      $errors The form's error messages.
	 * }
	 */
	protected function submit( $form, $overrides = array() ) {
		$_POST    = $this->posted_values( $form, $overrides );
		$_REQUEST = $_POST;

		$instance = $this->form( $form );
		$instance->process();

		return (object) array(
			'form'   => $instance,
			'errors' => $this->get_form_errors( $instance ),
		);
	}

	/**
	 * Every privacy request on the site, read from the database.
	 *
	 * @return \WP_User_Request[]
	 */
	protected function requests() {
		$ids = get_posts(
			array(
				'post_type'      => 'user_request',
				'post_status'    => array( 'request-pending', 'request-confirmed', 'request-failed', 'request-completed' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return array_map( 'wp_get_user_request', $ids );
	}

	/**
	 * Assert no privacy request exists.
	 */
	protected function assertNoRequests() {
		$this->assertSame( array(), $this->requests(), 'No request should have been filed.' );
	}

	/**
	 * Create a request the way the form does, with the key its email carries.
	 *
	 * @param int    $user_id
	 * @param string $action
	 *
	 * @return array Request ID and key.
	 */
	protected function create_request( $user_id, $action = 'export_personal_data' ) {
		$request_id = wp_create_user_request( $this->email_of( $user_id ), $action );

		$this->assertIsInt( $request_id );

		return array( $request_id, wp_generate_user_request_key( $request_id ) );
	}

	/**
	 * Follow the link in a confirmation email to the front end.
	 *
	 * @param int    $request_id
	 * @param string $key
	 *
	 * @return string|null Where it redirected to, or null.
	 */
	protected function follow_link( $request_id, $key ) {
		$_GET     = array(
			'action'      => 'confirmaction',
			'request_id'  => (string) $request_id,
			'confirm_key' => $key,
		);
		$_REQUEST = $_GET;

		return $this->catch_redirect( 'wpum_validate_user_requests_on_frontend' );
	}

	/**
	 * Register the fields the way the Custom Fields addon does, which is what lets
	 * carbon_get_user_meta() read them. Fields are read through that addon's datastore, which
	 * stores each value in one row under its plain meta key.
	 *
	 * It's a private addon that this repo's CI can't check out, and its own registration runs
	 * once while WordPress boots, before a test has created any field. The types are the
	 * Carbon Fields ones wpumf_create_carbon_field_by_type() picks for each WPUM type, and the
	 * WPUM field rows are created per test with create_field().
	 */
	protected function register_custom_fields() {
		if ( self::$fields_registered ) {
			return;
		}
		self::$fields_registered = true;

		$options = array(
			's' => 'Small',
			'm' => 'Medium',
			'l' => 'Large',
		);

		\WPUM\Carbon_Fields\Container::make( 'user_meta', 'Personal Data test fields' )
			->set_datastore( new \WPUM_User_Meta_Custom_Datastore() )
			->add_fields(
				array(
					\WPUM\Carbon_Fields\Field::make( 'text', 'wpum_phone', 'Phone' ),
					\WPUM\Carbon_Fields\Field::make( 'text', 'wpum_age', 'Age' ),
					\WPUM\Carbon_Fields\Field::make( 'text', 'firstname', 'First name' ),
					\WPUM\Carbon_Fields\Field::make( 'checkbox', 'wpum_newsletter', 'Newsletter' ),
					\WPUM\Carbon_Fields\Field::make( 'select', 'wpum_size', 'Size' )->add_options( $options ),
					\WPUM\Carbon_Fields\Field::make( 'radio', 'wpum_fit', 'Fit' )->add_options( $options ),
					\WPUM\Carbon_Fields\Field::make( 'multiselect', 'wpum_sizes', 'Sizes' )->add_options( $options ),
					\WPUM\Carbon_Fields\Field::make( 'set', 'wpum_days', 'Days' )->add_options( $options ),
					\WPUM\Carbon_Fields\Field::make( 'multiselect', 'wpum_coaches', 'Coaches' )->add_options(
						array(
							'1' => 'One',
							'2' => 'Two',
						)
					),
					\WPUM\Carbon_Fields\Field::make( 'file', 'wpum_file_field_cv', 'CV' )->set_value_type( 'url' ),
					\WPUM\Carbon_Fields\Field::make( 'image', 'current_user_avatar', 'Avatar' )->set_value_type( 'url' ),
					\WPUM\Carbon_Fields\Field::make( 'image', 'user_cover', 'Cover' )->set_value_type( 'url' ),
				)
			);
	}

	/**
	 * Create a WPUM field. Its meta key must be one of those in register_custom_fields().
	 *
	 * @param string $name
	 * @param string $type
	 * @param string $meta_key
	 * @param array  $meta
	 *
	 * @return \WPUM_Field
	 */
	protected function create_field( $name, $type, $meta_key, $meta = array() ) {
		$group_id = WPUM()->fields_groups->insert( array( 'name' => 'Test group' ) );
		$field_id = WPUM()->fields->insert(
			array(
				'group_id' => $group_id,
				'type'     => $type,
				'name'     => $name,
			)
		);

		$field = new \WPUM_Field( $field_id );
		$field->update_meta( 'user_meta_key', $meta_key );
		foreach ( $meta as $key => $value ) {
			$field->update_meta( $key, $value );
		}

		// get_fields() caches its list, and its cache key doesn't change when a field is added.
		wp_cache_flush();

		return new \WPUM_Field( $field_id );
	}

	/**
	 * The options WPUM stores for a dropdown, radio or multiple choice field.
	 *
	 * @return array
	 */
	protected function field_options() {
		return array(
			array(
				'value' => 's',
				'label' => 'Small',
			),
			array(
				'value' => 'm',
				'label' => 'Medium',
			),
			array(
				'value' => 'l',
				'label' => 'Large',
			),
		);
	}

	/**
	 * Find the addon's entry in one of WordPress's privacy lists.
	 *
	 * @param string $filter
	 * @param string $callback
	 *
	 * @return array|null
	 */
	protected function registered( $filter, $callback ) {
		foreach ( apply_filters( $filter, array() ) as $entry ) {
			if ( $callback === $entry['callback'] ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Run the addon's exporter the way WordPress does, through the registered callback.
	 *
	 * @param string $email
	 *
	 * @return array
	 */
	protected function export( $email ) {
		$entry = $this->registered( 'wp_privacy_personal_data_exporters', 'wpumpd_export_custom_fields_user_data' );

		return call_user_func( $entry['callback'], $email, 1 );
	}

	/**
	 * What the exporter says about someone, as name => value.
	 *
	 * @param string $email
	 *
	 * @return array
	 */
	protected function exported_values( $email ) {
		$values = array();

		foreach ( $this->export( $email )['data'] as $item ) {
			foreach ( $item['data'] as $datum ) {
				$values[ $datum['name'] ] = $datum['value'];
			}
		}

		return $values;
	}

	/**
	 * Run the addon's eraser the way WordPress does, through the registered callback.
	 *
	 * @param string $email
	 *
	 * @return array
	 */
	protected function erase( $email ) {
		$entry = $this->registered( 'wp_privacy_personal_data_erasers', 'wpumpd_plugin_user_data_eraser' );

		return call_user_func( $entry['callback'], $email, 1 );
	}

	/**
	 * Get a form's errors.
	 *
	 * @param \WPUM_Form $form
	 *
	 * @return array
	 */
	protected function get_form_errors( $form ) {
		$prop = new \ReflectionProperty( 'WPUM_Form', 'errors' );
		$prop->setAccessible( true );

		return $prop->getValue( $form );
	}

	/**
	 * Call a protected or private method.
	 *
	 * @param object $object
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	protected function call_method( $object, $method, $args = array() ) {
		$ref = new \ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $object, $args );
	}

	/**
	 * Run a callback that redirects, and return where it redirected to.
	 *
	 * @param callable $callback
	 *
	 * @return string|null The redirect location, or null if it didn't redirect.
	 */
	protected function catch_redirect( $callback ) {
		$filter = function ( $location ) {
			throw new AddonRedirectException( (string) $location );
		};
		add_filter( 'wp_redirect', $filter, PHP_INT_MAX );

		try {
			call_user_func( $callback );
		} catch ( AddonRedirectException $e ) {
			return $e->location;
		} finally {
			remove_filter( 'wp_redirect', $filter, PHP_INT_MAX );
		}

		return null;
	}

	/**
	 * Render a callback's output.
	 *
	 * @param callable $callback
	 *
	 * @return string
	 */
	protected function render( $callback ) {
		ob_start();
		try {
			call_user_func( $callback );
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Render the two forms the way the account tab does.
	 *
	 * @return string
	 */
	protected function render_tab() {
		return $this->render(
			function () {
				do_action( 'wpum_account_page_content_personal-data' );
			}
		);
	}

	/**
	 * Create the account page and render it on a tab.
	 *
	 * @param string $tab
	 *
	 * @return string
	 */
	protected function render_account_page( $tab = '' ) {
		$page_id = $this->create_page( 'Account', '[wpum_account]' );
		wpum_update_option( 'account_page', array( (string) $page_id ) );

		global $post;
		$post = get_post( $page_id );
		set_query_var( 'tab', $tab );

		return do_shortcode( '[wpum_account]' );
	}

	/**
	 * Parse HTML for XPath queries.
	 *
	 * @param string $html
	 *
	 * @return \DOMXPath
	 */
	protected function xpath( $html ) {
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $doc );
	}

	/**
	 * Create a published page.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return int
	 */
	protected function create_page( $title, $content = '' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Create a file in the uploads folder, removed after the test.
	 *
	 * @param string $name
	 *
	 * @return string[] The file's path and URL.
	 */
	protected function create_upload( $name ) {
		$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['basedir'] ) . $name;

		file_put_contents( $path, 'A file a member uploaded.' ); // phpcs:ignore
		$this->files[] = $path;

		return array( $path, trailingslashit( $uploads['baseurl'] ) . $name );
	}

	/**
	 * Ensure all WPUM custom tables exist.
	 */
	protected function ensure_tables() {
		$tables = array(
			new \WPUM_DB_Table_Fields(),
			new \WPUM_DB_Table_Field_Meta(),
			new \WPUM_DB_Table_Fields_Groups(),
			new \WPUM_DB_Table_Registration_Forms(),
			new \WPUM_DB_Table_Registration_Forms_Meta(),
			new \WPUM_DB_Table_Search_Fields(),
		);

		foreach ( $tables as $table ) {
			if ( ! $table->exists() ) {
				$table->create();
			}
		}
	}
}
