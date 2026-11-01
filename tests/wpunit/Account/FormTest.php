<?php
/**
 * The markup of the two forms and what they post.
 */
class FormTest extends AddonTestCase {

	/**
	 * The tab, for a logged-in member.
	 *
	 * @return \DOMXPath
	 */
	private function rendered_tab() {
		$this->login_as_member();

		return $this->xpath( $this->render_tab() );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_form_has_the_addons_name( $form ) {
		$this->assertSame( $form, $this->form( $form )->get_form_name() );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_it_has_a_submit_step_and_a_confirmation_step( $form ) {
		$details = $this->form_details( $form );
		$steps   = $this->form( $form )->get_steps();

		$this->assertSame( array( 'submit', 'submit_confirmed' ), array_keys( $steps ) );
		$this->assertSame( $details['heading'], $steps['submit']['name'] );
		$this->assertSame( $details['confirmed'], $steps['submit_confirmed']['name'] );
		$this->assertFalse( $steps['submit_confirmed']['handler'], 'Only the first step may file a request.' );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_form_asks_for_the_current_password( $form ) {
		$details = $this->form_details( $form );
		$xpath   = $this->rendered_tab();

		$input = $xpath->query( '//form[@id="' . $details['form_id'] . '"]//input[@name="' . $details['password'] . '"]' )->item( 0 );

		$this->assertNotNull( $input );
		$this->assertSame( 'password', $input->getAttribute( 'type' ) );
		$this->assertTrue( $input->hasAttribute( 'required' ) );
		$this->assertSame( '', $input->getAttribute( 'value' ) );
		$this->assertStringContainsString( 'Current password', $xpath->query( '//label[@for="' . $details['password'] . '"]' )->item( 0 )->textContent );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_form_posts( $form ) {
		$details = $this->form_details( $form );
		$element = $this->rendered_tab()->query( '//form[@id="' . $details['form_id'] . '"]' )->item( 0 );

		$this->assertSame( 'post', $element->getAttribute( 'method' ) );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_heading_and_the_button_name_the_form( $form ) {
		$details = $this->form_details( $form );
		$xpath   = $this->rendered_tab();

		$this->assertSame( 1, $xpath->query( '//h2[text()="' . $details['heading'] . '"]' )->length );
		$this->assertSame( $details['button'], $xpath->query( '//input[@name="' . $details['submit'] . '"]' )->item( 0 )->getAttribute( 'value' ) );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_nonce_is_valid_for_the_current_member( $form ) {
		$details = $this->form_details( $form );
		$nonce   = $this->rendered_tab()->query( '//input[@name="' . $details['nonce'] . '"]' )->item( 0 )->getAttribute( 'value' );

		$this->assertNotFalse( wp_verify_nonce( $nonce, $details['nonce_action'] ) );
	}

	/**
	 * The form must never name a user: the handler only ever acts on the current one.
	 *
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_form_posts_nothing_that_identifies_a_user( $form ) {
		$details = $this->form_details( $form );

		$names = array();
		foreach ( $this->rendered_tab()->query( '//form[@id="' . $details['form_id'] . '"]//*[@name]' ) as $element ) {
			$names[] = $element->getAttribute( 'name' );
		}
		sort( $names );

		$expected = array( '_wp_http_referer', $details['nonce'], $details['password'], 'step', $details['submit'], 'wpum_form' );
		sort( $expected );

		$this->assertSame( $expected, $names );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_hooks_around_the_form_fire( $form ) {
		$details = $this->form_details( $form );

		add_action(
			$details['before'],
			function () {
				echo '<i id="before-form"></i>';
			}
		);
		add_action(
			$details['after'],
			function () {
				echo '<i id="after-form"></i>';
			}
		);

		$xpath = $this->rendered_tab();

		$this->assertSame( 1, $xpath->query( '//i[@id="before-form"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form//i[@id="after-form"]' )->length );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_fields_can_be_extended_and_a_required_one_is_enforced( $form ) {
		$details = $this->form_details( $form );
		$this->login_as_member();

		add_filter(
			$details['fields'],
			function ( $fields ) use ( $details ) {
				$fields[ $details['group'] ]['reason'] = array(
					'label'    => 'Why are you asking?',
					'type'     => 'text',
					'required' => true,
					'priority' => 3,
				);

				return $fields;
			}
		);

		$this->assertSame( 1, $this->xpath( $this->render_tab() )->query( '//input[@name="reason"]' )->length );

		$result = $this->submit( $form );

		$this->assertNoRequests();
		$this->assertSame( array( 'Why are you asking? is a required field' ), $result->errors );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_a_translated_label_is_escaped( $form ) {
		$details = $this->form_details( $form );
		$this->login_as_member();

		add_filter(
			'gettext_wpum-personal-data',
			function ( $translation, $text ) use ( $details ) {
				return $details['heading'] === $text || $details['button'] === $text ? '<b>Translated</b>' : $translation;
			},
			10,
			2
		);

		$this->assertStringNotContainsString( '<b>Translated</b>', $this->render_tab() );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_the_form_runs_on_the_wp_hook( $form ) {
		$instance = $this->form( $form );

		$this->assertSame( 10, has_action( 'wp', array( $instance, 'process' ) ) );
	}

	/**
	 * @dataProvider forms
	 *
	 * @param string $form
	 */
	public function test_wpum_loads_the_form_when_it_is_posted( $form ) {
		$this->login_as_member();
		$this->reset_form_instances();

		$instance = WPUM()->forms->load_posted_form( $form );

		$this->assertInstanceOf( $this->form_details( $form )['class'], $instance );
	}
}
