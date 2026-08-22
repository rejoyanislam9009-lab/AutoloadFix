<?php
/**
 * Snapshot storage and restore service.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Snapshot {
	/**
	 * Create/update the snapshot table.
	 *
	 * @return void
	 */
	public static function install_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'autoloadfix_snapshots';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(191) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			total_before bigint(20) unsigned NOT NULL DEFAULT 0,
			total_after bigint(20) unsigned NOT NULL DEFAULT 0,
			restored_at datetime NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create a snapshot.
	 *
	 * @param array<string,string> $states Option name => raw autoload state.
	 * @param string               $reason Reason.
	 * @param int                  $total_before Total autoload bytes before.
	 * @return int|false
	 */
	public function create( $states, $reason, $total_before ) {
		global $wpdb;

		if ( empty( $states ) ) {
			return false;
		}

		$payload = wp_json_encode( $states );
		if ( false === $payload ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'autoloadfix_snapshots',
			array(
				'created_at'   => current_time( 'mysql' ),
				'user_id'      => get_current_user_id(),
				'reason'       => sanitize_text_field( $reason ),
				'payload'      => $payload,
				'total_before' => max( 0, (int) $total_before ),
				'total_after'  => 0,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%d' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update snapshot after operation.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @param int $total_after Total bytes after.
	 * @return bool
	 */
	public function set_total_after( $snapshot_id, $total_after ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'autoloadfix_snapshots',
			array( 'total_after' => max( 0, (int) $total_after ) ),
			array( 'id' => (int) $snapshot_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a snapshot that did not result in a completed change.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return bool
	 */
	public function delete( $snapshot_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'autoloadfix_snapshots',
			array( 'id' => (int) $snapshot_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get latest snapshots.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent( $limit = 20 ) {
		global $wpdb;

		$limit = max( 1, min( 100, (int) $limit ) );
		$sql   = $wpdb->prepare(
			"SELECT id, created_at, user_id, reason, payload, total_before, total_after, restored_at FROM {$wpdb->prefix}autoloadfix_snapshots ORDER BY id DESC LIMIT %d",
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as &$row ) {
			$payload              = json_decode( $row['payload'], true );
			$row['changed_count'] = is_array( $payload ) ? count( $payload ) : 0;
			unset( $row['payload'] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Restore a snapshot's autoload behavior.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function restore( $snapshot_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT id, payload, restored_at FROM {$wpdb->prefix}autoloadfix_snapshots WHERE id = %d LIMIT 1",
			(int) $snapshot_id
		);
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'autoloadfix_snapshot_missing', __( 'Snapshot not found.', 'autoloadfix' ) );
		}

		$states = json_decode( $row['payload'], true );
		if ( ! is_array( $states ) || empty( $states ) ) {
			return new WP_Error( 'autoloadfix_snapshot_invalid', __( 'Snapshot data is invalid.', 'autoloadfix' ) );
		}

		$autoloading_values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes', 'on', 'auto-on', 'auto' );
		$requested          = array();

		foreach ( $states as $option_name => $raw_state ) {
			$option_name = sanitize_text_field( $option_name );
			if ( '' === $option_name ) {
				continue;
			}
			$requested[ $option_name ] = in_array( $raw_state, $autoloading_values, true );
		}

		if ( empty( $requested ) ) {
			return new WP_Error( 'autoloadfix_snapshot_empty', __( 'No valid options were found in this snapshot.', 'autoloadfix' ) );
		}

		if ( ! function_exists( 'wp_set_option_autoload_values' ) ) {
			return new WP_Error( 'autoloadfix_api_missing', __( 'Your WordPress version does not provide the required autoload API.', 'autoloadfix' ) );
		}

		$results = wp_set_option_autoload_values( $requested );
		$updated = 0;
		$failed  = 0;

		foreach ( $requested as $option_name => $should_autoload ) {
			$current_state = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$option_name
				)
			);

			if ( null === $current_state ) {
				++$failed;
				continue;
			}

			$is_autoloaded = in_array( $current_state, $autoloading_values, true );
			if ( $is_autoloaded === (bool) $should_autoload ) {
				if ( isset( $results[ $option_name ] ) && true === $results[ $option_name ] ) {
					++$updated;
				}
			} else {
				++$failed;
			}
		}

		if ( $failed > 0 ) {
			return new WP_Error(
				'autoloadfix_restore_incomplete',
				__( 'WordPress could not restore every option in this snapshot.', 'autoloadfix' )
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'autoloadfix_snapshots',
			array( 'restored_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $snapshot_id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'updated' => $updated,
			'total'   => count( $requested ),
		);
	}
}
