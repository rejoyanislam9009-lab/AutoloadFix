<?php
/**
 * AutoloadFix uninstall cleanup.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
while ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'autoloadfix_audit_event' );
	$timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
}

delete_option( 'autoloadfix_settings' );
delete_option( 'autoloadfix_ignored_options' );
delete_option( 'autoloadfix_watched_options' );
delete_option( 'autoloadfix_growth_notice' );
delete_option( 'autoloadfix_db_version' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}autoloadfix_snapshots" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}autoloadfix_audits" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
