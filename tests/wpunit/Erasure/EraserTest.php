<?php
/**
 * The eraser removes WPUM's custom fields, the profile picture and cover, and the files uploaded
 * to them, when an administrator runs a confirmed erasure request in WordPress. It has to remove
 * the right data, nothing else, and say what it kept.
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
	 * The eraser goes by what's stored. Carbon Fields answers with the first option for a
	 * select that has no value, which must not count as a value to remove.
	 */
	public function test_a_dropdown_the_member_left_empty_is_not_reported_as_kept() {
		$this->create_field( 'Size', 'dropdown', 'wpum_size', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();

		$this->assertSame( $this->nothing_to_erase(), $this->erase( $this->email_of( $member ) ) );
	}

	public function test_a_value_of_zero_is_erased() {
		$this->create_field( 'Age', 'text', 'wpum_age' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_age', '0' );

		$this->assertTrue( $this->erase( $this->email_of( $member ) )['items_removed'] );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_age' ) );
	}

	/**
	 * Nothing about the member was in it, so there's nothing to report.
	 */
	public function test_an_empty_stored_value_is_removed_quietly() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		update_user_meta( $member, 'wpum_phone', '' );

		$this->assertSame( $this->nothing_to_erase(), $this->erase( $this->email_of( $member ) ) );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_phone' ) );
	}

	/**
	 * Fields are registered with Carbon Fields by the Custom Fields addon, and the value is
	 * erased whether that addon is active or not.
	 */
	public function test_a_field_carbon_fields_does_not_know_is_erased() {
		$this->create_field( 'Nickname', 'text', 'wpum_not_registered' );
		$member = $this->create_member();
		update_user_meta( $member, 'wpum_not_registered', 'Ada' );

		$this->assertTrue( $this->erase( $this->email_of( $member ) )['items_removed'] );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_not_registered' ) );
	}

	/**
	 * A member with a file in their CV field.
	 *
	 * @param string $path
	 * @param string $url
	 *
	 * @return int
	 */
	private function member_with_a_file( $path, $url ) {
		$this->create_field( 'CV', 'file', 'wpum_file_field_cv' );

		$member = $this->create_member();
		update_user_meta( $member, 'wpum_file_field_cv', $url );
		update_user_meta( $member, 'wpum_file_field_cv_path', $path );

		return $member;
	}

	/**
	 * Assert the file stayed on disk, the value went, and the erasure says so.
	 *
	 * @param array  $result
	 * @param string $path
	 * @param string $name The name in the message.
	 */
	private function assertFileKeptAndReported( $result, $path, $name = 'CV' ) {
		$this->assertFileExists( $path );
		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertStringContainsString( $name, $result['messages'][0] );
		$this->assertStringContainsString( 'file was kept', $result['messages'][0] );
	}

	public function test_a_file_field_is_erased_with_its_file() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-cv.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv' ) );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ) );
		$this->assertFileDoesNotExist( $path );
		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertSame( array(), $result['messages'] );
	}

	/**
	 * Uploads go into a dated folder of their own, with whatever the file was called.
	 */
	public function test_a_file_in_a_subfolder_is_erased() {
		list( $path, $url ) = $this->create_upload( 'wp-user-manager-uploads/2026/11/my_cv 100%.pdf' );

		$member = $this->member_with_a_file( $path, str_replace( array( ' ', '%.' ), array( '%20', '%25.' ), $url ) );
		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( $result['items_retained'] );
	}

	public function test_a_file_already_gone_is_not_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-gone.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		unlink( $path ); // phpcs:ignore

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
	}

	public function test_a_file_outside_the_uploads_folder_is_kept_and_reported() {
		$path          = sys_get_temp_dir() . '/wpum-personal-data-test-outside.pdf';
		$this->files[] = $path;
		file_put_contents( $path, 'Not an upload.' ); // phpcs:ignore

		$member = $this->member_with_a_file( $path, 'http://localhost/wp-content/uploads/wpum-personal-data-test-outside.pdf' );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ) );
	}

	public function test_a_path_that_climbs_out_of_the_uploads_folder_is_kept_and_reported() {
		$uploads       = wp_upload_dir();
		$path          = realpath( sys_get_temp_dir() ) . '/wpum-personal-data-test-above.pdf';
		$climb         = str_repeat( '/..', substr_count( realpath( $uploads['basedir'] ), '/' ) );
		$this->files[] = $path;
		file_put_contents( $path, 'Not an upload.' ); // phpcs:ignore

		$member = $this->member_with_a_file( $uploads['basedir'] . $climb . $path, $uploads['baseurl'] . $climb . $path );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	public function test_a_link_out_of_the_uploads_folder_is_kept_and_reported() {
		$uploads       = wp_upload_dir();
		$target        = sys_get_temp_dir() . '/wpum-personal-data-test-target.pdf';
		$link          = $uploads['basedir'] . '/wpum-personal-data-test-link.pdf';
		$this->files[] = $link;
		$this->files[] = $target;
		file_put_contents( $target, 'Not an upload.' ); // phpcs:ignore
		symlink( $target, $link );

		$member = $this->member_with_a_file( $link, $uploads['baseurl'] . '/wpum-personal-data-test-link.pdf' );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $target );
	}

	/**
	 * The URL has to be exactly the recorded file's address in the uploads folder.
	 *
	 * @dataProvider other_urls
	 *
	 * @param callable $change Turns the file's URL into the stored one.
	 */
	public function test_a_url_that_is_not_the_recorded_file_is_kept_and_reported( $change ) {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-recorded.pdf' );

		$member = $this->member_with_a_file( $path, call_user_func( $change, $url ) );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	public function other_urls() {
		return array(
			'a longer name ending the same' => array(
				function ( $url ) {
					return str_replace( '/wpum-personal-data-test-recorded.pdf', '/other-wpum-personal-data-test-recorded.pdf', $url );
				},
			),
			'another folder'                => array(
				function ( $url ) {
					return str_replace( '/wpum-personal-data-test-recorded.pdf', '/2026/11/wpum-personal-data-test-recorded.pdf', $url );
				},
			),
			'a path ending the same'        => array(
				function ( $url ) {
					return str_replace( '/uploads/', '/uploads/backup/uploads/', $url );
				},
			),
			'another host'                  => array(
				function ( $url ) {
					return preg_replace( '#^(https?://)[^/:]+#', '$1example.test', $url );
				},
			),
			'another port'                  => array(
				function ( $url ) {
					return preg_replace( '#^(https?://[^/:]+)(:\d+)?#', '$1:1234', $url );
				},
			),
			'no host'                       => array(
				function ( $url ) {
					return (string) wp_parse_url( $url, PHP_URL_PATH );
				},
			),
		);
	}

	/**
	 * A site moved to https keeps its old URLs in meta, and they're still the same file.
	 */
	public function test_a_url_on_the_other_scheme_is_erased() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-scheme.pdf' );

		$member = $this->member_with_a_file( $path, str_replace( 'http://', 'https://', $url ) );
		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( $result['items_retained'] );
	}

	/**
	 * The path and the URL have to be the same file.
	 */
	public function test_a_path_that_is_not_the_stored_file_is_kept_and_reported() {
		list( $path )        = $this->create_upload( 'wpum-personal-data-test-mine.pdf' );
		list( , $other_url ) = $this->create_upload( 'wpum-personal-data-test-other.pdf' );

		$member = $this->member_with_a_file( $path, $other_url );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	public function test_a_file_another_member_uses_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-shared.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		$other  = $this->create_member();
		update_user_meta( $other, 'wpum_file_field_cv', $url );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
		$this->assertSame( $url, get_user_meta( $other, 'wpum_file_field_cv', true ) );
	}

	public function test_a_file_another_field_uses_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-reused.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		update_user_meta( $member, 'some_plugins_attachments', array( 'files' => array( $path ) ) );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	/**
	 * However another member's value writes the file's name, it's still the same file.
	 *
	 * @dataProvider references
	 *
	 * @param callable $reference Turns the file's encoded URL into what the other member has stored.
	 */
	public function test_a_file_another_member_refers_to_in_any_form_is_kept_and_reported( $reference ) {
		list( $path, $url ) = $this->create_upload( 'wp-user-manager-uploads/2026/11/shared cv (1).pdf' );

		$encoded = str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $url );
		$member  = $this->member_with_a_file( $path, $encoded );

		update_user_meta( $this->create_member(), 'some_plugins_file', call_user_func( $reference, $encoded ) );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	public function references() {
		return array(
			'a URL-encoded URL'             => array(
				function ( $encoded ) {
					return $encoded;
				},
			),
			'a URL in a query string'       => array(
				function ( $encoded ) {
					return 'https://example.test/?file=' . rawurlencode( rawurldecode( $encoded ) );
				},
			),
			'a decoded URL'                 => array( 'rawurldecode' ),
			'spaces encoded, brackets left' => array(
				function ( $encoded ) {
					return str_replace( array( '%28', '%29' ), array( '(', ')' ), $encoded );
				},
			),
			'plus signs for spaces'         => array(
				function ( $encoded ) {
					return str_replace( '%20', '+', $encoded );
				},
			),
			'inside a serialized value'     => array(
				function ( $encoded ) {
					return array( 'files' => array( array( 'url' => $encoded ) ) );
				},
			),
		);
	}

	public function test_a_file_in_the_media_library_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-library.pdf' );

		wp_insert_attachment(
			array(
				'post_title'     => 'In the media library',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$path
		);

		$member = $this->member_with_a_file( $path, $url );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	/**
	 * Only WPUM's upload handling records a path, so without one the file may not be theirs.
	 */
	public function test_a_file_with_no_recorded_path_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-no-path.pdf' );

		$this->create_field( 'CV', 'file', 'wpum_file_field_cv' );
		$member = $this->create_member();
		update_user_meta( $member, 'wpum_file_field_cv', $url );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv' ) );
	}

	/**
	 * Repeaters keep the URLs of their files among the other values in a row, with no path.
	 */
	public function test_a_file_in_a_repeater_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-row.pdf' );

		$this->create_field( 'Jobs', 'repeater', 'wpum_jobs' );
		$member = $this->create_member();
		update_user_meta(
			$member,
			'wpum_jobs',
			array(
				array(
					'wpum_title'  => 'Mathematician',
					'wpum_letter' => $url,
				),
			)
		);

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path, 'Jobs' );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_jobs' ) );
	}

	public function test_a_file_that_can_not_be_deleted_is_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-stuck.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		add_filter( 'wp_delete_file', '__return_empty_string' );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
	}

	/**
	 * Refuse to delete one meta key, until the returned filter is removed.
	 *
	 * @param string $refused_key
	 *
	 * @return callable
	 */
	private function refuse_to_delete( $refused_key ) {
		$filter = function ( $check, $object_id, $meta_key ) use ( $refused_key ) {
			return $refused_key === $meta_key ? false : $check;
		};
		add_filter( 'delete_user_metadata', $filter, 10, 3 );

		return $filter;
	}

	public function test_a_file_stays_when_its_value_can_not_be_removed_and_a_retry_erases_it() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-held.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		$filter = $this->refuse_to_delete( 'wpum_file_field_cv' );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFileExists( $path );
		$this->assertTrue( metadata_exists( 'user', $member, 'wpum_file_field_cv' ) );
		$this->assertTrue( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ), 'A retry needs the path.' );
		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertStringContainsString( 'was unable to be removed', $result['messages'][0] );

		remove_filter( 'delete_user_metadata', $filter, 10 );

		$retry = $this->erase( $this->email_of( $member ) );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv' ) );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ) );
		$this->assertTrue( $retry['items_removed'] );
		$this->assertFalse( $retry['items_retained'] );
	}

	/**
	 * With the URL gone the file can't be matched to it, so a retry keeps it and says so.
	 */
	public function test_a_file_stays_when_its_path_can_not_be_removed_and_a_retry_reports_it() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-path-held.pdf' );

		$member = $this->member_with_a_file( $path, $url );
		$filter = $this->refuse_to_delete( 'wpum_file_field_cv_path' );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFileExists( $path );
		$this->assertTrue( $result['items_retained'] );
		$this->assertStringContainsString( 'was unable to be removed', $result['messages'][0] );

		remove_filter( 'delete_user_metadata', $filter, 10 );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path );
		$this->assertFalse( metadata_exists( 'user', $member, 'wpum_file_field_cv_path' ) );
	}

	public function test_the_profile_picture_and_cover_are_erased_with_their_files() {
		$member = $this->create_member();

		list( $avatar_path, $avatar_url ) = $this->create_upload( 'wpum-personal-data-test-avatar.png' );
		list( $cover_path, $cover_url )   = $this->create_upload( 'wpum-personal-data-test-cover.png' );

		update_user_meta( $member, 'current_user_avatar', $avatar_url );
		update_user_meta( $member, '_current_user_avatar_path', $avatar_path );
		update_user_meta( $member, 'user_cover', $cover_url );
		update_user_meta( $member, '_user_cover_path', $cover_path );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );

		foreach ( array( 'current_user_avatar', '_current_user_avatar_path', 'user_cover', '_user_cover_path' ) as $key ) {
			$this->assertFalse( metadata_exists( 'user', $member, $key ), $key );
		}

		$this->assertFileDoesNotExist( $avatar_path );
		$this->assertFileDoesNotExist( $cover_path );
	}

	/**
	 * The member's own values have all gone before the file is looked at.
	 */
	public function test_a_file_used_for_the_picture_and_the_cover_is_erased() {
		$member = $this->create_member();

		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-both.png' );

		foreach ( array( 'current_user_avatar', 'user_cover' ) as $key ) {
			update_user_meta( $member, $key, $url );
		}
		update_user_meta( $member, '_current_user_avatar_path', $path );
		update_user_meta( $member, '_user_cover_path', $path );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( $result['items_retained'] );
	}

	public function test_a_picture_another_member_uses_is_kept_and_reported() {
		list( $path, $url ) = $this->create_upload( 'wpum-personal-data-test-team.png' );

		$member = $this->create_member();
		update_user_meta( $member, 'current_user_avatar', $url );
		update_user_meta( $member, '_current_user_avatar_path', $path );
		update_user_meta( $this->create_member(), 'current_user_avatar', $url );

		$this->assertFileKeptAndReported( $this->erase( $this->email_of( $member ) ), $path, 'User avatar' );
	}

	/**
	 * A picture on another site leaves nothing here to delete.
	 */
	public function test_a_picture_hosted_elsewhere_is_removed_without_a_report() {
		$member = $this->create_member();
		update_user_meta( $member, 'current_user_avatar', 'https://example.test/avatar.png' );

		$result = $this->erase( $this->email_of( $member ) );

		$this->assertFalse( metadata_exists( 'user', $member, 'current_user_avatar' ) );
		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
	}
}
