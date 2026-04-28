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
	delete_option( 'lexical_lode_allow_live_mode' );

	// Live-ID registry — stored as a persistent option in current builds.
	delete_option( 'lexical_lode_live_ids' );

	// Legacy: the live-IDs tracker was previously a site transient.
	delete_site_transient( 'lexical_lode_live_ids' );

	// Legacy: very early builds used per-page transients with a suffix.
	// Clean any up so no orphan rows remain. The LIKE pattern is fully
	// hardcoded — no user input is interpolated — so $wpdb->prepare is
	// not required here.
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
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		lexical_lode_delete_site_data();
		restore_current_blog();
	}
}
