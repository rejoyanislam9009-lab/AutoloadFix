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
	/**
	 * Singleton instance.
	 *
	 * @var AutoloadFix_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Scanner service.
	 *
	 * @var AutoloadFix_Scanner
	 */
	private $scanner;

	/**
	 * Snapshot service.
	 *
	 * @var AutoloadFix_Snapshot
	 */
	private $snapshot;

	/**
	 * Return singleton instance.
	 *
	 * @return AutoloadFix_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		AutoloadFix_Snapshot::install_table();

		if ( false === get_option( 'autoloadfix_settings', false ) ) {
			add_option(
				'autoloadfix_settings',
				array(
					'large_option_threshold' => 150000,
					'health_limit'           => 800000,
				),
				'',
				false
			);
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->scanner  = new AutoloadFix_Scanner();
		$this->snapshot = new AutoloadFix_Snapshot();

		if ( is_admin() ) {
			new AutoloadFix_Admin( $this->scanner, $this->snapshot );
		}

		new AutoloadFix_Site_Health( $this->scanner );
	}
}
