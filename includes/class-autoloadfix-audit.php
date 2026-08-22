<?php
/**
 * Scheduled/manual autoload audit history.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Audit {
	/** @var AutoloadFix_Scanner */
	private $scanner;

	/**
	 * @param AutoloadFix_Scanner $scanner Scanner.
	 */
	public function __construct( AutoloadFix_Scanner $scanner ) {
		$this->scanner = $scanner;
		add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
		add_action( 'autoloadfix_audit_event', array( $this, 'run_scheduled' ) );
	}

	/** Create/update audit table. */
	public static function install_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = $wpdb->prefix . 'autoloadfix_audits';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			total_size bigint(20) unsigned NOT NULL DEFAULT 0,
			option_count bigint(20) unsigned NOT NULL DEFAULT 0,
			large_count bigint(20) unsigned NOT NULL DEFAULT 0,
			score smallint(5) unsigned NOT NULL DEFAULT 0,
			delta_bytes bigint(20) NOT NULL DEFAULT 0,
			delta_percent decimal(8,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Add a weekly schedule.
	 *
	 * @param array<string,mixed> $schedules Schedules.
	 * @return array<string,mixed>
	 */
	public function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['autoloadfix_weekly'] ) ) {
			$schedules['autoloadfix_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (AutoloadFix)', 'autoloadfix' ),
			);
		}
		return $schedules;
	}

	/** Synchronize WP-Cron with settings. */
	public function sync_schedule() {
		$settings  = $this->scanner->get_settings();
		$interval  = isset( $settings['audit_interval'] ) ? sanitize_key( $settings['audit_interval'] ) : 'daily';
		$scheduled = wp_next_scheduled( 'autoloadfix_audit_event' );

		if ( 'disabled' === $interval ) {
			$this->clear_schedule();
			return;
		}

		$desired = 'weekly' === $interval ? 'autoloadfix_weekly' : 'daily';
		if ( $scheduled ) {
			$current = wp_get_schedule( 'autoloadfix_audit_event' );
			if ( $current === $desired ) {
				return;
			}
			$this->clear_schedule();
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, $desired, 'autoloadfix_audit_event' );
	}

	/** Clear audit schedule. */
	public function clear_schedule() {
		$timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'autoloadfix_audit_event' );
			$timestamp = wp_next_scheduled( 'autoloadfix_audit_event' );
		}
	}

	/** Run scheduled audit. */
	public function run_scheduled() {
		$result = $this->record( 'scheduled' );
		if ( is_wp_error( $result ) ) {
			return;
		}
		$this->maybe_create_growth_notice( $result );
	}

	/**
	 * Record an audit.
	 *
	 * @param string $trigger Trigger type.
	 * @return array<string,mixed>|WP_Error
	 */
	public function record( $trigger = 'manual' ) {
		global $wpdb;
		$summary = $this->scanner->get_summary();
		$latest  = $this->get_latest();
		$delta   = 0;
		$percent = 0.0;

		if ( is_array( $latest ) ) {
			$delta = (int) $summary['total_size'] - (int) $latest['total_size'];
			if ( (int) $latest['total_size'] > 0 ) {
				$percent = ( $delta / (int) $latest['total_size'] ) * 100;
			}
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'autoloadfix_audits',
			array(
				'created_at'    => current_time( 'mysql' ),
				'trigger_type'  => sanitize_key( $trigger ),
				'total_size'    => (int) $summary['total_size'],
				'option_count'  => (int) $summary['option_count'],
				'large_count'   => (int) $summary['large_count'],
				'score'         => (int) $summary['score'],
				'delta_bytes'   => $delta,
				'delta_percent' => round( $percent, 2 ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'autoloadfix_audit_write_failed', __( 'AutoloadFix could not save the audit result.', 'autoloadfix' ) );
		}

		$this->prune();
		return array(
			'id'            => (int) $wpdb->insert_id,
			'total_size'    => (int) $summary['total_size'],
			'option_count'  => (int) $summary['option_count'],
			'large_count'   => (int) $summary['large_count'],
			'score'         => (int) $summary['score'],
			'delta_bytes'   => $delta,
			'delta_percent' => round( $percent, 2 ),
		);
	}

	/** @return array<string,mixed>|null */
	public function get_latest() {
		global $wpdb;
		$row = $wpdb->get_row( "SELECT id, created_at, trigger_type, total_size, option_count, large_count, score, delta_bytes, delta_percent FROM {$wpdb->prefix}autoloadfix_audits ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get recent audits.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent( $limit = 30 ) {
		global $wpdb;
		$limit = max( 1, min( 100, (int) $limit ) );
		$sql   = $wpdb->prepare( "SELECT id, created_at, trigger_type, total_size, option_count, large_count, score, delta_bytes, delta_percent FROM {$wpdb->prefix}autoloadfix_audits ORDER BY id DESC LIMIT %d", $limit );
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/** @return int|false */
	public function get_next_scheduled() {
		return wp_next_scheduled( 'autoloadfix_audit_event' );
	}

	/** Prune old rows according to retention. */
	private function prune() {
		global $wpdb;
		$settings  = $this->scanner->get_settings();
		$retention = max( 7, min( 365, (int) $settings['history_retention'] ) );
		$cutoff    = wp_date( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}autoloadfix_audits WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Save a notice when scheduled growth exceeds threshold.
	 *
	 * @param array<string,mixed> $result Audit result.
	 * @return void
	 */
	private function maybe_create_growth_notice( $result ) {
		$settings  = $this->scanner->get_settings();
		$threshold = max( 1, min( 500, (int) $settings['growth_alert_percent'] ) );
		if ( isset( $result['delta_percent'] ) && (float) $result['delta_percent'] >= $threshold ) {
			update_option(
				'autoloadfix_growth_notice',
				array(
					'created_at'    => current_time( 'mysql' ),
					'delta_bytes'   => (int) $result['delta_bytes'],
					'delta_percent' => (float) $result['delta_percent'],
				),
				false
			);
		}
	}
}
