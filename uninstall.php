<?php
/**
 * AutoloadFix uninstall cleanup.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'autoloadfix_settings' );

$table_name = $wpdb->prefix . 'autoloadfix_snapshots';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
