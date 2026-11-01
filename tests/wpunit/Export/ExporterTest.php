<?php
/**
 * The exporter adds WPUM's custom fields to WordPress's personal data export. WordPress finds
 * the member from the request's email, writes the file and emails the link.
 */
class ExporterTest extends AddonTestCase {

	public function test_the_exporter_is_registered_and_keeps_the_others() {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array( 'other' => array( 'callback' => 'other' ) ) );

		$this->assertArrayHasKey( 'other', $exporters );
		$this->assertNotNull( $this->registered( 'wp_privacy_personal_data_exporters', 'wpumpd_export_custom_fields_user_data' ) );
		$this->assertSame( 'Additional account details', $this->registered( 'wp_privacy_personal_data_exporters', 'wpumpd_export_custom_fields_user_data' )['exporter_friendly_name'] );
	}

	/**
	 * The file, where it's kept and who it's emailed to are all WordPress's.
	 */
	public function test_wordpress_keeps_the_export_file_and_chooses_who_gets_it() {
		foreach ( array( 'wp_privacy_exports_dir', 'wp_privacy_exports_url', 'wp_privacy_personal_data_email_to', 'wp_privacy_export_expiration' ) as $filter ) {
			$this->assertFalse( has_filter( $filter ), $filter );
		}
	}

	public function test_an_unknown_email_exports_nothing() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$this->assertSame(
			array(
				'data' => array(),
				'done' => true,
			),
			$this->export( 'nobody@example.com' )
		);
	}

	public function test_a_custom_field_is_exported_under_its_name() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '01234 567890' );

		$export = $this->export( $this->email_of( $member ) );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'user', $export['data'][0]['group_id'] );
		$this->assertSame( 'Additional account details', $export['data'][0]['group_label'] );
		$this->assertSame( 'additional-user-data-' . $member, $export['data'][0]['item_id'] );
		$this->assertSame(
			array(
				array(
					'name'  => 'Phone',
					'value' => '01234 567890',
				),
			),
			$export['data'][0]['data']
		);
	}

	/**
	 * What the addon must never do: put one member's values in another's file.
	 */
	public function test_an_export_only_holds_the_requested_members_values() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$member = $this->create_member();
		$other  = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', 'Mine' );
		carbon_set_user_meta( $other, 'wpum_phone', 'Not mine' );

		$this->assertSame( array( 'Phone' => 'Mine' ), $this->exported_values( $this->email_of( $member ) ) );
		$this->assertSame( array( 'Phone' => 'Not mine' ), $this->exported_values( $this->email_of( $other ) ) );
	}

	/**
	 * The exporter runs for the administrator in wp-admin, so it must go by the email.
	 */
	public function test_it_goes_by_the_email_and_not_the_logged_in_user() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );

		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', 'The member' );

		$admin = $this->login_as_member( 'administrator' );
		carbon_set_user_meta( $admin, 'wpum_phone', 'The administrator' );

		$this->assertSame( array( 'Phone' => 'The member' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_a_field_the_member_left_empty_is_left_out() {
		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();

		$this->assertSame( array(), $this->exported_values( $this->email_of( $member ) ) );
	}

	/**
	 * WordPress exports the account's own fields, so the addon leaves them alone.
	 */
	public function test_primary_fields_are_left_to_wordpress() {
		$this->create_field( 'First name', 'user_firstname', 'firstname' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'firstname', 'Ada' );

		$this->assertSame( array(), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_the_profile_picture_and_cover_are_exported() {
		$member = $this->create_member();
		update_user_meta( $member, 'current_user_avatar', 'https://example.test/avatar.png' );
		update_user_meta( $member, 'user_cover', 'https://example.test/cover.png' );

		$this->assertSame(
			array(
				'User avatar'        => 'https://example.test/avatar.png',
				'User profile cover' => 'https://example.test/cover.png',
			),
			$this->exported_values( $this->email_of( $member ) )
		);
	}

	public function test_a_ticked_checkbox_exports_as_yes() {
		$this->create_field( 'Newsletter', 'checkbox', 'wpum_newsletter' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_newsletter', true );

		$this->assertSame( array( 'Newsletter' => 'Yes' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_a_dropdown_exports_the_label() {
		$this->create_field( 'Size', 'dropdown', 'wpum_size', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_size', 'm' );

		$this->assertSame( array( 'Size' => 'Medium' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_a_radio_exports_the_label() {
		$this->create_field( 'Fit', 'radio', 'wpum_fit', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_fit', 'l' );

		$this->assertSame( array( 'Fit' => 'Large' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_a_multiselect_exports_the_labels() {
		$this->create_field( 'Sizes', 'multiselect', 'wpum_sizes', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_sizes', array( 's', 'l' ) );

		$this->assertSame( array( 'Sizes' => 'Small, Large' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	public function test_multiple_checkboxes_export_the_labels() {
		$this->create_field( 'Days', 'multicheckbox', 'wpum_days', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_days', array( 'm', 'l' ) );

		$this->assertSame( array( 'Days' => 'Medium, Large' ), $this->exported_values( $this->email_of( $member ) ) );
	}

	/**
	 * What the addon returns goes straight into WordPress's export file.
	 */
	public function test_wordpress_can_write_the_exported_values() {
		require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';

		$this->create_field( 'Phone', 'text', 'wpum_phone' );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_phone', '<b>01234</b> <script>alert(1)</script>' );

		$export = $this->export( $this->email_of( $member ) );
		$html   = wp_privacy_generate_personal_data_export_group_html(
			array(
				'group_label'       => $export['data'][0]['group_label'],
				'group_description' => '',
				'items'             => array( $export['data'][0]['item_id'] => $export['data'][0]['data'] ),
			)
		);

		$this->assertStringContainsString( '<th>Phone</th>', $html );
		$this->assertStringContainsString( '01234', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Carbon Fields answers with the first option for a select that has no value, so a member
	 * who never chose one is reported as having chosen it.
	 *
	 * @group known-bug
	 */
	public function test_a_dropdown_the_member_left_empty_is_left_out() {
		$this->create_field( 'Size', 'dropdown', 'wpum_size', array( 'dropdown_options' => $this->field_options() ) );
		$member = $this->create_member();

		$this->assertSame( array(), $this->exported_values( $this->email_of( $member ) ) );
	}

	/**
	 * Only multiselect and multicheckbox are flattened. The array from a User or Role field
	 * allowing several values, a Taxonomy field or a Repeater goes through as it is, and
	 * WordPress's export file can't take it: str_contains() in
	 * wp_privacy_generate_personal_data_export_group_html() is a TypeError on PHP 8.
	 *
	 * @group known-bug
	 */
	public function test_a_field_with_several_values_exports_as_text() {
		$this->create_field( 'Coaches', 'user', 'wpum_coaches', array( 'allow_multiple' => true ) );
		$member = $this->create_member();
		carbon_set_user_meta( $member, 'wpum_coaches', array( '1', '2' ) );

		$values = $this->exported_values( $this->email_of( $member ) );

		$this->assertArrayHasKey( 'Coaches', $values );
		$this->assertIsString( $values['Coaches'] );
	}
}
