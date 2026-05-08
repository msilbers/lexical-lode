<?php
/**
 * Uninstall cleanup for Lexical Lode.
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin options and site transients so nothing is left behind.
 */

// Bail if WordPress didn't call this file directly as part of uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all Lexical Lode data for the current site context.
 */
function lexical_lode_delete_site_data() {
	// Plugin settings — registered via register_setting().
	delete_option( 'lexical_lode_enabled_formats' );
	delete_option( 'lexical_lode_excluded_categories' );
	delete_option( 'lexical_lode_excluded_tags' );

	// Legacy options from earlier versions.
	delete_option( 'lexical_lode_allow_live_mode' );
	delete_option( 'lexical_lode_live_ids' );
	delete_site_transient( 'lexical_lode_live_ids' );

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_lexical_lode_live_ids_%'
		    OR option_name LIKE '_transient_timeout_lexical_lode_live_ids_%'"
	);
}

// Run cleanup for the current site.
lexical_lode_delete_site_data();

// Multisite: clean up across all sites in the network.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		lexical_lode_delete_site_data();
		restore_current_blog();
	}
}
