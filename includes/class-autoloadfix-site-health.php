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
	/**
	 * Scanner.
	 *
	 * @var AutoloadFix_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param AutoloadFix_Scanner $scanner Scanner service.
	 */
	public function __construct( AutoloadFix_Scanner $scanner ) {
		$this->scanner = $scanner;
		add_filter( 'site_status_tests', array( $this, 'register_test' ) );
	}

	/**
	 * Register direct Site Health test.
	 *
	 * @param array<string,mixed> $tests Tests.
	 * @return array<string,mixed>
	 */
	public function register_test( $tests ) {
		$tests['direct']['autoloadfix_autoload_health'] = array(
			'label' => __( 'AutoloadFix autoload health', 'autoloadfix' ),
			'test'  => array( $this, 'run_test' ),
		);
		return $tests;
	}

	/**
	 * Run Site Health test.
	 *
	 * @return array<string,mixed>
	 */
	public function run_test() {
		$summary = $this->scanner->get_summary();
		$good    = $summary['total_size'] <= $summary['health_limit'];

		$result = array(
			'label'       => $good ? __( 'Autoloaded options are within the recommended limit', 'autoloadfix' ) : __( 'Autoloaded options need review', 'autoloadfix' ),
			'status'      => $good ? 'good' : 'critical',
			'badge'       => array(
				'label' => __( 'Performance', 'autoloadfix' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: current autoload size, 2: limit. */
						__( 'Autoloaded options currently use %1$s. The configured health limit is %2$s.', 'autoloadfix' ),
						size_format( $summary['total_size'], 1 ),
						size_format( $summary['health_limit'], 1 )
					)
				)
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'admin.php?page=autoloadfix' ) ),
				esc_html__( 'Review with AutoloadFix', 'autoloadfix' )
			),
			'test'        => 'autoloadfix_autoload_health',
		);

		return $result;
	}
}
