<?php
/**
 * A request starts the export or erasure of someone's data, so everything here must file
 * nothing, or only ever a request for the logged-in member: a wrong password, a missing or
 * forged nonce, a tampered ID, a visitor.
 */
class SecurityTest extends AddonTestCase {

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_wrong_password_files_nothing( $form ) {
		$this->login_as_member();

		$result = $this->submit( $form, array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertNoRequests();
		$this->assertSame( array( self::WRONG_PASSWORD_ERROR ), $result->errors );
		$this->assertSame( array(), $this->emails );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_error_for_a_wrong_password_is_shown_on_the_form( $form ) {
		$this->login_as_member();

		$result = $this->submit( $form, array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertStringContainsString( self::WRONG_PASSWORD_ERROR, $this->render( array( $result->form, 'output' ) ) );
	}

	/**
	 * A password is only ever compared with a hash, not the stored hash with itself.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_stored_hash_is_not_accepted_as_the_password( $form ) {
		$user_id = $this->login_as_member();

		$this->submit( $form, array( 'password' => get_userdata( $user_id )->user_pass ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_passwords_are_case_sensitive( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'password' => strtolower( self::PASSWORD ) ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_an_empty_password_files_nothing( $form ) {
		$this->login_as_member();

		$result = $this->submit( $form, array( 'password' => '' ) );

		$this->assertNoRequests();
		$this->assertSame( array( 'Current password is a required field' ), $result->errors );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_no_password_at_all_files_nothing( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'password' => null ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_password_posted_as_an_array_files_nothing( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'password' => array( self::PASSWORD ) ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_password_of_another_member_files_nothing( $form ) {
		$this->create_member( 'subscriber', array( 'user_pass' => 'SomeoneElse!123' ) );
		$this->login_as_member();

		$this->submit( $form, array( 'password' => 'SomeoneElse!123' ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_no_nonce_files_nothing( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'nonce' => null ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider bad_nonces
	 *
	 * @param string $form
	 * @param mixed  $nonce
	 */
	public function test_a_forged_nonce_files_nothing( $form, $nonce ) {
		$this->login_as_member();

		$this->submit( $form, array( 'nonce' => $nonce ) );

		$this->assertNoRequests();
	}

	public function bad_nonces() {
		$nonces = array(
			'empty'        => '',
			'made up'      => 'abcdef1234',
			'zero'         => '0',
			'an array'     => array( 'abcdef1234' ),
			'one too long' => str_repeat( 'a', 200 ),
		);

		$cases = array();
		foreach ( $this->forms() as $name => $form ) {
			foreach ( $nonces as $label => $nonce ) {
				$cases[ $name . ', ' . $label ] = array( $form[0], $nonce );
			}
		}

		return $cases;
	}

	/**
	 * The two forms' nonces aren't interchangeable, and a page's other nonces aren't either.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_nonce_for_another_action_files_nothing( $form ) {
		$this->login_as_member();

		$other = self::EXPORT_FORM === $form ? self::ERASURE_FORM : self::EXPORT_FORM;

		$this->submit( $form, array( 'nonce' => wp_create_nonce( $this->form_details( $other )['nonce_action'] ) ) );
		$this->submit( $form, array( 'nonce' => wp_create_nonce( 'verify_password_form' ) ) );

		$this->assertNoRequests();
	}

	/**
	 * The classic CSRF: an attacker gets a nonce from their own account's form and tricks
	 * the victim into posting it.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_nonce_issued_to_another_member_files_nothing( $form ) {
		$this->login_as_member();
		$nonce = wp_create_nonce( $this->form_details( $form )['nonce_action'] );

		$this->login_as_member();

		$this->submit( $form, array( 'nonce' => $nonce ) );

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_without_the_submit_button_nothing_is_filed( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'submit' => null ) );

		$this->assertNoRequests();
	}

	/**
	 * Only the first step has a handler.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_later_step_files_nothing( $form ) {
		$this->login_as_member();

		$this->submit( $form, array( 'step' => '1' ) );
		$this->submit( $form, array( 'step' => '2' ) );

		$this->assertNoRequests();
	}

	/**
	 * The handler reads $_POST only, so a link can never start a request.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_get_request_files_nothing( $form ) {
		$this->login_as_member();

		$_GET     = $this->posted_values( $form );
		$_REQUEST = $_GET;
		$_POST    = array();

		$this->form( $form )->process();

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider identity_fields
	 *
	 * @param string $form
	 * @param string $field
	 */
	public function test_a_tampered_identity_cannot_file_a_request_for_someone_else( $form, $field ) {
		$victim   = $this->create_member();
		$attacker = $this->login_as_member();

		$value = false !== strpos( $field, 'email' ) ? $this->email_of( $victim ) : (string) $victim;

		$this->submit( $form, array( $field => $value ) );

		$requests = $this->requests();

		$this->assertCount( 1, $requests, 'The attacker gave their own password, so their own request goes through.' );
		$this->assertSame( $this->email_of( $attacker ), $requests[0]->email );
		$this->assertSame( $attacker, (int) $requests[0]->user_id );
		$this->assertSame( array( $this->email_of( $attacker ) ), wp_list_pluck( $this->emails, 'to' ) );
	}

	public function identity_fields() {
		$cases = array();
		foreach ( $this->forms() as $name => $form ) {
			foreach ( array( 'user_id', 'ID', 'id', 'user', 'uid', 'author', 'member_id', 'email', 'user_email', 'email_address', 'username_or_email' ) as $field ) {
				$cases[ $name . ', ' . $field ] = array( $form[0], $field );
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_tampered_id_and_a_wrong_password_file_nothing( $form ) {
		$victim = $this->create_member();
		$this->login_as_member();

		$this->submit(
			$form,
			array(
				'user_id'  => (string) $victim,
				'password' => 'NotMyP@ssw0rd!',
			)
		);

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_victims_password_in_the_attackers_session_files_nothing( $form ) {
		$victim = $this->create_member( 'subscriber', array( 'user_pass' => 'VictimP@ssw0rd!' ) );
		$this->login_as_member();

		$this->submit(
			$form,
			array(
				'user_id'  => (string) $victim,
				'password' => 'VictimP@ssw0rd!',
			)
		);

		$this->assertNoRequests();
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_an_administrator_cannot_file_a_request_for_someone_else( $form ) {
		$victim = $this->create_member();
		$admin  = $this->login_as_member( 'administrator' );

		$this->submit( $form, array( 'user_id' => (string) $victim ) );

		$this->assertSame( array( $this->email_of( $admin ) ), wp_list_pluck( $this->requests(), 'email' ) );
	}

	/**
	 * Visitors never see these forms, but a request could be forged. The test's error handler
	 * turns the PHP error from reading a hash user 0 doesn't have into a form error.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_visitor_with_a_valid_nonce_files_nothing( $form ) {
		$this->create_member();
		wp_set_current_user( 0 );

		$this->submit( $form );

		$this->assertNoRequests();
		$this->assertSame( array(), $this->emails );
	}
}
