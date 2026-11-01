<?php
/**
 * The eraser removes WPUM's custom fields when an administrator runs a confirmed erasure
 * request in WordPress. It has to remove the right values, and nothing else.
 */
class EraserTest extends AddonTestCase {

	/**
	 * What the eraser returns when it has nothing to do.
	 *
	 * @return array
	 */
	private function nothing_to_erase() {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public function test_the_eraser_is_registered_and_keeps_the_others() {
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array( 'other' => array( 'callback' => 'other' ) ) );

		$this->assertArrayHasKey( 'other', $erasers );
		$this->assertSame( 'Additional account details', $this->registered( 'wp_privacy_personal_data_erasers', 'wpumpd_plugin_user_data_eraser' )['eraser_friendly_name'] );
	}

	public function test_an_empty_email_erases_nothing() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$this->assertSame( $this->nothing_to_erase(), $this->erase( '' ) );
		$this->assertSame( '01234 567890', get_user_meta( $member, 'wpum_phone', true ) );
	}

	public function test_an_unknown_email_erases_nothing() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$this->assertSame( $this->nothing_to_erase(), $this->erase( 'nobody@example.com' ) );
		$this->assertSame( '01234 567890', get_user_meta( $member, 'wpum_phone', true ) );
	}

	public function test_a_custom_field_is_erased() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertSame(
			array(
				'items_removed'  => true,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			),
			$result
		);
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_phone' ) );
	}

	/**
	 * @dataProvider stored_values
	 *
	 * @param string $name
	 * @param string $type
	 * @param string $key
	 * @param mixed  $value
	 */
	public function test_every_kind_of_field_is_erased( $name, $type, $key, $value ) {
		$this->create_field( $name, $type, $key, array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, $key, $value );

		$this->assertTrue( metadata_exists( 'user', $member, $key ) );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( metadata_exists( 'user', $member, $key ) );
	}

	public function stored_values() {
		return array(
			'checkbox'      => array( 'Newsletter', 'checkbox', 'wpum_newsletter', true ),
			'dropdown'      => array( 'Size', 'dropdown', 'wpum_size', 'm' ),
			'radio'         => array( 'Fit', 'radio', 'wpum_fit', 'l' ),
			'multiselect'   => array( 'Sizes', 'multiselect', 'wpum_sizes', array( 's', 'l' ) ),
			'multicheckbox' => array( 'Days', 'multicheckbox', 'wpum_days', array( 's', 'm' ) ),
		);
	}

	/**
	 * What the addon must never do: touch anyone else's values.
	 */
	public function test_other_members_keep_their_values() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$member = $this->create_member();
		$other  = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', 'Mine' );
		carbon_set_user_meta( $other, 'wpum_phone', 'Not mine' );

		$this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_phone' ) );
		$this->assertSame( 'Not mine', get_user_meta( $other, 'wpum_phone', true ) );
	}

	/**
	 * The eraser runs for the administrator in wp-admin, so it must go by the email.
	 */
	public function test_it_goes_by_the_email_and_not_the_logged_in_user() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', 'The member' );

		$admin = $this->login_as_member( 'administrator' );
		carbon_set_user_meta( $admin, 'wpum_phone', 'The administrator' );

		$this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_phone' ) );
		$this->assertSame( 'The administrator', get_user_meta( $admin, 'wpum_phone', true ) );
	}

	/**
	 * Erasing someone's data isn't deleting their account.
	 */
	public function test_the_account_and_its_login_stay() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member( 'author' );
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$this->erase( $this->email_of( $member ) );

		wp_cache_flush();
		$user = get_userdata( $member );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( array( 'author' ), array_values( $user->roles ) );
		$this->assertTrue( wp_check_password( self::PASSWORD, $user->user_pass, $user->ID ) );
	}

	public function test_meta_that_is_not_a_custom_field_stays() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );
		update_user_meta( $member, 'first_name', 'Ada' );
		update_user_meta( $member, 'description', 'Mathematician' );
		update_user_meta( $member, 'some_plugins_setting', 'Keep me' );
		update_user_meta( $member, 'wpum_some_other_value', 'Keep me too' );

		$this->erase( $this->email_of( $member ) );

		$this->assertSame( 'Ada', get_user_meta( $member, 'first_name', true ) );
		$this->assertSame( 'Mathematician', get_user_meta( $member, 'description', true ) );
		$this->assertSame( 'Keep me', get_user_meta( $member, 'some_plugins_setting', true ) );
		$this->assertSame( 'Keep me too', get_user_meta( $member, 'wpum_some_other_value', true ) );
	}

	/**
	 * Primary fields are the account itself, which an erasure leaves alone.
	 */
	public function test_primary_fields_stay() {
		$this->create_field( 'First name', 'user_firstname', 'firstname' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'firstname', 'Ada' );

		$this->assertSame( $this->nothing_to_erase(), $this->erase( $this->email_of( $member ) ) );
		$this->assertSame( 'Ada', get_user_meta( $member, 'firstname', true ) );
	}

	public function test_a_member_with_nothing_stored_has_nothing_erased() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();

		$this->assertSame( $this->nothing_to_erase(), $this->erase( $this->email_of( $member ) ) );
	}

	public function test_a_value_that_can_not_be_removed_is_reported() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		add_filter(
			'delete_user_metadata',
			function ( $check, $object_id, $meta_key ) {
				return 'wpum_phone' === $meta_key ? false : $check;
			},
			10,
			3
		);

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertStringContainsString( 'Phone', $result['messages'][0] );
		$this->assertStringContainsString( 'was unable to be removed', $result['messages'][0] );
	}

	public function test_an_erased_value_is_gone_from_the_next_export() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$this->erase( $this->email_of( $member ) );

		$this->assertSame( array(), $this->exported_values( $this->email_of( $member ) ) );
	}

	/**
	 * Carbon Fields answers with the first option for a select that has no value, so the
	 * eraser tries to remove a value that isn't there, delete_user_meta() says it failed,
	 * and a clean erasure is reported as incomplete.
	 *
	 * @group known-bug
	 */
	public function test_a_dropdown_the_member_left_empty_is_not_reported_as_kept() {
		$this->create_field( 'Size', 'dropdown', 'wpum_size', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();

		$this->assertSame( $this->nothing_to_erase(), $this->erase( $this->email_of( $member ) ) );
	}

	/**
	 * @group known-bug
	 */
	public function test_a_value_of_zero_is_erased() {
		$this->create_field( 'Age', 'text', 'wpum_age' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_age', '0' );

		$this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_age' ) );
	}

	/**
	 * @group known-bug
	 */
	public function test_a_file_field_is_erased_with_its_file() {
		$this->create_field( 'CV', 'file', 'wpum_file_field_cv' );
		$member = $this->create_member();

		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-cv.pdf' );
		update_user_meta( $member, 'wpum_file_field_cv', $url );
		update_user_meta( $member, 'wpum_file_field_cv_path', $path );

		$this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv' ) );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * @group known-bug
	 */
	public function test_the_profile_picture_and_cover_are_erased_with_their_files() {
		$member = $this->create_member();

		list( $avatar_path, $avatar_url ) = $this->create_upload( 'wpum-personal-data-test-avatar.png' );
		list( $cover_path, $cover_url )   = $this->create_upload( 'wpum-personal-data-test-cover.png' );

		update_user_meta( $member, 'current_user_avatar', $avatar_url );
		update_user_meta( $member, '_current_user_avatar_path', $avatar_path );
		update_user_meta( $member, 'user_cover', $cover_url );
		update_user_meta( $member, '_user_cover_path', $cover_path );

		$this->assertTrue( $this->erase( $this->email_of( $member ) )['items_removed'] );

		foreach ( array( 'current_user_avatar', '_current_user_avatar_path', 'user_cover', '_user_cover_path' ) as $key ) {
			$this->assertFalse( metadata_exists( 'user', $member, $key ), $key );
		}

		$this->assertFileDoesNotExist( $avatar_path );
		$this->assertFileDoesNotExist( $cover_path );
	}
}
