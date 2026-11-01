<?php
/**
 * The Personal Data tab on WPUM's account page.
 */
class AccountTabTest extends AddonTestCase {

	public function test_the_tab_is_registered() {
		$this->login_as_member();

		$tabs = wpum_get_account_page_tabs();

		$this->assertArrayHasKey( 'personal-data', $tabs );
		$this->assertSame( 'Personal Data', $tabs['personal-data']['name'] );
		$this->assertSame( 3, $tabs['personal-data']['priority'] );
	}

	public function test_the_tab_comes_after_settings_and_keeps_the_core_tabs() {
		$this->login_as_member();

		$keys = array_keys( wpum_get_account_page_tabs() );

		$this->assertSame( array( 'settings', 'personal-data' ), array_slice( $keys, 0, 2 ) );
		$this->assertContains( 'password', $keys );
		$this->assertContains( 'logout', $keys );
	}

	public function test_a_logged_in_member_sees_the_tab_link() {
		$this->login_as_member();

		$xpath = $this->xpath( $this->render_account_page() );

		$this->assertSame( 1, $xpath->query( '//nav//li[contains(@class, "tab-personal-data")]//a' )->length );
	}

	public function test_a_logged_in_member_sees_both_forms_on_the_tab() {
		$this->login_as_member();

		$xpath = $this->xpath( $this->render_account_page( 'personal-data' ) );

		$this->assertSame( 1, $xpath->query( '//form[@id="wpum-submit-personal-data-form"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form[@id="wpum-submit-data-erasure-form"]' )->length );
	}

	public function test_the_forms_are_not_on_the_other_tabs() {
		$this->login_as_member();

		$html = $this->render_account_page( 'password' );

		$this->assertStringNotContainsString( 'wpum-submit-personal-data-form', $html );
		$this->assertStringNotContainsString( 'wpum-submit-data-erasure-form', $html );
	}

	/**
	 * What keeps the tab from visitors: WPUM sends them from the account page to the login page.
	 */
	public function test_visitors_are_sent_from_the_account_page_to_the_login_page() {
		$account_id = $this->create_page( 'Account', '[wpum_account]' );
		$login_id   = $this->create_page( 'Login', '[wpum_login_form]' );
		wpum_update_option( 'account_page', array( (string) $account_id ) );
		wpum_update_option( 'login_page', array( (string) $login_id ) );

		$this->go_to( get_permalink( $account_id ) );
		wp_set_current_user( 0 );

		$redirect = $this->catch_redirect( 'wpum_restrict_account_page' );

		$this->assertStringStartsWith( get_permalink( $login_id ), (string) $redirect );
	}

	/**
	 * And the forms keep themselves from visitors, wherever [wpum_account] is.
	 */
	public function test_visitors_never_get_either_form() {
		wp_set_current_user( 0 );

		$this->assertStringNotContainsString( '<form', $this->render_tab() );
	}

	public function test_the_request_confirmed_message_shows_after_following_the_link() {
		$this->login_as_member();
		$_GET['user_action'] = 'confirmed';

		$this->assertStringContainsString( 'Your request has been successfully confirmed.', $this->render_tab() );
	}

	/**
	 * @dataProvider other_user_actions
	 *
	 * @param array $get
	 */
	public function test_the_request_confirmed_message_needs_the_right_value( $get ) {
		$this->login_as_member();
		$_GET = $get;

		$this->assertStringNotContainsString( 'Your request has been successfully confirmed.', $this->render_tab() );
	}

	public function other_user_actions() {
		return array(
			'none'    => array( array() ),
			'another' => array( array( 'user_action' => 'declined' ) ),
		);
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_wpum_loads_the_addons_form_file( $form ) {
		$this->assertSame(
			WPUMPD_PLUGIN_DIR . 'includes/class-wpum-form-' . $form . '.php',
			apply_filters( 'wpum_load_form_path', '/core/form.php', $form )
		);
	}

	public function test_other_forms_keep_their_file() {
		$this->assertSame( '/core/login.php', apply_filters( 'wpum_load_form_path', '/core/login.php', 'login' ) );
	}

	public function test_the_addons_templates_are_on_wpums_template_path() {
		$paths = apply_filters( 'wpum_template_paths', array( 10 => '/theme/' ) );

		$this->assertSame( WPUMPD_PLUGIN_DIR . 'templates/', $paths[13] );
		$this->assertSame( '/theme/', $paths[10] );
	}
}
