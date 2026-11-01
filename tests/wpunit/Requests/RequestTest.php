<?php
/**
 * A member with the right password and a valid nonce files a privacy request for themselves.
 * WordPress keeps it, and emails them a link to confirm it.
 */
class RequestTest extends AddonTestCase {

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_right_password_and_nonce_file_a_request( $form ) {
		$user_id = $this->login_as_member();

		$result = $this->submit( $form );

		$this->assertSame( array(), $result->errors );

		$requests = $this->requests();

		$this->assertCount( 1, $requests );
		$this->assertSame( $this->form_details( $form )['action'], $requests[0]->action_name );
		$this->assertSame( $this->email_of( $user_id ), $requests[0]->email );
		$this->assertSame( $user_id, (int) $requests[0]->user_id );
		$this->assertSame( 'request-pending', $requests[0]->status );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_member_is_emailed_a_link_to_confirm_it( $form ) {
		$user_id = $this->login_as_member();

		$this->submit( $form );

		$this->assertCount( 1, $this->emails );
		$this->assertSame( $this->email_of( $user_id ), $this->emails[0]['to'] );
		$this->assertStringContainsString( 'action=confirmaction', $this->emails[0]['message'] );
		$this->assertStringContainsString( 'request_id=' . $this->requests()[0]->ID, $this->emails[0]['message'] );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_confirmation_step_names_the_members_email( $form ) {
		$user_id = $this->login_as_member();

		$result = $this->submit( $form );

		$html = $this->render( array( $result->form, 'output' ) );

		$this->assertStringContainsString( 'A confirmation email has been sent to <strong>' . $this->email_of( $user_id ) . '</strong>', $html );
		$this->assertStringContainsString( '<h2>' . $this->form_details( $form )['confirmed'] . '</h2>', $html );
		$this->assertStringContainsString( 'wpum-message success', $html );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_success_message_can_be_filtered( $form ) {
		$this->login_as_member();

		add_filter(
			$this->form_details( $form )['success'],
			function () {
				return 'We will be in touch.';
			}
		);

		$result = $this->submit( $form );

		$this->assertStringContainsString( 'We will be in touch.', $this->render( array( $result->form, 'output' ) ) );
	}

	public function test_the_other_form_still_shows_after_a_request() {
		$this->login_as_member();

		$this->submit( self::EXPORT_FORM );

		$xpath = $this->xpath( $this->render_tab() );

		$this->assertSame( 0, $xpath->query( '//form[@id="wpum-submit-personal-data-form"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form[@id="wpum-submit-data-erasure-form"]' )->length );
	}

	public function test_a_member_can_ask_for_an_export_and_an_erasure() {
		$this->login_as_member();

		$this->submit( self::EXPORT_FORM );
		$this->submit( self::ERASURE_FORM );

		$actions = wp_list_pluck( $this->requests(), 'action_name' );
		sort( $actions );

		$this->assertSame( array( 'export_personal_data', 'remove_personal_data' ), $actions );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_second_request_is_refused_while_the_first_is_open( $form ) {
		$this->login_as_member();

		$this->submit( $form );
		$result = $this->submit( $form );

		$this->assertSame( array( 'An incomplete personal data request for this email address already exists.' ), $result->errors );
		$this->assertCount( 1, $this->requests() );
		$this->assertCount( 1, $this->emails );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_password_with_quotes_and_a_backslash_works_as_it_does_at_login( $form ) {
		$password = 'It\'s "my" pass\\word1!';
		$this->login_as_member( 'subscriber', array( 'user_pass' => wp_slash( $password ) ) );

		$this->submit( $form, array( 'password' => $password ) );

		$this->assertCount( 1, $this->requests() );
	}

	/**
	 * Old accounts keep their phpass hash until the next login.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_password_hashed_by_an_older_wordpress_is_accepted( $form ) {
		global $wpdb;

		$user_id = $this->login_as_member();

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wpdb->update( $wpdb->users, array( 'user_pass' => ( new PasswordHash( 8, true ) )->HashPassword( self::PASSWORD ) ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->submit( $form );

		$this->assertCount( 1, $this->requests() );
	}

	/**
	 * wp_send_user_request() returns an error when the mail can't be sent. The form doesn't
	 * look at it, so the member is told an email is on its way, and the open request refuses
	 * their next try.
	 *
	 * @group known-bug
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_mail_failure_is_reported_to_the_member( $form ) {
		$this->login_as_member();
		add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );

		$result = $this->submit( $form );

		$this->assertNotEmpty( $result->errors );
	}

	/**
	 * The confirmation step only checks the step number, so a link to ?step=1 says an email
	 * was sent when nothing was filed. Both forms read the same parameter.
	 *
	 * @group known-bug
	 */
	public function test_the_confirmation_step_needs_a_request() {
		$this->login_as_member();
		$_GET['step'] = '1';

		$this->assertStringNotContainsString( 'A confirmation email has been sent', $this->render_tab() );
	}

	/**
	 * @dataProvider forms
	 * @group known-bug
	 *
	 * @param string $form
	 */
	public function test_the_password_field_comes_back_blank( $form ) {
		$this->login_as_member();

		$result = $this->submit( $form, array( 'password' => 'CurrentP@ssw0rd!124' ) );

		$this->assertStringNotContainsString( 'CurrentP@ssw0rd!124', $this->render( array( $result->form, 'output' ) ) );
	}

	/**
	 * @dataProvider forms
	 * @group known-bug
	 *
	 * @param string $form
	 */
	public function test_the_password_field_comes_back_blank_after_a_refused_request( $form ) {
		$this->login_as_member();

		$this->submit( $form );
		$result = $this->submit( $form );

		$this->assertStringNotContainsString( self::PASSWORD, $this->render( array( $result->form, 'output' ) ) );
	}
}
