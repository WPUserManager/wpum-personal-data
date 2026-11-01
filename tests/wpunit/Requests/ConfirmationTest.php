<?php
/**
 * With wp-login.php locked, the link in the confirmation email comes to WPUM's login page and
 * the addon confirms the request there. WordPress handles it otherwise.
 */
class ConfirmationTest extends AddonTestCase {

	public function _setUp() {
		parent::_setUp();

		$account_id = $this->create_page( 'Account', '[wpum_account]' );
		wpum_update_option( 'account_page', array( (string) $account_id ) );
	}

	/**
	 * Lock wp-login.php.
	 */
	private function lock_wp_login() {
		wpum_update_option( 'lock_wplogin', true );
	}

	/**
	 * A request's status, read from the database.
	 *
	 * @param int $request_id
	 *
	 * @return string
	 */
	private function status( $request_id ) {
		clean_post_cache( $request_id );

		return wp_get_user_request( $request_id )->status;
	}

	/**
	 * @dataProvider actions
	 *
	 * @param string $action
	 */
	public function test_the_link_confirms_the_request( $action ) {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member, $action );

		$redirect = $this->follow_link( $request_id, $key );

		$this->assertSame( 'request-confirmed', $this->status( $request_id ) );
		$this->assertStringContainsString( 'personal-data', (string) $redirect );
		$this->assertStringContainsString( 'user_action=confirmed', (string) $redirect );
	}

	public function actions() {
		return array(
			'export'  => array( 'export_personal_data' ),
			'erasure' => array( 'remove_personal_data' ),
		);
	}

	public function test_the_administrator_is_told_once_it_is_confirmed() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$this->assertSame( array(), $this->emails );

		$this->follow_link( $request_id, $key );

		$this->assertSame( array( get_site_option( 'admin_email' ) ), wp_list_pluck( $this->emails, 'to' ) );
	}

	/**
	 * The link in the mail the form sends is the one that works, from start to finish.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_link_in_the_email_works( $form ) {
		$this->lock_wp_login();
		$this->login_as_member();

		$this->submit( $form );

		$this->assertSame( 1, preg_match( '/confirm_key=([A-Za-z0-9]+)/', $this->emails[0]['message'], $matches ) );

		$request_id = $this->requests()[0]->ID;

		$this->follow_link( $request_id, $matches[1] );

		$this->assertSame( 'request-confirmed', $this->status( $request_id ) );
	}

	public function test_a_wrong_key_confirms_nothing() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id ) = $this->create_request( $member );

		$redirect = $this->follow_link( $request_id, 'NotTheKey1234567890' );

		$this->assertNull( $redirect );
		$this->assertSame( 'request-pending', $this->status( $request_id ) );
		$this->assertSame( array(), $this->emails );
	}

	public function test_an_empty_key_confirms_nothing() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id ) = $this->create_request( $member );

		$this->assertNull( $this->follow_link( $request_id, '' ) );
		$this->assertSame( 'request-pending', $this->status( $request_id ) );
	}

	/**
	 * Holding your own link gets you nowhere with someone else's request.
	 */
	public function test_one_members_key_does_not_confirm_anothers_request() {
		$this->lock_wp_login();

		list( , $attackers_key )    = $this->create_request( $this->create_member() );
		list( $victims_request_id ) = $this->create_request( $this->create_member() );

		$this->assertNull( $this->follow_link( $victims_request_id, $attackers_key ) );
		$this->assertSame( 'request-pending', $this->status( $victims_request_id ) );
	}

	public function test_a_link_works_once() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$this->follow_link( $request_id, $key );
		$this->emails = array();

		$this->assertNull( $this->follow_link( $request_id, $key ) );
		$this->assertSame( array(), $this->emails );
	}

	public function test_an_expired_link_confirms_nothing() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		add_filter(
			'user_request_key_expiration',
			function () {
				return -60;
			}
		);

		$this->assertNull( $this->follow_link( $request_id, $key ) );
		$this->assertSame( 'request-pending', $this->status( $request_id ) );
	}

	/**
	 * WordPress handles the link on wp-login.php when it isn't locked.
	 */
	public function test_it_leaves_the_link_alone_when_wp_login_is_not_locked() {
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$this->assertNull( $this->follow_link( $request_id, $key ) );
		$this->assertSame( 'request-pending', $this->status( $request_id ) );
	}

	public function test_it_needs_the_confirm_action() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$_GET = array(
			'action'      => 'login',
			'request_id'  => (string) $request_id,
			'confirm_key' => $key,
		);

		$this->assertNull( $this->catch_redirect( 'wpum_validate_user_requests_on_frontend' ) );
		$this->assertSame( 'request-pending', $this->status( $request_id ) );
	}

	/**
	 * Confirming only marks the request. An administrator runs the erasure.
	 */
	public function test_confirming_an_erasure_erases_nothing() {
		$this->lock_wp_login();
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		list( $request_id, $key ) = $this->create_request( $member, 'remove_personal_data' );

		$this->follow_link( $request_id, $key );

		$this->assertSame( '01234 567890', get_user_meta( $member, 'wpum_phone', true ) );
	}

	/**
	 * The lock on the whole site also sends wp_login_url() to WPUM's login page, so that's
	 * where the link goes, but the addon only looks at the lock on wp-login.php. The link
	 * does nothing and the request can't be confirmed.
	 *
	 * @group known-bug
	 */
	public function test_the_link_works_when_the_whole_site_is_locked() {
		wpum_update_option( 'lock_complete_site', true );
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$this->follow_link( $request_id, $key );

		$this->assertSame( 'request-confirmed', $this->status( $request_id ) );
	}

	/**
	 * wp-login.php fires user_request_action_confirmed, and WordPress's own two steps hang off
	 * it. The addon calls those two itself, so anything else on that action never hears.
	 *
	 * @group known-bug
	 */
	public function test_other_plugins_hear_about_the_confirmation() {
		$this->lock_wp_login();
		$member = $this->create_member();

		list( $request_id, $key ) = $this->create_request( $member );

		$heard = null;
		add_action(
			'user_request_action_confirmed',
			function ( $id ) use ( &$heard ) {
				$heard = $id;
			},
			5
		);

		$this->follow_link( $request_id, $key );

		$this->assertSame( $request_id, (int) $heard );
	}
}
