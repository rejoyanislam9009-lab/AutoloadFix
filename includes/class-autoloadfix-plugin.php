<?php
/**
 * Main plugin bootstrap.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AutoloadFix_Plugin {
	/** @var AutoloadFix_Plugin|null */
	private static $instance = null;
	/** @var AutoloadFix_Scanner */
	private $scanner;
	/** @var AutoloadFix_Snapshot */
	private $snapshot;
	/** @var AutoloadFix_Audit */
	private $audit;

	/** @return AutoloadFix_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Activation routine. */
	public static function activate() {
		AutoloadFix_Snapshot::install_table();
		AutoloadFix_Audit::install_table();
		if ( false === get_option( 'autoloadfix_settings', false ) ) {
			add_option( 'autoloadfix_settings', array( 'large_option_threshold' => 150000, 'health_limit' => 800000, 'audit_interval' => 'daily', 'growth_alert_percent' => 25, 'history_retention' => 30, 'read_only' => 0, 'custom_protected' => '' ), '', false );
		}
		$scanner = new AutoloadFix_Scanner();
		$audit   = new AutoloadFix_Audit( $scanner );
		$audit->sync_schedule();
		if ( ! $audit->get_latest() ) {
			$audit->record( 'activation' );
		}
		update_option( 'autoloadfix_db_version', AUTOLOADFIX_VERSION, false );
	}

	/** Deactivation routine. */
	public static function deactivate() {
		$scanner = new AutoloadFix_Scanner();
		$audit   = new AutoloadFix_Audit( $scanner );
		$audit->clear_schedule();
	}

	/** Constructor. */
	private function __construct() {
		$this->scanner  = new AutoloadFix_Scanner();
		$this->snapshot = new AutoloadFix_Snapshot();
		$this->audit    = new AutoloadFix_Audit( $this->scanner );
		$this->maybe_upgrade();

		if ( is_admin() ) {
			new AutoloadFix_Admin( $this->scanner, $this->snapshot );
			new AutoloadFix_Advanced_Admin( $this->scanner, $this->snapshot, $this->audit );
			new AutoloadFix_Cache_Advisor( $this->scanner );
			new AutoloadFix_Site_Scanner();
		}

		new AutoloadFix_Site_Health( $this->scanner );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'autoloadfix', new AutoloadFix_CLI( $this->scanner, $this->audit ) );
		}
	}

	/** Upgrade schema/settings when version changes. */
	private function maybe_upgrade() {
		$installed = (string) get_option( 'autoloadfix_db_version', '0.0.0' );
		if ( version_compare( $installed, AUTOLOADFIX_VERSION, '>=' ) ) {
			return;
		}
		AutoloadFix_Snapshot::install_table();
		AutoloadFix_Audit::install_table();
		$this->audit->sync_schedule();
		update_option( 'autoloadfix_db_version', AUTOLOADFIX_VERSION, false );
	}
}
