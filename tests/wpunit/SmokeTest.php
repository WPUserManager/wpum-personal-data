<?php
/**
 * Smoke tests: the addon loads, starts on WPUM's hook and its version is consistent.
 */
class SmokeTest extends AddonTestCase {

	public function test_wpum_is_active() {
		$this->assertTrue( function_exists( 'WPUM' ) );
	}

	public function test_addon_class_and_global_function_exist() {
		$this->assertTrue( class_exists( 'WPUM_Personal_Data' ) );
		$this->assertSame( WPUMPD(), WPUM_Personal_Data::instance() );
	}

	public function test_constants_are_defined() {
		$this->assertTrue( defined( 'WPUMPD_VERSION' ) );
		$this->assertDirectoryExists( WPUMPD_PLUGIN_DIR );
		$this->assertFileExists( WPUMPD_PLUGIN_FILE );
		$this->assertSame( 'wpum-personal-data/wpum-personal-data.php', WPUMPD_SLUG );
		$this->assertStringEndsWith( '/wpum-personal-data/', WPUMPD_PLUGIN_URL );
	}

	public function test_addon_starts_on_wpums_hook() {
		$this->assertSame( 10, has_action( 'after_wpum_init', array( WPUMPD(), 'init' ) ) );
	}

	/**
	 * Every place the version lives must agree, or sites see the update over and over.
	 */
	public function test_version_is_the_same_everywhere() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$header = get_plugin_data( WPUMPD_PLUGIN_FILE, false, false );
		$this->assertSame( $header['Version'], WPUMPD_VERSION, 'Plugin header vs $version property' );

		$readme = get_file_data( WPUMPD_PLUGIN_DIR . 'readme.txt', array( 'stable' => 'Stable tag' ) );
		$this->assertSame( WPUMPD_VERSION, $readme['stable'], 'readme.txt Stable tag' );

		$package = json_decode( file_get_contents( WPUMPD_PLUGIN_DIR . 'package.json' ), true ); // phpcs:ignore
		$this->assertSame( WPUMPD_VERSION, $package['version'], 'package.json version' );
		$this->assertSame( 'wpum-personal-data', $package['name'], 'package.json name names the release zip' );
	}

	public function test_release_json_deploys_to_the_addons_edd_download() {
		$release = json_decode( file_get_contents( WPUMPD_PLUGIN_DIR . 'release.json' ), true ); // phpcs:ignore

		$this->assertSame( 25551, $release['edd_download_id'] );
		$this->assertSame( 'wpum-personal-data.php', $release['main_file'] );
		$this->assertTrue( $release['channels']['edd'] );
		$this->assertFalse( $release['channels']['wordpress_org'] );
	}

	/**
	 * Deploy runs this pattern over the main file, and fails the release if it finds nothing.
	 */
	public function test_release_json_version_check_finds_the_version() {
		$release = json_decode( file_get_contents( WPUMPD_PLUGIN_DIR . 'release.json' ), true ); // phpcs:ignore
		$check   = $release['version_checks'][0];

		$this->assertSame( 1, preg_match( '/' . $check['pattern'] . '/', file_get_contents( WPUMPD_PLUGIN_DIR . $check['file'] ), $matches ) ); // phpcs:ignore
		$this->assertSame( WPUMPD_VERSION, $matches[1] );
	}

	public function test_the_pot_file_ships_with_the_addon() {
		$this->assertFileExists( WPUMPD_PLUGIN_DIR . 'languages/wpum-personal-data.pot' );
	}

	/**
	 * WPUM starts on plugins_loaded at priority 0 and the addon starts from inside that, so
	 * its own plugins_loaded hook at priority 0 is added too late to run, and translations in
	 * the addon's languages folder are never loaded. WPUM's own domain is the control.
	 *
	 * @group known-bug
	 */
	public function test_the_text_domain_loads_while_wordpress_boots() {
		$this->assertContains( 'wp-user-manager', $GLOBALS['wpumpd_boot_text_domains'] );
		$this->assertContains( 'wpum-personal-data', $GLOBALS['wpumpd_boot_text_domains'] );
	}

	public function test_requirements_pass_on_the_test_site() {
		$this->assertTrue( WPUMPD()->plugin_can_run() );
		$this->assertTrue( $this->call_method( WPUMPD(), 'addon_can_run' ) );
	}

	public function test_version_is_in_the_changelog() {
		$readme = file_get_contents( WPUMPD_PLUGIN_DIR . 'readme.txt' ); // phpcs:ignore

		$this->assertStringContainsString( '= ' . WPUMPD_VERSION . ' (', $readme );
	}
}
