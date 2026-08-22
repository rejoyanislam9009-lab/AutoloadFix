<?php
/**
 * AutoloadFix uninstall cleanup.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$autoloadfix_timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
while ( $autoloadfix_timestamp ) {
	wp_unschedule_event( $autoloadfix_timestamp, 'autoloadfix_audit_event' );
	$autoloadfix_timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
}

delete_option( 'autoloadfix_settings' );
delete_option( 'autoloadfix_ignored_options' );
delete_option( 'autoloadfix_watched_options' );
delete_option( 'autoloadfix_growth_notice' );
delete_option( 'autoloadfix_db_version' );

global $wpdb;

// These tables are created and owned exclusively by AutoloadFix and are removed only on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}autoloadfix_snapshots" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}autoloadfix_audits" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
