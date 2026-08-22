<?php
/**
 * Site Health integration.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Site_Health {
	/** @var AutoloadFix_Scanner */
	private $scanner;

	/** @param AutoloadFix_Scanner $scanner Scanner service. */
	public function __construct( AutoloadFix_Scanner $scanner ) {
		$this->scanner = $scanner;
		add_filter( 'site_status_tests', array( $this, 'register_test' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/** @param array<string,mixed> $tests Tests. @return array<string,mixed> */
	public function register_test( $tests ) {
		$tests['direct']['autoloadfix_autoload_health'] = array(
			'label' => __( 'AutoloadFix autoload health', 'autoloadfix' ),
			'test'  => array( $this, 'run_test' ),
		);
		return $tests;
	}

	/** @return array<string,mixed> */
	public function run_test() {
		$summary = $this->scanner->get_summary();
		$good    = $summary['total_size'] <= $summary['health_limit'];
		return array(
			'label'       => $good ? __( 'Autoloaded options are within the configured limit', 'autoloadfix' ) : __( 'Autoloaded options need review', 'autoloadfix' ),
			'status'      => $good ? 'good' : 'critical',
			'badge'       => array( 'label' => __( 'Performance', 'autoloadfix' ), 'color' => 'blue' ),
			'description' => sprintf( '<p>%s</p>', esc_html( sprintf( __( 'Autoloaded options currently use %1$s. The configured health limit is %2$s.', 'autoloadfix' ), size_format( $summary['total_size'], 1 ), size_format( $summary['health_limit'], 1 ) ) ) ),
			'actions'     => sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'admin.php?page=autoloadfix' ) ), esc_html__( 'Review with AutoloadFix', 'autoloadfix' ) ),
			'test'        => 'autoloadfix_autoload_health',
		);
	}

	/** @param array<string,mixed> $info Debug info. @return array<string,mixed> */
	public function debug_information( $info ) {
		$summary = $this->scanner->get_summary();
		$info['autoloadfix'] = array(
			'label'  => __( 'AutoloadFix', 'autoloadfix' ),
			'fields' => array(
				'autoload_size'  => array( 'label' => __( 'Autoload size', 'autoloadfix' ), 'value' => size_format( $summary['total_size'], 1 ) ),
				'autoload_count' => array( 'label' => __( 'Autoloaded options', 'autoloadfix' ), 'value' => number_format_i18n( $summary['option_count'] ) ),
				'health_score'   => array( 'label' => __( 'Health score', 'autoloadfix' ), 'value' => (int) $summary['score'] . '/100' ),
			),
		);
		return $info;
	}
}
