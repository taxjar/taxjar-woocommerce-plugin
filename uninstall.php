<?php
/**
 * TaxJar Uninstall
 *
 * Uninstalling TaxJar deletes queue table and scheduled actions
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( WC_Taxjar_Transaction_Sync::PROCESS_QUEUE_HOOK );
	as_unschedule_all_actions( WC_Taxjar_Transaction_Sync::PROCESS_BATCH_HOOK );
}


/*
 * Only remove ALL tables and data if TAXJAR_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'TAXJAR_REMOVE_ALL_DATA' ) && true === TAXJAR_REMOVE_ALL_DATA ) {
	include_once dirname( __FILE__ ) . '/includes/class-wc-taxjar-install.php';

	// drop all tables installed by TaxJar
	WC_Taxjar_Install::drop_tables();

	// Delete options.
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'woocommerce\_taxjar\_%';" );

	// Clear any cached data that has been removed.
	wp_cache_flush();
}
