<?php
/**
 * WP-CLI read-only commands.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_CLI {
	/** @var AutoloadFix_Scanner */
	private $scanner;

	/** @var AutoloadFix_Audit */
	private $audit;

	/** @param AutoloadFix_Scanner $scanner Scanner. @param AutoloadFix_Audit $audit Audit. */
	public function __construct( AutoloadFix_Scanner $scanner, AutoloadFix_Audit $audit ) {
		$this->scanner = $scanner;
		$this->audit   = $audit;
	}

	/** Show current autoload health. */
	public function status() {
		$summary = $this->scanner->get_summary();
		WP_CLI::line( 'Autoload size: ' . size_format( $summary['total_size'], 1 ) );
		WP_CLI::line( 'Autoloaded options: ' . number_format_i18n( $summary['option_count'] ) );
		WP_CLI::line( 'Large options: ' . number_format_i18n( $summary['large_count'] ) );
		WP_CLI::line( 'Health score: ' . (int) $summary['score'] . '/100' );
	}

	/**
	 * Show largest autoloaded options.
	 *
	 * @param array<int,string>   $args Positional args.
	 * @param array<string,mixed> $assoc_args Named args.
	 */
	public function top( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? max( 1, min( 100, (int) $assoc_args['limit'] ) ) : 20;
		$rows  = $this->scanner->get_largest_options( $limit );
		$data  = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'option'     => $row['option_name'],
				'size'       => size_format( $row['option_size'], 1 ),
				'impact'     => $row['impact_percent'] . '%',
				'owner'      => $row['owner']['label'],
				'confidence' => (int) $row['owner']['confidence'] . '%',
				'assessment' => $row['risk']['label'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $data, array( 'option', 'size', 'impact', 'owner', 'confidence', 'assessment' ) );
	}

	/** Record a manual audit point. */
	public function audit() {
		$result = $this->audit->record( 'cli' );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( 'Audit saved. Current autoload size: ' . size_format( $result['total_size'], 1 ) );
	}
}
