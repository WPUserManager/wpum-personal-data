<?php
/**
 * WPLoader configFile: runs before WordPress boots, and creates the WPUM custom tables
 * before plugins load.
 *
 * WPLoader (isolatedInstall: false) bootstraps WordPress, then drops core
 * tables, then runs wp_install(). During the initial wp-settings.php load
 * WPUM's init fires, and it reads its own tables, so they have to exist
 * already. This file hooks into muplugins_loaded (before plugins_loaded) to
 * create them ahead of time.
 */

require_once dirname( __DIR__, 2 ) . '/vendor/lucatume/wp-browser/src/includes/functions.php';

// The text domains loaded while WordPress boots, which no test can see once it has finished.
$GLOBALS['wpumpd_boot_text_domains'] = array();
tests_add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	$GLOBALS['wpumpd_boot_text_domains'][] = $domain;

	return $mofile;
}, 10, 2 );

tests_add_filter( 'muplugins_loaded', function () {
	global $wpdb;

	require_once ABSPATH . '/wp-admin/includes/upgrade.php';

	$charset_collation = $wpdb->get_charset_collate();
	$max_index_length  = 191;

	$tables = [
		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_fieldsgroups (
			id bigint(20) unsigned NOT NULL auto_increment,
			group_order bigint(20) unsigned NOT NULL default '0',
			is_primary bool NOT NULL DEFAULT '0',
			name varchar(190) NOT NULL default '',
			description longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY name (name(190)),
			KEY group_order (group_order)
		) {$charset_collation};",

		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_fields (
			id bigint(20) unsigned NOT NULL auto_increment,
			group_id bigint(20) unsigned NOT NULL default '0',
			field_order bigint(20) unsigned NOT NULL default '0',
			type varchar(20) NOT NULL default 'text',
			name varchar(255) NOT NULL default '',
			description longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY group_id (group_id),
			KEY field_order (field_order)
		) {$charset_collation};",

		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_fieldmeta (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			wpum_field_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY (meta_id),
			KEY wpum_field_id (wpum_field_id),
			KEY meta_key (meta_key({$max_index_length}))
		) {$charset_collation};",

		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_registration_forms (
			id bigint(20) unsigned NOT NULL auto_increment,
			name varchar(255) NOT NULL default '',
			PRIMARY KEY (id)
		) {$charset_collation};",

		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_registration_formmeta (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			wpum_registration_form_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY (meta_id),
			KEY wpum_registration_form_id (wpum_registration_form_id),
			KEY meta_key (meta_key({$max_index_length}))
		) {$charset_collation};",

		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_search_fields (
			id bigint(20) unsigned NOT NULL auto_increment,
			meta_key varchar(255) NOT NULL default '',
			PRIMARY KEY (id)
		) {$charset_collation};",
	];

	foreach ( $tables as $sql ) {
		dbDelta( $sql );
	}
}, 0 );

